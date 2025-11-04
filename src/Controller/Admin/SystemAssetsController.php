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
use Symfony\Contracts\Translation\TranslatorInterface;

#[IsGranted('ROLE_ADMIN')]
final class SystemAssetsController extends AbstractController
{
    use AdminNavigationTrait;

    public function __construct(
        private readonly AssetRebuildScheduler $scheduler,
        private readonly AssetStateTracker $stateTracker,
        private readonly ModuleRegistry $moduleRegistry,
        private readonly ThemeRegistry $themeRegistry,
        private readonly ModuleStateRepository $moduleStateRepository,
        private readonly ThemeStateRepository $themeStateRepository,
        private readonly TranslatorInterface $translator,
    ) {
    }

    #[Route('/admin/system/assets', name: 'admin_assets_overview', methods: ['GET'])]
    public function overview(): Response
    {
        $currentState = $this->stateTracker->currentState();
        $persistedState = $this->stateTracker->readPersistedState();

        return $this->render('pages/admin/system/assets.html.twig', array_merge([
            'modules' => $this->moduleRegistry->all(),
            'themes' => $this->themeRegistry->all(),
            'current_state' => $currentState,
            'persisted_state' => $persistedState,
        ], $this->adminNavigation('system', 'assets')));
    }

    #[Route('/admin/system/assets/rebuild', name: 'admin_assets_rebuild', methods: ['POST'])]
    public function rebuild(Request $request): Response
    {
        $token = (string) $request->request->get('_token');
        if (!$this->isCsrfTokenValid('admin_assets_rebuild', $token)) {
            $this->addFlash('error', $this->translator->trans('flash.common.invalid_csrf_retry'));

            return $this->redirectToRoute('admin_assets_overview');
        }

        $mode = (string) $request->request->get('mode', 'queue');
        $force = $request->request->getBoolean('force', false);

        if ($mode === 'sync') {
            try {
                $executed = $this->scheduler->runNow($force);
                $this->addFlash(
                    $executed ? 'success' : 'info',
                    $this->translator->trans($executed ? 'flash.assets.rebuild.success' : 'flash.assets.rebuild.up_to_date')
                );
            } catch (\Throwable $exception) {
                $this->addFlash('error', $this->translator->trans('flash.assets.rebuild.failed', ['%error%' => $exception->getMessage()]));
            }
        } else {
            $queued = $this->scheduler->schedule($force);
            $this->addFlash(
                $queued ? 'success' : 'info',
                $this->translator->trans($queued ? 'flash.assets.rebuild.queued' : 'flash.assets.rebuild.queued_skip')
            );
        }

        return $this->redirectToRoute('admin_assets_overview');
    }

    #[Route('/admin/system/assets/modules/{slug}/toggle', name: 'admin_assets_module_toggle', methods: ['POST'])]
    public function toggleModule(Request $request, string $slug): Response
    {
        $token = (string) $request->request->get('_token');
        if (!$this->isCsrfTokenValid('module_state_'.$slug, $token)) {
            $this->addFlash('error', $this->translator->trans('flash.common.invalid_csrf'));

            return $this->redirectToRoute('admin_assets_overview');
        }

        $manifest = $this->moduleRegistry->find($slug);
        if ($manifest === null) {
            $this->addFlash('error', $this->translator->trans('flash.assets.module.not_found', ['%slug%' => $slug]));

            return $this->redirectToRoute('admin_assets_overview');
        }

        $metadata = $manifest->metadata;
        if (!empty($metadata['locked'])) {
            $this->addFlash('info', $this->translator->trans('flash.assets.module.locked', ['%name%' => $manifest->name]));

            return $this->redirectToRoute('admin_assets_overview');
        }

        $action = (string) $request->request->get('action', '');
        $targetState = match ($action) {
            'enable' => true,
            'disable' => false,
            default => null,
        };

        if ($targetState === null) {
            $this->addFlash('error', $this->translator->trans('flash.assets.module.unknown_action'));

            return $this->redirectToRoute('admin_assets_overview');
        }

        $this->moduleStateRepository->setEnabled($slug, $targetState);
        $this->scheduler->schedule(true);

        $this->addFlash(
            'success',
            $this->translator->trans(
                $targetState ? 'flash.assets.module.enabled' : 'flash.assets.module.disabled',
                ['%name%' => $manifest->name]
            )
        );

        return $this->redirectToRoute('admin_assets_overview');
    }

    #[Route('/admin/system/assets/themes/{slug}/toggle', name: 'admin_assets_theme_toggle', methods: ['POST'])]
    public function toggleTheme(Request $request, string $slug): Response
    {
        $token = (string) $request->request->get('_token');
        if (!$this->isCsrfTokenValid('theme_state_'.$slug, $token)) {
            $this->addFlash('error', $this->translator->trans('flash.common.invalid_csrf'));

            return $this->redirectToRoute('admin_assets_overview');
        }

        $manifest = $this->themeRegistry->find($slug);
        if ($manifest === null) {
            $this->addFlash('error', $this->translator->trans('flash.assets.theme.not_found', ['%slug%' => $slug]));

            return $this->redirectToRoute('admin_assets_overview');
        }

        $metadata = $manifest->metadata;
        if (!empty($metadata['locked'])) {
            $this->addFlash('info', $this->translator->trans('flash.assets.theme.locked_disable', ['%name%' => $manifest->name]));

            return $this->redirectToRoute('admin_assets_overview');
        }

        $action = (string) $request->request->get('action', '');
        $targetState = match ($action) {
            'enable' => true,
            'disable' => false,
            default => null,
        };

        if ($targetState === null) {
            $this->addFlash('error', $this->translator->trans('flash.assets.theme.unknown_action'));

            return $this->redirectToRoute('admin_assets_overview');
        }

        $this->themeStateRepository->setEnabled($slug, $targetState);

        if (!$targetState && $manifest->active) {
            $this->themeStateRepository->activate('base');
        }

        $this->scheduler->schedule(true);

        $this->addFlash(
            'success',
            $this->translator->trans(
                $targetState ? 'flash.assets.theme.enabled' : 'flash.assets.theme.disabled',
                ['%name%' => $manifest->name]
            )
        );

        return $this->redirectToRoute('admin_assets_overview');
    }

    #[Route('/admin/system/assets/themes/{slug}/activate', name: 'admin_assets_theme_activate', methods: ['POST'])]
    public function activateTheme(Request $request, string $slug): Response
    {
        $token = (string) $request->request->get('_token');
        if (!$this->isCsrfTokenValid('theme_activate_'.$slug, $token)) {
            $this->addFlash('error', $this->translator->trans('flash.common.invalid_csrf'));

            return $this->redirectToRoute('admin_assets_overview');
        }

        $manifest = $this->themeRegistry->find($slug);
        if ($manifest === null) {
            $this->addFlash('error', $this->translator->trans('flash.assets.theme.not_found', ['%slug%' => $slug]));

            return $this->redirectToRoute('admin_assets_overview');
        }

        if (!empty($manifest->metadata['locked'])) {
            $this->addFlash('info', $this->translator->trans('flash.assets.theme.locked_always', ['%name%' => $manifest->name]));

            return $this->redirectToRoute('admin_assets_overview');
        }

        if (!$manifest->enabled) {
            $this->themeStateRepository->setEnabled($slug, true);
        }

        $this->themeStateRepository->activate($slug);
        $this->scheduler->schedule(true);

        $this->addFlash('success', $this->translator->trans('flash.assets.theme.activated', ['%name%' => $manifest->name]));

        return $this->redirectToRoute('admin_assets_overview');
    }
}
