<?php

declare(strict_types=1);

namespace App\Tests\EventSubscriber;

use App\EventSubscriber\LocaleSubscriber;
use App\Internationalization\LocaleProvider;
use App\Settings\SystemSettings;
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;

final class LocaleSubscriberTest extends TestCase
{
    /** @var list<string> */
    private array $tempDirs = [];

    protected function tearDown(): void
    {
        foreach ($this->tempDirs as $dir) {
            $this->removeDirectory($dir);
        }

        parent::tearDown();
    }

    public function testUsesUserLocaleWhenSupported(): void
    {
        $user = new class implements UserInterface, PasswordAuthenticatedUserInterface {
            public function getRoles(): array { return []; }
            public function eraseCredentials(): void {}
            public function getUserIdentifier(): string { return 'user@example.com'; }
            public function getPassword(): ?string { return null; }
            public function getLocale(): string { return 'de'; }
        };

        $security = $this->createMock(Security::class);
        $security->method('getUser')->willReturn($user);

        $provider = $this->createProvider(['de']);

        $subscriber = new LocaleSubscriber($security, $provider, $this->createSystemSettings());

        $request = Request::create('/');
        $event = $this->createRequestEvent($request);

        $subscriber->onKernelRequest($event);

        self::assertSame('de', $request->getLocale());
    }

    public function testFallsBackToAcceptLanguage(): void
    {
        $security = $this->createMock(Security::class);
        $security->method('getUser')->willReturn(null);

        $provider = $this->createProvider(['fr']);

        $subscriber = new LocaleSubscriber($security, $provider, $this->createSystemSettings());

        $request = Request::create('/', 'GET', [], [], [], ['HTTP_ACCEPT_LANGUAGE' => 'fr,en;q=0.8']);
        $event = $this->createRequestEvent($request);
        $subscriber->onKernelRequest($event);

        self::assertSame('fr', $request->getLocale());
    }

    public function testFallsBackToSystemSetting(): void
    {
        $security = $this->createMock(Security::class);
        $security->method('getUser')->willReturn(null);

        $provider = $this->createProvider(['de']);

        $subscriber = new LocaleSubscriber($security, $provider, $this->createSystemSettings('de'));

        $request = Request::create('/', 'GET', [], [], [], ['HTTP_ACCEPT_LANGUAGE' => 'es']);
        $event = $this->createRequestEvent($request);
        $subscriber->onKernelRequest($event);

        self::assertSame('de', $request->getLocale());
    }

    public function testFallsBackToProviderDefault(): void
    {
        $security = $this->createMock(Security::class);
        $security->method('getUser')->willReturn(null);

        $provider = $this->createProvider([]);

        $subscriber = new LocaleSubscriber($security, $provider, $this->createSystemSettings('it'));

        $request = Request::create('/');
        $event = $this->createRequestEvent($request);
        $subscriber->onKernelRequest($event);

        self::assertSame('en', $request->getLocale());
    }

    private function createProvider(array $locales): LocaleProvider
    {
        $projectDir = sys_get_temp_dir().'/locale_subscriber_'.bin2hex(random_bytes(4));
        mkdir($projectDir.'/translations', 0777, true);

        foreach ($locales as $locale) {
            file_put_contents(sprintf('%s/translations/messages.%s.yaml', $projectDir, $locale), '');
        }

        $this->tempDirs[] = $projectDir;

        return new LocaleProvider($projectDir);
    }

    private function createRequestEvent(Request $request): RequestEvent
    {
        $kernel = $this->createMock(HttpKernelInterface::class);

        return new RequestEvent($kernel, $request, HttpKernelInterface::MAIN_REQUEST);
    }

    private function removeDirectory(string $path): void
    {
        if (!is_dir($path)) {
            return;
        }

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($path, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );

        foreach ($iterator as $item) {
            $item->isDir() ? rmdir($item->getPathname()) : unlink($item->getPathname());
        }

        rmdir($path);
    }

    private function createSystemSettings(?string $locale = null): SystemSettings
    {
        $connection = $this->createMock(\Doctrine\DBAL\Connection::class);
        $rows = [];

        if ($locale !== null) {
            $rows[] = [
                'key' => 'core.locale',
                'value' => json_encode($locale, JSON_THROW_ON_ERROR),
            ];
        }

        $connection->method('fetchAllAssociative')->willReturn($rows);

        return new SystemSettings($connection);
    }
}
