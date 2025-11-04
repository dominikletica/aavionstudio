<?php

declare(strict_types=1);

namespace App\Controller\Installer;

use App\Installer\Action\ActionExecutorInterface;
use App\Setup\SetupAccessToken;
use App\Setup\SetupState;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\HttpKernel\Profiler\Profiler;
use Symfony\Contracts\Translation\TranslatorInterface;

final class ActionController extends AbstractController
{
    private const MODE_STREAM_KEEP_SESSION = 'stream_keep_session';
    private const MODE_BUFFERED = 'buffered';

    public function __construct(
        private readonly ActionExecutorInterface $actionExecutor,
        private readonly SetupState $setupState,
        private readonly SetupAccessToken $setupAccessToken,
        private readonly Security $security,
        private readonly UrlGeneratorInterface $urlGenerator,
        private readonly TranslatorInterface $translator,
        private readonly ?Profiler $profiler = null,
        #[Autowire('%app.installer_action_mode%')]
        private readonly string $actionMode = self::MODE_STREAM_KEEP_SESSION,
    ) {
    }

    #[Route('/setup/action', name: 'app_installer_action', methods: ['POST'])]
    public function __invoke(Request $request): Response
    {
        if ($this->setupState->isCompleted() && ! $this->security->isGranted('ROLE_ADMIN')) {
            return $this->redirect($this->urlGenerator->generate('app_login'));
        }

        $payload = $this->extractPayload($request);

        $providedToken = $payload['token'] ?? $request->headers->get('X-Setup-Token');
        if (! $this->setupAccessToken->validate(\is_string($providedToken) ? $providedToken : null)) {
            return $this->json(['error' => $this->translator->trans('installer.errors.invalid_token')], Response::HTTP_FORBIDDEN);
        }

        $session = $request->getSession();
        if ($session !== null && ! $session->isStarted()) {
            $session->start();
        }

        $context = (string) ($payload['context'] ?? 'generic');
        $steps = $payload['steps'] ?? $payload['commands'] ?? [];

        if (!\is_array($steps)) {
            return $this->json(['error' => $this->translator->trans('installer.errors.invalid_action_format')], Response::HTTP_BAD_REQUEST);
        }

        /** @var UploadedFile|null $package */
        $package = $request->files->get('package');

        $this->profiler?->disable();

        return match ($this->actionMode) {
            self::MODE_BUFFERED => $this->createBufferedResponse($steps, $package, $context, $session),
            default => $this->createStreamedResponse($steps, $package, $context, $session),
        };
    }

    /**
     * @param array<int,array<string,mixed>> $steps
     */
    private function createStreamedResponse(array $steps, ?UploadedFile $package, string $context, ?SessionInterface $session): StreamedResponse
    {
        $response = new StreamedResponse(function () use ($steps, $package, $context, $session): void {
            ignore_user_abort(true);
            set_time_limit(0);

            if (PHP_SAPI !== 'cli') {
                if (function_exists('apache_setenv')) {
                    @apache_setenv('no-gzip', '1');
                }

                while (ob_get_level() > 0) {
                    ob_end_flush();
                }

                ob_implicit_flush(true);
            }

            $emit = function (string $type, string $message = '', array $extra = []): void {
                $line = json_encode($this->createEventPayload($type, $message, $extra), JSON_UNESCAPED_UNICODE);

                if ($line !== false) {
                    echo $line."\n";
                    flush();
                }
            };

            $emit('heartbeat', 'ready');

            if (PHP_SAPI !== 'cli') {
                echo str_repeat(' ', 4096)."\n";
                flush();
            }

            try {
                $this->actionExecutor->execute($steps, $package, $emit);
                $emit('done', 'success', ['context' => $context]);
            } catch (\Throwable $exception) {
                $emit('error', $exception->getMessage());
                $emit('done', 'error', ['context' => $context]);
            } finally {
                $this->finalizeSession($session);
            }
        });

        $response->headers->set('Content-Type', 'application/x-ndjson; charset=utf-8');
        $response->headers->set('Cache-Control', 'no-cache');
        $response->headers->set('X-Accel-Buffering', 'no');

        return $response;
    }

    /**
     * @param array<int,array<string,mixed>> $steps
     */
    private function createBufferedResponse(array $steps, ?UploadedFile $package, string $context, ?SessionInterface $session): JsonResponse
    {
        $events = [];
        $emit = function (string $type, string $message = '', array $extra = []) use (&$events): void {
            $events[] = $this->createEventPayload($type, $message, $extra);
        };

        $emit('heartbeat', 'ready');

        $status = Response::HTTP_OK;

        try {
            $this->actionExecutor->execute($steps, $package, $emit);
            $emit('done', 'success', ['context' => $context]);
        } catch (\Throwable $exception) {
            $emit('error', $exception->getMessage());
            $emit('done', 'error', ['context' => $context]);
            $status = Response::HTTP_INTERNAL_SERVER_ERROR;
        } finally {
            $this->finalizeSession($session);
        }

        return new JsonResponse([
            'mode' => self::MODE_BUFFERED,
            'events' => $events,
        ], $status);
    }

    /**
     * @return array{type:string,message:string,extra:array<string,mixed>,timestamp:string}
     */
    private function createEventPayload(string $type, string $message = '', array $extra = []): array
    {
        return [
            'type' => $type,
            'message' => $message,
            'extra' => $extra,
            'timestamp' => (new \DateTimeImmutable())->format(\DateTimeInterface::ATOM),
        ];
    }

    private function finalizeSession(?SessionInterface $session): void
    {
        if ($session !== null && $session->isStarted()) {
            $session->save();
        }
    }

    /**
     * @return array<string,mixed>
     */
    private function extractPayload(Request $request): array
    {
        if ($request->getContentTypeFormat() === 'json') {
            $data = json_decode((string) $request->getContent(), true);

            return \is_array($data) ? $data : [];
        }

        $data = $request->request->all();

        if (isset($data['steps']) && \is_string($data['steps'])) {
            $decoded = json_decode($data['steps'], true);
            if (\is_array($decoded)) {
                $data['steps'] = $decoded;
            }
        }

        if (isset($data['commands']) && \is_string($data['commands'])) {
            $decoded = json_decode($data['commands'], true);
            if (\is_array($decoded)) {
                $data['commands'] = $decoded;
            }
        }

        return $data;
    }
}
