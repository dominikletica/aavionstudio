<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20251031000100 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Extend user/access-control schema with roles, memberships, audit logs, and credential tokens.';
    }

    public function up(Schema $schema): void
    {
        // Rename app_user.password to app_user.password_hash and add new columns.
        $this->addSql('ALTER TABLE app_user RENAME COLUMN password TO password_hash');
        $this->addSql('ALTER TABLE app_user ADD status VARCHAR(16) NOT NULL DEFAULT \'active\'');
        $this->addSql('ALTER TABLE app_user ADD last_login_at DATETIME DEFAULT NULL');
        $this->addSql("UPDATE app_user SET status = 'active' WHERE status IS NULL");

        // Roles and role assignments.
        $this->addSql('CREATE TABLE IF NOT EXISTS app_role (
            name VARCHAR(64) NOT NULL PRIMARY KEY,
            label VARCHAR(190) NOT NULL,
            is_system INTEGER NOT NULL DEFAULT 1,
            metadata TEXT NOT NULL DEFAULT \'{}\'
        )');

        $this->addSql('CREATE TABLE IF NOT EXISTS app_user_role (
            user_id CHAR(26) NOT NULL,
            role_name VARCHAR(64) NOT NULL,
            assigned_at DATETIME NOT NULL,
            assigned_by CHAR(26) DEFAULT NULL,
            PRIMARY KEY (user_id, role_name),
            FOREIGN KEY (user_id) REFERENCES app_user(id) ON DELETE CASCADE,
            FOREIGN KEY (role_name) REFERENCES app_role(name) ON DELETE CASCADE
        )');

        // Project memberships with per-project overrides.
        $this->addSql('CREATE TABLE IF NOT EXISTS app_project_user (
            project_id CHAR(26) NOT NULL,
            user_id CHAR(26) NOT NULL,
            role_name VARCHAR(64) NOT NULL,
            permissions TEXT NOT NULL DEFAULT \'{}\',
            created_at DATETIME NOT NULL,
            created_by CHAR(26) DEFAULT NULL,
            PRIMARY KEY (project_id, user_id),
            FOREIGN KEY (project_id) REFERENCES app_project(id) ON DELETE CASCADE,
            FOREIGN KEY (user_id) REFERENCES app_user(id) ON DELETE CASCADE,
            FOREIGN KEY (role_name) REFERENCES app_role(name) ON DELETE CASCADE
        )');

        // Password reset tokens (selector/verifier pair).
        $this->addSql('CREATE TABLE IF NOT EXISTS app_password_reset_token (
            id CHAR(26) NOT NULL PRIMARY KEY,
            user_id CHAR(26) NOT NULL,
            selector VARCHAR(24) NOT NULL UNIQUE,
            verifier_hash VARCHAR(128) NOT NULL,
            requested_at DATETIME NOT NULL,
            expires_at DATETIME NOT NULL,
            consumed_at DATETIME DEFAULT NULL,
            FOREIGN KEY (user_id) REFERENCES app_user(id) ON DELETE CASCADE
        )');

        // Remember-me tokens (persistent login).
        $this->addSql('CREATE TABLE IF NOT EXISTS app_remember_me_token (
            series VARCHAR(64) NOT NULL PRIMARY KEY,
            token_hash VARCHAR(128) NOT NULL,
            class VARCHAR(190) NOT NULL,
            user_id CHAR(26) NOT NULL,
            last_used_at DATETIME NOT NULL,
            FOREIGN KEY (user_id) REFERENCES app_user(id) ON DELETE CASCADE
        )');

        // Audit log for security-sensitive actions.
        $this->addSql('CREATE TABLE IF NOT EXISTS app_audit_log (
            id CHAR(26) NOT NULL PRIMARY KEY,
            actor_id CHAR(26) DEFAULT NULL,
            action VARCHAR(128) NOT NULL,
            subject_id CHAR(26) DEFAULT NULL,
            context TEXT NOT NULL,
            ip_hash VARCHAR(128) DEFAULT NULL,
            occurred_at DATETIME NOT NULL,
            FOREIGN KEY (actor_id) REFERENCES app_user(id) ON DELETE SET NULL
        )');

        // Capability registry persistence for role seeding.
        $this->addSql('CREATE TABLE IF NOT EXISTS app_role_capability (
            role_name VARCHAR(64) NOT NULL,
            capability VARCHAR(190) NOT NULL,
            PRIMARY KEY (role_name, capability),
            FOREIGN KEY (role_name) REFERENCES app_role(name) ON DELETE CASCADE
        )');

        // Add missing columns to existing tables.
        $this->addSql('ALTER TABLE app_api_key ADD expires_at DATETIME DEFAULT NULL');

        // Seed default roles.
        $now = (new \DateTimeImmutable())->format('Y-m-d H:i:s');
        $roles = [
            ['name' => 'ROLE_VIEWER', 'label' => 'Viewer'],
            ['name' => 'ROLE_EDITOR', 'label' => 'Editor'],
            ['name' => 'ROLE_ADMIN', 'label' => 'Administrator'],
            ['name' => 'ROLE_SUPER_ADMIN', 'label' => 'Super Administrator'],
        ];

        foreach ($roles as $role) {
            $this->addSql(
                'INSERT OR IGNORE INTO app_role (name, label, is_system, metadata) VALUES (:name, :label, 1, :metadata)',
                [
                    'name' => $role['name'],
                    'label' => $role['label'],
                    'metadata' => json_encode(['created_at' => $now], JSON_THROW_ON_ERROR),
                ]
            );
        }
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE app_user DROP COLUMN status');
        $this->addSql('ALTER TABLE app_user DROP COLUMN last_login_at');
        $this->addSql('ALTER TABLE app_user RENAME COLUMN password_hash TO password');

        $this->addSql('ALTER TABLE app_api_key DROP COLUMN expires_at');

        $this->addSql('DROP TABLE IF EXISTS app_role_capability');
        $this->addSql('DROP TABLE IF EXISTS app_audit_log');
        $this->addSql('DROP TABLE IF EXISTS app_remember_me_token');
        $this->addSql('DROP TABLE IF EXISTS app_password_reset_token');
        $this->addSql('DROP TABLE IF EXISTS app_project_user');
        $this->addSql('DROP TABLE IF EXISTS app_user_role');
        $this->addSql('DROP TABLE IF EXISTS app_role');
    }
}
