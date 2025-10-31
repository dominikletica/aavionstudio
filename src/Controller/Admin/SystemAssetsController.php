<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Asset\AssetStateTracker;
use App\Service\AssetRebuildScheduler;
use App\Module\ModuleRegistry;
use App\Theme\ThemeRegistry;
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
}
