<?php

declare(strict_types=1);

namespace App\Tests\Controller\Admin;

use Doctrine\DBAL\Connection;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class UserControllerTest extends WebTestCase
{
    private Connection $connection;
    private \Symfony\Bundle\FrameworkBundle\KernelBrowser $client;

    protected static function getKernelClass(): string
    {
        return \App\Kernel::class;
    }

    protected function setUp(): void
    {
        static::bootKernel();
        $container = static::getContainer();
        $this->connection = $container->get(Connection::class);

        $this->connection->executeStatement('PRAGMA foreign_keys = OFF');

        foreach (['app_api_key', 'app_project_user', 'app_project', 'app_user_role', 'app_role', 'app_user', 'app_audit_log'] as $table) {
            $this->connection->executeStatement('DROP TABLE IF EXISTS '.$table);
        }

        $this->connection->executeStatement('CREATE TABLE app_user (id CHAR(26) PRIMARY KEY, email VARCHAR(190) NOT NULL UNIQUE, password_hash VARCHAR(255) NOT NULL, display_name VARCHAR(190) NOT NULL, locale VARCHAR(12) NOT NULL, timezone VARCHAR(64) NOT NULL, status VARCHAR(16) NOT NULL DEFAULT "active", flags TEXT NOT NULL DEFAULT "{}", created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, last_login_at DATETIME DEFAULT NULL)');
        $this->connection->executeStatement('CREATE TABLE app_role (name VARCHAR(64) PRIMARY KEY, label VARCHAR(190) NOT NULL, is_system INTEGER NOT NULL DEFAULT 1, metadata TEXT NOT NULL DEFAULT "{}")');
        $this->connection->executeStatement('CREATE TABLE app_user_role (user_id CHAR(26) NOT NULL, role_name VARCHAR(64) NOT NULL, assigned_at DATETIME NOT NULL, assigned_by CHAR(26), PRIMARY KEY (user_id, role_name))');
        $this->connection->executeStatement('CREATE TABLE app_audit_log (id CHAR(26) PRIMARY KEY, actor_id CHAR(26), action VARCHAR(128) NOT NULL, subject_id CHAR(26), context TEXT NOT NULL, ip_hash VARCHAR(128), occurred_at DATETIME NOT NULL)');
        $this->connection->executeStatement('CREATE TABLE app_api_key (id CHAR(26) PRIMARY KEY, user_id CHAR(26) NOT NULL, label VARCHAR(190) NOT NULL, hashed_key VARCHAR(128) NOT NULL, scopes TEXT NOT NULL, last_used_at DATETIME DEFAULT NULL, created_at DATETIME NOT NULL, revoked_at DATETIME DEFAULT NULL, expires_at DATETIME DEFAULT NULL)');
        $this->connection->executeStatement('CREATE TABLE app_project (id CHAR(26) PRIMARY KEY, slug VARCHAR(190) NOT NULL, name VARCHAR(190) NOT NULL)');
        $this->connection->executeStatement('CREATE TABLE app_project_user (project_id CHAR(26) NOT NULL, user_id CHAR(26) NOT NULL, role_name VARCHAR(64) NOT NULL, permissions TEXT NOT NULL, created_at DATETIME NOT NULL, created_by CHAR(26), PRIMARY KEY (project_id, user_id))');

        $now = (new \DateTimeImmutable())->format('Y-m-d H:i:s');

        foreach ([
            ['ROLE_VIEWER', 'Viewer'],
            ['ROLE_EDITOR', 'Editor'],
            ['ROLE_ADMIN', 'Administrator'],
        ] as [$roleName, $label]) {
            $this->connection->insert('app_role', [
                'name' => $roleName,
                'label' => $label,
                'is_system' => 1,
                'metadata' => '{}',
            ]);
        }

        $this->connection->insert('app_user', [
            'id' => '01HXADMINUSER0000000000000',
            'email' => 'admin@example.com',
            'password_hash' => password_hash('secret123', PASSWORD_BCRYPT),
            'display_name' => 'Admin',
            'locale' => 'en',
            'timezone' => 'UTC',
            'status' => 'active',
            'flags' => '{}',
            'created_at' => $now,
            'updated_at' => $now,
            'last_login_at' => $now,
        ]);

        $this->connection->insert('app_user_role', [
            'user_id' => '01HXADMINUSER0000000000000',
            'role_name' => 'ROLE_ADMIN',
            'assigned_at' => $now,
            'assigned_by' => null,
        ]);

        $this->connection->insert('app_user', [
            'id' => '01HXUSER000000000000000000',
            'email' => 'user@example.com',
            'password_hash' => password_hash('secret123', PASSWORD_BCRYPT),
            'display_name' => 'Existing User',
            'locale' => 'en',
            'timezone' => 'UTC',
            'status' => 'active',
            'flags' => '{}',
            'created_at' => $now,
            'updated_at' => $now,
            'last_login_at' => null,
        ]);

        $this->connection->insert('app_user_role', [
            'user_id' => '01HXUSER000000000000000000',
            'role_name' => 'ROLE_VIEWER',
            'assigned_at' => $now,
            'assigned_by' => '01HXADMINUSER0000000000000',
        ]);

        $this->connection->insert('app_project', [
            'id' => '01HXPROJECT0000000000000000',
            'slug' => 'default',
            'name' => 'Default Project',
        ]);

        $this->connection->executeStatement('PRAGMA foreign_keys = ON');
        static::ensureKernelShutdown();
    }

    protected function tearDown(): void
    {
        static::ensureKernelShutdown();
        parent::tearDown();
    }

    private function loginAsAdmin(): void
    {
        static::bootKernel();
        $provider = static::getContainer()->get(\App\Security\User\AppUserProvider::class);
        $user = $provider->loadUserByIdentifier('admin@example.com');
        static::ensureKernelShutdown();

        $this->client = static::createClient();
        $this->client->loginUser($user);
    }

    public function testIndexDisplaysUsers(): void
    {
        $this->loginAsAdmin();
        $this->client->request('GET', '/admin/users');

        self::assertResponseIsSuccessful();
        $html = $this->client->getResponse()->getContent();
        self::assertStringContainsString('User management', $html);
        self::assertStringContainsString('user@example.com', $html);
    }

    public function testEditUpdatesProfileAndRoles(): void
    {
        $this->loginAsAdmin();
        $crawler = $this->client->request('GET', '/admin/users/01HXUSER000000000000000000');

        self::assertResponseIsSuccessful();

        $form = $crawler->selectButton('Save changes')->form();
        $form['user_profile[display_name]']->setValue('Updated User');
        $form['user_profile[locale]']->setValue('en_GB');
        $form['user_profile[timezone]']->setValue('Europe/Berlin');
        $form['user_profile[status]']->setValue('disabled');

        $roleFieldNames = [];
        $index = 0;

        foreach ($crawler->filter('input[type="checkbox"][name^="user_profile[roles]"]') as $input) {
            /** @var \DOMElement $input */
            $roleFieldNames[$input->getAttribute('value')] = sprintf('user_profile[roles][%d]', $index);
            ++$index;
        }

        self::assertArrayHasKey('ROLE_ADMIN', $roleFieldNames);
        self::assertArrayHasKey('ROLE_VIEWER', $roleFieldNames);

        $form[$roleFieldNames['ROLE_ADMIN']]->tick();
        $form[$roleFieldNames['ROLE_VIEWER']]->tick();

        $this->client->submit($form);

        self::assertResponseRedirects('/admin/users/01HXUSER000000000000000000');
        $crawler = $this->client->followRedirect();

        $row = $this->connection->fetchAssociative('SELECT display_name, locale, timezone, status FROM app_user WHERE id = ?', ['01HXUSER000000000000000000']);
        self::assertNotFalse($row);
        self::assertSame('Updated User', $row['display_name']);
        self::assertSame('en_GB', $row['locale']);
        self::assertSame('Europe/Berlin', $row['timezone']);
        self::assertSame('disabled', $row['status']);

        $roles = $this->connection->fetchFirstColumn('SELECT role_name FROM app_user_role WHERE user_id = ?', ['01HXUSER000000000000000000']);
        sort($roles);
        self::assertSame(['ROLE_ADMIN', 'ROLE_VIEWER'], $roles);

        $auditCount = $this->connection->fetchOne('SELECT COUNT(*) FROM app_audit_log WHERE subject_id = ?', ['01HXUSER000000000000000000']);
        self::assertGreaterThanOrEqual(1, (int) $auditCount);
    }

    public function testCreateAndRevokeApiKey(): void
    {
        $this->loginAsAdmin();
        $crawler = $this->client->request('GET', '/admin/users/01HXUSER000000000000000000');
        self::assertResponseIsSuccessful();

        $form = $crawler->selectButton('Create API key')->form();
        $form['api_key_create[label]']->setValue('Automation');
        $form['api_key_create[scopes]']->setValue('content.read content.write');
        $form['api_key_create[expires_at]']->setValue('2030-01-01');

        $this->client->submit($form);

        self::assertResponseRedirects('/admin/users/01HXUSER000000000000000000');
        $crawler = $this->client->followRedirect();

        $row = $this->connection->fetchAssociative('SELECT id, label, scopes, revoked_at FROM app_api_key WHERE user_id = ?', ['01HXUSER000000000000000000']);
        self::assertNotFalse($row);
        $manager = $this->client->getContainer()->get(\App\Security\Api\ApiKeyManager::class);
        $listedKeys = $manager->listForUser('01HXUSER000000000000000000');
        self::assertSame('Automation', $row['label']);

        $decodedScopes = json_decode((string) $row['scopes'], true, flags: JSON_THROW_ON_ERROR);
        self::assertSame(['content.read', 'content.write'], $decodedScopes);
        self::assertNull($row['revoked_at']);

        $revokeFormNode = $crawler->filterXPath(sprintf('//form[contains(@action, "%s")]','/api-keys/'.$row['id'].'/revoke'));
        self::assertGreaterThan(0, $revokeFormNode->count());
        $revokeForm = $revokeFormNode->form();
        $this->client->submit($revokeForm);

        self::assertResponseRedirects('/admin/users/01HXUSER000000000000000000');
        $revokedAt = $this->connection->fetchOne('SELECT revoked_at FROM app_api_key WHERE id = ?', [$row['id']]);
        self::assertNotNull($revokedAt);
    }

    public function testUpdateProjectMembership(): void
    {
        $this->loginAsAdmin();
        $crawler = $this->client->request('GET', '/admin/users/01HXUSER000000000000000000');
        self::assertResponseIsSuccessful();

        // Assign project role + capabilities.
        $form = $crawler->selectButton('Save project access')->form();
        $form['project_membership_collection[assignments][0][role]']->setValue('ROLE_EDITOR');
        $form['project_membership_collection[assignments][0][capabilities]']->setValue('content.publish content.review');

        $this->client->submit($form);

        self::assertResponseRedirects('/admin/users/01HXUSER000000000000000000');
        $this->client->followRedirect();

        $membershipRow = $this->connection->fetchAssociative('SELECT role_name, permissions FROM app_project_user WHERE project_id = ? AND user_id = ?', ['01HXPROJECT0000000000000000', '01HXUSER000000000000000000']);
        self::assertNotFalse($membershipRow);
        self::assertSame('ROLE_EDITOR', $membershipRow['role_name']);
        $permissions = json_decode((string) $membershipRow['permissions'], true, flags: JSON_THROW_ON_ERROR);
        self::assertSame(['capabilities' => ['content.publish', 'content.review']], $permissions);

        // Remove membership again (inherit global).
        $crawler = $this->client->request('GET', '/admin/users/01HXUSER000000000000000000');
        $form = $crawler->selectButton('Save project access')->form();
        $form['project_membership_collection[assignments][0][role]']->setValue('');
        $form['project_membership_collection[assignments][0][capabilities]']->setValue('');
        $this->client->submit($form);

        self::assertResponseRedirects('/admin/users/01HXUSER000000000000000000');
        $this->client->followRedirect();

        $exists = $this->connection->fetchOne('SELECT COUNT(*) FROM app_project_user WHERE project_id = ? AND user_id = ?', ['01HXPROJECT0000000000000000', '01HXUSER000000000000000000']);
        self::assertSame(0, (int) $exists);
    }
}
