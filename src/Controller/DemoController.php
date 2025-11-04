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
                'title_key' => 'demo.tips.keep_tokens.title',
                'description_key' => 'demo.tips.keep_tokens.description',
            ],
            [
                'title_key' => 'demo.tips.prefer_partials.title',
                'description_key' => 'demo.tips.prefer_partials.description',
            ],
            [
                'title_key' => 'demo.tips.bundle_hero.title',
                'description_key' => 'demo.tips.bundle_hero.description',
            ],
            [
                'title_key' => 'demo.tips.document_overrides.title',
                'description_key' => 'demo.tips.document_overrides.description',
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
