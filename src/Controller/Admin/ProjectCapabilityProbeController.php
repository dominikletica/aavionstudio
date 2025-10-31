<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Security\Authorization\ProjectCapabilityRequirement;
use App\Security\Authorization\ProjectCapabilityVoter;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/projects', name: 'admin_project_')]
final class ProjectCapabilityProbeController extends AbstractController
{
    #[Route('/{projectId}/capability/{capability}/probe', name: 'capability_probe', methods: ['GET'])]
    public function __invoke(string $projectId, string $capability): Response
    {
        $requirement = new ProjectCapabilityRequirement($capability, $projectId);

        if (!$this->isGranted(ProjectCapabilityVoter::ATTRIBUTE, $requirement)) {
            throw $this->createAccessDeniedException();
        }

        return new JsonResponse([
            'status' => 'ok',
            'project' => $projectId,
            'capability' => $capability,
        ]);
    }
}
