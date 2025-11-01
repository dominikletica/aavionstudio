<?php

declare(strict_types=1);

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

final class DemoController extends AbstractController
{
    #[Route('/_themedemo', name: '_theme_demo')]
    public function __invoke(): Response
    {
        return $this->render('pages/demo.html.twig');
    }

    #[Route('/_themedemo/tip', name: '_theme_demo_tip', methods: ['GET'])]
    public function tip(Request $request): Response
    {
        $tips = [
            [
                'title' => 'Keep Tailwind tokens in sync',
                'description' => 'Theme overrides should ship their own theme.css but continue importing the base tokens to avoid regressions.',
            ],
            [
                'title' => 'Prefer partial components',
                'description' => 'Composer-facing templates stay lean when they reuse buttons, cards, tables, and forms from the component library.',
            ],
            [
                'title' => 'Bundle hero content',
                'description' => 'Use the header partial with project- or admin-specific imagery. Themes can override the same block without touching controllers.',
            ],
            [
                'title' => 'Document overrides',
                'description' => 'Whenever you override templates, update the developer docs and class map so contributors discover them quickly.',
            ],
        ];

        $index = (int) $request->query->get('i', -1);

        if (!isset($tips[$index])) {
            $index = array_rand($tips);
        }

        return $this->render('pages/demo/_tip.html.twig', [
            'tip' => $tips[$index],
        ]);
    }
}
