<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Security\Audit\SecurityAuditRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_ADMIN')]
final class SecurityAuditController extends AbstractController
{
    use AdminNavigationTrait;

    public function __construct(
        private readonly SecurityAuditRepository $auditRepository,
    ) {
    }

    #[Route('/admin/security/audit', name: 'admin_security_audit', methods: ['GET'])]
    public function index(Request $request): Response
    {
        $action = (string) $request->query->get('action', '');
        $actor = (string) $request->query->get('actor', '');
        $subject = (string) $request->query->get('subject', '');
        $from = $this->parseDate((string) $request->query->get('from'));
        $to = $this->parseDate((string) $request->query->get('to'));

        $entries = $this->auditRepository->search(
            $action !== '' ? $action : null,
            $actor !== '' ? $actor : null,
            $subject !== '' ? $subject : null,
            $from,
            $to,
        );

        return $this->render('pages/admin/security/audit.html.twig', array_merge([
            'entries' => $entries,
            'actions' => $this->auditRepository->getAvailableActions(),
            'filters' => [
                'action' => $action,
                'actor' => $actor,
                'subject' => $subject,
                'from' => $request->query->get('from'),
                'to' => $request->query->get('to'),
            ],
        ], $this->adminNavigation('security', 'audit')));
    }

    private function parseDate(string $value): ?\DateTimeImmutable
    {
        if ($value === '') {
            return null;
        }

        try {
            return new \DateTimeImmutable($value);
        } catch (\Exception) {
            return null;
        }
    }
}
