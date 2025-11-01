<?php

declare(strict_types=1);

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

final class DemoController extends AbstractController
{
    #[Route('/_themedemo', name: '_theme_demo')]
    public function __invoke(): Response
    {
        return $this->render('pages/demo.html.twig');
    }
}
