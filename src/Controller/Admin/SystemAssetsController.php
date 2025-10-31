<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Asset\AssetStateTracker;
use App\Module\ModuleRegistry;
use App\Module\ModuleStateRepository;
use App\Service\AssetRebuildScheduler;
use App\Theme\ThemeRegistry;
use App\Theme\ThemeStateRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_ADMIN')]
final class SystemAssetsController extends AbstractController
{
    public function __construct(
        private readonly AssetRebuildScheduler $scheduler,
        private readonly AssetStateTracker $stateTracker,
        private readonly ModuleRegistry $moduleRegistry,
        private readonly ThemeRegistry $themeRegistry,
        private readonly ModuleStateRepository $moduleStateRepository,
        private readonly ThemeStateRepository $themeStateRepository,
    ) {
    }

    #[Route('/admin/system/assets', name: 'admin_assets_overview', methods: ['GET'])]
    public function overview(): Response
    {
        return $this->render('admin/system/assets.html.twig', [
            'modules' => $this->moduleRegistry->all(),
            'themes' => $this->themeRegistry->all(),
            'current_state' => $this->stateTracker->currentState(),
            'persisted_state' => $this->stateTracker->readPersistedState(),
        ]);
    }

    #[Route('/admin/system/assets/rebuild', name: 'admin_assets_rebuild', methods: ['POST'])]
    public function rebuild(Request $request): Response
    {
        $token = (string) $request->request->get('_token');
        if (!$this->isCsrfTokenValid('admin_assets_rebuild', $token)) {
            $this->addFlash('error', 'Invalid CSRF token. Please try again.');

            return $this->redirectToRoute('admin_assets_overview');
        }

        $mode = (string) $request->request->get('mode', 'queue');
        $force = $request->request->getBoolean('force', false);

        if ($mode === 'sync') {
            try {
                $executed = $this->scheduler->runNow($force);
                $this->addFlash($executed ? 'success' : 'info', $executed ? 'Asset pipeline rebuilt successfully.' : 'Assets already up to date; nothing to rebuild.');
            } catch (\Throwable $exception) {
                $this->addFlash('error', sprintf('Asset rebuild failed: %s', $exception->getMessage()));
            }
        } else {
            $queued = $this->scheduler->schedule($force);
            $this->addFlash($queued ? 'success' : 'info', $queued ? 'Queued asset rebuild job.' : 'Assets already up to date; no rebuild queued.');
        }

        return $this->redirectToRoute('admin_assets_overview');
    }

    #[Route('/admin/system/assets/modules/{slug}/toggle', name: 'admin_assets_module_toggle', methods: ['POST'])]
    public function toggleModule(Request $request, string $slug): Response
    {
        $token = (string) $request->request->get('_token');
        if (!$this->isCsrfTokenValid('module_state_'.$slug, $token)) {
            $this->addFlash('error', 'Invalid CSRF token.');

            return $this->redirectToRoute('admin_assets_overview');
        }

        $manifest = $this->moduleRegistry->find($slug);
        if ($manifest === null) {
            $this->addFlash('error', sprintf('Module "%s" not found.', $slug));

            return $this->redirectToRoute('admin_assets_overview');
        }

        $metadata = $manifest->metadata;
        if (!empty($metadata['locked'])) {
            $this->addFlash('info', sprintf('Module "%s" is locked and cannot be modified.', $manifest->name));

            return $this->redirectToRoute('admin_assets_overview');
        }

        $action = (string) $request->request->get('action', '');
        $targetState = match ($action) {
            'enable' => true,
            'disable' => false,
            default => null,
        };

        if ($targetState === null) {
            $this->addFlash('error', 'Unknown module action.');

            return $this->redirectToRoute('admin_assets_overview');
        }

        $this->moduleStateRepository->setEnabled($slug, $targetState);
        $this->scheduler->schedule(true);

        $this->addFlash('success', sprintf('Module "%s" %s.', $manifest->name, $targetState ? 'enabled' : 'disabled'));

        return $this->redirectToRoute('admin_assets_overview');
    }

    #[Route('/admin/system/assets/themes/{slug}/toggle', name: 'admin_assets_theme_toggle', methods: ['POST'])]
    public function toggleTheme(Request $request, string $slug): Response
    {
        $token = (string) $request->request->get('_token');
        if (!$this->isCsrfTokenValid('theme_state_'.$slug, $token)) {
            $this->addFlash('error', 'Invalid CSRF token.');

            return $this->redirectToRoute('admin_assets_overview');
        }

        $manifest = $this->themeRegistry->find($slug);
        if ($manifest === null) {
            $this->addFlash('error', sprintf('Theme "%s" not found.', $slug));

            return $this->redirectToRoute('admin_assets_overview');
        }

        $metadata = $manifest->metadata;
        if (!empty($metadata['locked'])) {
            $this->addFlash('info', sprintf('Theme "%s" is locked and cannot be disabled.', $manifest->name));

            return $this->redirectToRoute('admin_assets_overview');
        }

        $action = (string) $request->request->get('action', '');
        $targetState = match ($action) {
            'enable' => true,
            'disable' => false,
            default => null,
        };

        if ($targetState === null) {
            $this->addFlash('error', 'Unknown theme action.');

            return $this->redirectToRoute('admin_assets_overview');
        }

        $this->themeStateRepository->setEnabled($slug, $targetState);

        if (!$targetState && $manifest->active) {
            $this->themeStateRepository->activate('base');
        }

        $this->scheduler->schedule(true);

        $this->addFlash('success', sprintf('Theme "%s" %s.', $manifest->name, $targetState ? 'enabled' : 'disabled'));

        return $this->redirectToRoute('admin_assets_overview');
    }

    #[Route('/admin/system/assets/themes/{slug}/activate', name: 'admin_assets_theme_activate', methods: ['POST'])]
    public function activateTheme(Request $request, string $slug): Response
    {
        $token = (string) $request->request->get('_token');
        if (!$this->isCsrfTokenValid('theme_activate_'.$slug, $token)) {
            $this->addFlash('error', 'Invalid CSRF token.');

            return $this->redirectToRoute('admin_assets_overview');
        }

        $manifest = $this->themeRegistry->find($slug);
        if ($manifest === null) {
            $this->addFlash('error', sprintf('Theme "%s" not found.', $slug));

            return $this->redirectToRoute('admin_assets_overview');
        }

        if (!empty($manifest->metadata['locked'])) {
            $this->addFlash('info', sprintf('Theme "%s" is locked and always available.', $manifest->name));

            return $this->redirectToRoute('admin_assets_overview');
        }

        if (!$manifest->enabled) {
            $this->themeStateRepository->setEnabled($slug, true);
        }

        $this->themeStateRepository->activate($slug);
        $this->scheduler->schedule(true);

        $this->addFlash('success', sprintf('Theme "%s" is now active.', $manifest->name));

        return $this->redirectToRoute('admin_assets_overview');
    }
}
