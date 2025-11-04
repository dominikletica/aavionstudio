<?php

declare(strict_types=1);

namespace App\Tests\Controller\Debug;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class LocaleControllerTest extends WebTestCase
{
    protected static function getKernelClass(): string
    {
        return \App\Kernel::class;
    }

    protected function tearDown(): void
    {
        static::ensureKernelShutdown();
        parent::tearDown();
    }

    public function testEnablingDebugKeyModeStoresFlagInSession(): void
    {
        $client = static::createClient();

        $client->request('POST', '/_debug/locale', ['mode' => 'keys'], [], ['HTTP_REFERER' => '/admin']);

        self::assertResponseRedirects('/admin');

        $session = $client->getRequest()?->getSession();
        self::assertNotNull($session);
        self::assertTrue($session->get('_app.translation_debug_keys', false));
        self::assertNull($session->get('_app.translation_override'));
    }

    public function testSelectingLocaleOverridePersistsChoice(): void
    {
        $client = static::createClient();

        $client->request('POST', '/_debug/locale', ['mode' => 'de'], [], ['HTTP_REFERER' => '/setup']);

        self::assertResponseRedirects('/setup');

        $session = $client->getRequest()?->getSession();
        self::assertNotNull($session);
        self::assertSame('de', $session->get('_app.translation_override'));
        self::assertFalse($session->get('_app.translation_debug_keys', false));
    }

    public function testAutoModeClearsOverrides(): void
    {
        $client = static::createClient();

        $client->request('POST', '/_debug/locale', ['mode' => 'keys'], [], ['HTTP_REFERER' => '/admin']);
        $client->request('POST', '/_debug/locale', ['mode' => 'auto'], [], ['HTTP_REFERER' => '/admin']);

        self::assertResponseRedirects('/admin');

        $session = $client->getRequest()?->getSession();
        self::assertNotNull($session);
        self::assertNull($session->get('_app.translation_override'));
        self::assertFalse($session->get('_app.translation_debug_keys', false));
    }
}
