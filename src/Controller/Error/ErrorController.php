<?php

declare(strict_types=1);

namespace App\Controller\Error;

use App\Error\ErrorPageResolver;
use App\Project\ProjectRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\ErrorHandler\ErrorRenderer\HtmlErrorRenderer;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Twig\Error\Error as TwigError;
use Twig\Environment;

final class ErrorController extends AbstractController
{
    public function __construct(
        private readonly ProjectRepository $projectRepository,
        private readonly ErrorPageResolver $errorPageResolver,
        private readonly Environment $twig,
        #[Autowire('%app.default_project_slug%')] private readonly string $defaultProjectSlug,
        #[Autowire('%kernel.debug%')] private readonly bool $debug,
    ) {
    }

    public function show(Request $request, \Throwable $exception): Response
    {
        $statusCode = $this->resolveStatusCode($exception, $request);

        try {
            $project = $this->projectRepository->findBySlug($this->defaultProjectSlug);
            $projectSettings = [];
            $projectName = null;

            if (is_array($project)) {
                $projectSettings = $project['settings'] ?? [];
                $projectName = $project['name'] ?? ($project['slug'] ?? null);
            } else {
                $project = null;
            }

            $template = $this->errorPageResolver->resolve($projectSettings, $statusCode);
            if ($template === null || !$this->twig->getLoader()->exists($template)) {
                $template = 'pages/error/default.html.twig';
            }

            $context = [
                'status_code' => $statusCode,
                'status_text' => Response::$statusTexts[$statusCode] ?? 'Error',
                'project' => $project,
                'project_name' => $projectName,
                'debug' => $this->debug,
                'exception_message' => $this->debug ? $exception->getMessage() : null,
                'stack' => $this->debug ? array_slice($exception->getTrace(), 0, 8) : [],
                'request' => $request,
            ];

            $content = $this->twig->render($template, $context);
            $response = new Response($content, $statusCode);
            $response->headers->set('Content-Type', 'text/html; charset=UTF-8');

            if ($this->debug) {
                $response->headers->set('X-Debug-Exception', rawurlencode($exception->getMessage()));
                $response->headers->set('X-Debug-Exception-File', rawurlencode($exception->getFile().':'.$exception->getLine()));
            }

            return $response;
        } catch (TwigError|\Throwable $renderException) {
            if ($renderException instanceof TwigError) {
                fwrite(STDERR, 'Twig error while rendering error page: '.$renderException->getMessage()."\n");
                $renderer = new HtmlErrorRenderer($this->debug);
                $flatten = $renderer->render($exception);

                return new Response($flatten->getAsString(), $flatten->getStatusCode(), $flatten->getHeaders());
            }

            throw $renderException;
        }
    }

    private function resolveStatusCode(\Throwable $exception, Request $request): int
    {
        if ($exception instanceof HttpExceptionInterface) {
            return $exception->getStatusCode();
        }

        $status = $request->attributes->getInt('exception_statuscode', 0);

        if ($status >= 400 && $status < 600) {
            return $status;
        }

        return 500;
    }
}
