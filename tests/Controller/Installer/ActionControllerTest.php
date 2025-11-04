<?php

declare(strict_types=1);

namespace App\Tests\Controller\Installer;

use App\Controller\Installer\ActionController;
use App\Installer\Action\ActionExecutorInterface;
use App\Setup\SetupAccessToken;
use App\Setup\SetupState;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\HttpKernel\Profiler\Profiler;
use Symfony\Contracts\Translation\TranslatorInterface;

final class ActionControllerTest extends KernelTestCase
{
    protected static function getKernelClass(): string
    {
        return \App\Kernel::class;
    }

    protected function setUp(): void
    {
        parent::setUp();
        self::bootKernel();
    }

    public function testStreamedModeKeepsSessionOpenUntilCompletion(): void
    {
        /** @var RequestStack $requestStack */
        $requestStack = self::getContainer()->get(RequestStack::class);
        /** @var SetupAccessToken $accessToken */
        $accessToken = self::getContainer()->get(SetupAccessToken::class);
        /** @var SetupState $setupState */
        $setupState = self::getContainer()->get(SetupState::class);
        $setupState->clearLock();
        /** @var ActionController $controller */
        $controller = self::getContainer()->get(ActionController::class);

        $session = new class extends Session {
            public int $saveCalls = 0;

            public function __construct()
            {
                parent::__construct(new MockArraySessionStorage());
            }

            public function save(): void
            {
                ++$this->saveCalls;
                parent::save();
            }
        };

        $request = new Request();
        $request->setMethod('POST');
        $request->headers->set('Content-Type', 'application/json');
        $request->setSession($session);

        $requestStack->push($request);

        try {
            $token = $accessToken->issue();
            $payload = json_encode([
                'token' => $token,
                'steps' => [
                    ['type' => 'log', 'message' => 'Streaming test'],
                ],
            ], JSON_THROW_ON_ERROR);
            $request->initialize(
                [],
                [],
                [],
                [],
                [],
                [
                    'CONTENT_TYPE' => 'application/json',
                    'REQUEST_METHOD' => 'POST',
                ],
                $payload,
            );
            $request->setSession($session);

            $response = $controller($request);

            self::assertInstanceOf(StreamedResponse::class, $response);

            ob_start();
            $response->sendContent();
            $output = (string) ob_get_clean();

            self::assertStringContainsString('"type":"heartbeat"', $output);
            self::assertStringContainsString('"type":"done"', $output);
            self::assertSame(1, $session->saveCalls);
        } finally {
            $requestStack->pop();
        }
    }

    public function testBufferedModeReturnsEventLog(): void
    {
        /** @var RequestStack $requestStack */
        $requestStack = self::getContainer()->get(RequestStack::class);
        /** @var SetupAccessToken $accessToken */
        $accessToken = self::getContainer()->get(SetupAccessToken::class);
        /** @var ActionExecutorInterface $executor */
        $executor = self::getContainer()->get(ActionExecutorInterface::class);
        /** @var SetupState $setupState */
        $setupState = self::getContainer()->get(SetupState::class);
        $setupState->clearLock();
        /** @var Security $security */
        $security = self::getContainer()->get(Security::class);
        /** @var UrlGeneratorInterface $urlGenerator */
        $urlGenerator = self::getContainer()->get(UrlGeneratorInterface::class);
        /** @var TranslatorInterface $translator */
        $translator = self::getContainer()->get(TranslatorInterface::class);
        $profiler = self::getContainer()->has(Profiler::class) ? self::getContainer()->get(Profiler::class) : null;

        $controller = new ActionController(
            $executor,
            $setupState,
            $accessToken,
            $security,
            $urlGenerator,
            $translator,
            $profiler,
            'buffered',
        );

        $session = new class extends Session {
            public int $saveCalls = 0;

            public function __construct()
            {
                parent::__construct(new MockArraySessionStorage());
            }

            public function save(): void
            {
                ++$this->saveCalls;
                parent::save();
            }
        };

        $request = new Request();
        $request->setMethod('POST');
        $request->headers->set('Content-Type', 'application/json');
        $request->setSession($session);

        $requestStack->push($request);

        try {
            $token = $accessToken->issue();
            $payload = json_encode([
                'token' => $token,
                'steps' => [
                    ['type' => 'log', 'message' => 'Buffered test'],
                ],
            ], JSON_THROW_ON_ERROR);
            $request->initialize(
                [],
                [],
                [],
                [],
                [],
                [
                    'CONTENT_TYPE' => 'application/json',
                    'REQUEST_METHOD' => 'POST',
                ],
                $payload,
            );
            $request->setSession($session);

            $response = $controller($request);

            self::assertInstanceOf(JsonResponse::class, $response);
            self::assertSame(JsonResponse::HTTP_OK, $response->getStatusCode());

            $content = $response->getContent();
            self::assertIsString($content);

            $decoded = json_decode($content, true, 512, JSON_THROW_ON_ERROR);

            self::assertSame('buffered', $decoded['mode']);
            self::assertCount(3, $decoded['events']);
            self::assertSame('heartbeat', $decoded['events'][0]['type']);
            self::assertSame('done', $decoded['events'][2]['type']);
            self::assertSame(1, $session->saveCalls);
        } finally {
            $requestStack->pop();
        }
    }
}
