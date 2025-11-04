<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use App\Installer\DefaultProjects;
use App\Installer\DefaultSystemSettings;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;
use Symfony\Component\Uid\Ulid;

final class Version20251105000100 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Bootstrap core platform schema (system + user databases) with baseline data and presets.';
    }

    public function up(Schema $schema): void
    {
        $this->createSystemTables();
        $this->createUserTables();

        $this->seedSystemSettings();
        $this->seedDefaultProjects();
        $this->seedModuleStates();
        $this->seedThemeStates();
        $this->seedRoles();
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE IF EXISTS app_preset');
        $this->addSql('DROP TABLE IF EXISTS app_role_capability');
        $this->addSql('DROP TABLE IF EXISTS app_audit_log');
        $this->addSql('DROP TABLE IF EXISTS app_remember_me_token');
        $this->addSql('DROP TABLE IF EXISTS app_user_invitation');
        $this->addSql('DROP TABLE IF EXISTS app_password_reset_token');
        $this->addSql('DROP TABLE IF EXISTS app_user_role');
        $this->addSql('DROP TABLE IF EXISTS app_role');
        $this->addSql('DROP TABLE IF EXISTS app_theme_state');
        $this->addSql('DROP TABLE IF EXISTS app_module_state');
        $this->addSql('DROP TABLE IF EXISTS app_system_setting');
        $this->addSql('DROP TABLE IF EXISTS app_api_key');
        $this->addSql('DROP TABLE IF EXISTS app_user');
        $this->addSql('DROP TABLE IF EXISTS app_log');

        $this->addSql('DROP TABLE IF EXISTS user_brain.app_project_user');
        $this->addSql('DROP TABLE IF EXISTS user_brain.app_relation');
        $this->addSql('DROP TABLE IF EXISTS user_brain.app_draft');
        $this->addSql('DROP TABLE IF EXISTS user_brain.app_entity_version');
        $this->addSql('DROP TABLE IF EXISTS user_brain.app_entity');
        $this->addSql('DROP TABLE IF EXISTS user_brain.app_schema');
        $this->addSql('DROP TABLE IF EXISTS user_brain.app_template');
        $this->addSql('DROP TABLE IF EXISTS user_brain.app_project');
    }

    private function createSystemTables(): void
    {
        $this->addSql(<<<'SQL'
CREATE TABLE IF NOT EXISTS app_user (
    id CHAR(26) NOT NULL PRIMARY KEY,
    email VARCHAR(190) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    display_name VARCHAR(190) NOT NULL,
    locale VARCHAR(12) NOT NULL,
    timezone VARCHAR(64) NOT NULL,
    flags TEXT NOT NULL,
    status VARCHAR(16) NOT NULL DEFAULT 'active',
    last_login_at DATETIME DEFAULT NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL
)
SQL);

        $this->addSql(<<<'SQL'
CREATE TABLE IF NOT EXISTS app_api_key (
    id CHAR(26) NOT NULL PRIMARY KEY,
    user_id CHAR(26) NOT NULL,
    label VARCHAR(190) NOT NULL,
    hashed_key VARCHAR(128) NOT NULL,
    scopes TEXT NOT NULL,
    last_used_at DATETIME DEFAULT NULL,
    expires_at DATETIME DEFAULT NULL,
    created_at DATETIME NOT NULL,
    revoked_at DATETIME DEFAULT NULL,
    FOREIGN KEY (user_id) REFERENCES app_user(id) ON DELETE CASCADE
)
SQL);

        $this->addSql('CREATE TABLE IF NOT EXISTS app_log (
            id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL,
            channel VARCHAR(64) NOT NULL,
            level INTEGER NOT NULL,
            message TEXT NOT NULL,
            context TEXT NOT NULL,
            created_at DATETIME NOT NULL
        )');

        $this->addSql('CREATE TABLE IF NOT EXISTS app_system_setting (
            key VARCHAR(190) NOT NULL PRIMARY KEY,
            value TEXT NOT NULL,
            type VARCHAR(16) NOT NULL,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL
        )');

        $this->addSql('CREATE TABLE IF NOT EXISTS app_module_state (
            name VARCHAR(190) NOT NULL PRIMARY KEY,
            enabled INTEGER NOT NULL DEFAULT 1,
            metadata TEXT NOT NULL,
            updated_at DATETIME NOT NULL
        )');

        $this->addSql('CREATE TABLE IF NOT EXISTS app_theme_state (
            name VARCHAR(190) NOT NULL PRIMARY KEY,
            enabled INTEGER NOT NULL DEFAULT 1,
            active INTEGER NOT NULL DEFAULT 0,
            metadata TEXT NOT NULL,
            updated_at DATETIME NOT NULL
        )');

        $this->addSql(<<<'SQL'
CREATE TABLE IF NOT EXISTS app_role (
    name VARCHAR(64) NOT NULL PRIMARY KEY,
    label VARCHAR(190) NOT NULL,
    is_system INTEGER NOT NULL DEFAULT 1,
    metadata TEXT NOT NULL DEFAULT '{}'
)
SQL);

        $this->addSql(<<<'SQL'
CREATE TABLE IF NOT EXISTS app_user_role (
    user_id CHAR(26) NOT NULL,
    role_name VARCHAR(64) NOT NULL,
    assigned_at DATETIME NOT NULL,
    assigned_by CHAR(26) DEFAULT NULL,
    PRIMARY KEY (user_id, role_name),
    FOREIGN KEY (user_id) REFERENCES app_user(id) ON DELETE CASCADE,
    FOREIGN KEY (role_name) REFERENCES app_role(name) ON DELETE CASCADE
)
SQL);

        $this->addSql(<<<'SQL'
CREATE TABLE IF NOT EXISTS app_password_reset_token (
    id CHAR(26) NOT NULL PRIMARY KEY,
    user_id CHAR(26) NOT NULL,
    selector VARCHAR(24) NOT NULL UNIQUE,
    verifier_hash VARCHAR(128) NOT NULL,
    requested_at DATETIME NOT NULL,
    expires_at DATETIME NOT NULL,
    consumed_at DATETIME DEFAULT NULL,
    metadata TEXT NOT NULL DEFAULT '{}',
    FOREIGN KEY (user_id) REFERENCES app_user(id) ON DELETE CASCADE
)
SQL);

        $this->addSql(<<<'SQL'
CREATE TABLE IF NOT EXISTS app_user_invitation (
    id CHAR(26) NOT NULL PRIMARY KEY,
    email VARCHAR(190) NOT NULL,
    token_hash VARCHAR(128) NOT NULL,
    status VARCHAR(16) NOT NULL DEFAULT 'pending',
    invited_by CHAR(26) DEFAULT NULL,
    metadata TEXT NOT NULL DEFAULT '{}',
    created_at DATETIME NOT NULL,
    expires_at DATETIME NOT NULL,
    accepted_at DATETIME DEFAULT NULL,
    cancelled_at DATETIME DEFAULT NULL,
    UNIQUE(email),
    FOREIGN KEY (invited_by) REFERENCES app_user(id) ON DELETE SET NULL
)
SQL);

        $this->addSql('CREATE TABLE IF NOT EXISTS app_remember_me_token (
            series VARCHAR(64) NOT NULL PRIMARY KEY,
            token_hash VARCHAR(128) NOT NULL,
            class VARCHAR(190) NOT NULL,
            user_id CHAR(26) NOT NULL,
            last_used_at DATETIME NOT NULL,
            FOREIGN KEY (user_id) REFERENCES app_user(id) ON DELETE CASCADE
        )');

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

        $this->addSql('CREATE TABLE IF NOT EXISTS app_role_capability (
            role_name VARCHAR(64) NOT NULL,
            capability VARCHAR(190) NOT NULL,
            PRIMARY KEY (role_name, capability),
            FOREIGN KEY (role_name) REFERENCES app_role(name) ON DELETE CASCADE
        )');

        $this->addSql(<<<'SQL'
CREATE TABLE IF NOT EXISTS app_preset (
    id VARCHAR(190) NOT NULL PRIMARY KEY,
    content_type VARCHAR(32) NOT NULL,
    filter TEXT NOT NULL,
    payload_filter TEXT NOT NULL,
    root_schema TEXT NOT NULL,
    entity_schema TEXT NOT NULL,
    settings TEXT NOT NULL DEFAULT '{}',
    metadata TEXT NOT NULL DEFAULT '{}',
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL
)
SQL);
    }

    private function createUserTables(): void
    {
        $this->addSql(<<<'SQL'
CREATE TABLE IF NOT EXISTS user_brain.app_template (
    id CHAR(26) NOT NULL PRIMARY KEY,
    slug VARCHAR(190) NOT NULL,
    channel VARCHAR(32) NOT NULL,
    twig_source TEXT NOT NULL,
    checksum VARCHAR(128) NOT NULL,
    metadata TEXT NOT NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    UNIQUE(slug, channel)
)
SQL);

        $this->addSql(<<<'SQL'
CREATE TABLE IF NOT EXISTS user_brain.app_schema (
    id CHAR(26) NOT NULL PRIMARY KEY,
    slug VARCHAR(190) NOT NULL,
    scope VARCHAR(32) NOT NULL,
    version VARCHAR(20) NOT NULL,
    status VARCHAR(16) NOT NULL,
    definition TEXT NOT NULL,
    metadata TEXT NOT NULL,
    template_id CHAR(26) DEFAULT NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    UNIQUE(slug, scope),
    FOREIGN KEY (template_id) REFERENCES user_brain.app_template(id) ON DELETE SET NULL
)
SQL);

        $this->addSql(<<<'SQL'
CREATE TABLE IF NOT EXISTS user_brain.app_project (
    id CHAR(26) NOT NULL PRIMARY KEY,
    slug VARCHAR(190) NOT NULL UNIQUE,
    name VARCHAR(190) NOT NULL,
    locale VARCHAR(12) NOT NULL,
    timezone VARCHAR(64) NOT NULL,
    settings TEXT NOT NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL
)
SQL);

        $this->addSql(<<<'SQL'
CREATE TABLE IF NOT EXISTS user_brain.app_project_user (
    project_id CHAR(26) NOT NULL,
    user_id CHAR(26) NOT NULL,
    role_name VARCHAR(64) NOT NULL,
    permissions TEXT NOT NULL DEFAULT '{}',
    created_at DATETIME NOT NULL,
    created_by CHAR(26) DEFAULT NULL,
    PRIMARY KEY (project_id, user_id),
    FOREIGN KEY (project_id) REFERENCES user_brain.app_project(id) ON DELETE CASCADE
)
SQL);

        $this->addSql(<<<'SQL'
CREATE TABLE IF NOT EXISTS user_brain.app_entity (
    id CHAR(26) NOT NULL PRIMARY KEY,
    project_id CHAR(26) NOT NULL,
    slug VARCHAR(190) NOT NULL,
    type VARCHAR(190) NOT NULL,
    status VARCHAR(32) NOT NULL,
    flags TEXT NOT NULL,
    meta TEXT NOT NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    UNIQUE(project_id, slug),
    FOREIGN KEY (project_id) REFERENCES user_brain.app_project(id) ON DELETE CASCADE
)
SQL);

        $this->addSql(<<<'SQL'
CREATE TABLE IF NOT EXISTS user_brain.app_entity_version (
    id CHAR(26) NOT NULL PRIMARY KEY,
    entity_id CHAR(26) NOT NULL,
    version VARCHAR(26) NOT NULL,
    payload TEXT NOT NULL,
    committed_at DATETIME NOT NULL,
    committed_by CHAR(26) NOT NULL,
    commit_message TEXT,
    is_active INTEGER NOT NULL DEFAULT 0,
    checksum VARCHAR(64) NOT NULL,
    FOREIGN KEY (entity_id) REFERENCES user_brain.app_entity(id) ON DELETE CASCADE
)
SQL);

        $this->addSql(<<<'SQL'
CREATE TABLE IF NOT EXISTS user_brain.app_draft (
    id CHAR(26) NOT NULL PRIMARY KEY,
    entity_id CHAR(26) NOT NULL,
    payload TEXT NOT NULL,
    updated_at DATETIME NOT NULL,
    updated_by CHAR(26) NOT NULL,
    autosave INTEGER NOT NULL DEFAULT 0,
    FOREIGN KEY (entity_id) REFERENCES user_brain.app_entity(id) ON DELETE CASCADE
)
SQL);

        $this->addSql(<<<'SQL'
CREATE TABLE IF NOT EXISTS user_brain.app_relation (
    id CHAR(26) NOT NULL PRIMARY KEY,
    project_id CHAR(26) NOT NULL,
    source_entity_id CHAR(26) NOT NULL,
    target_entity_id CHAR(26) NOT NULL,
    relation_type VARCHAR(64) NOT NULL,
    payload TEXT NOT NULL,
    created_at DATETIME NOT NULL,
    FOREIGN KEY (project_id) REFERENCES user_brain.app_project(id) ON DELETE CASCADE,
    FOREIGN KEY (source_entity_id) REFERENCES user_brain.app_entity(id) ON DELETE CASCADE,
    FOREIGN KEY (target_entity_id) REFERENCES user_brain.app_entity(id) ON DELETE CASCADE
)
SQL);
    }

    private function seedSystemSettings(): void
    {
        $now = (new \DateTimeImmutable())->format('Y-m-d H:i:s');

        foreach (DefaultSystemSettings::all() as $key => $value) {
            $type = \gettype($value);

            $this->addSql(
                'INSERT OR IGNORE INTO app_system_setting (key, value, type, created_at, updated_at) VALUES (:key, :value, :type, :created_at, :updated_at)',
                [
                    'key' => $key,
                    'value' => json_encode($value, JSON_THROW_ON_ERROR),
                    'type' => $type === 'double' ? 'float' : $type,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]
            );
        }
    }

    private function seedDefaultProjects(): void
    {
        $now = (new \DateTimeImmutable())->format('Y-m-d H:i:s');

        foreach (DefaultProjects::all() as $project) {
            $id = $project['id'] ?? (new Ulid())->toBase32();
            $slug = $project['slug'] ?? null;

            if (!\is_string($slug) || $slug === '') {
                continue;
            }

            $this->addSql(
                'INSERT OR IGNORE INTO user_brain.app_project (id, slug, name, locale, timezone, settings, created_at, updated_at) VALUES (:id, :slug, :name, :locale, :timezone, :settings, :created_at, :updated_at)',
                [
                    'id' => $id,
                    'slug' => $slug,
                    'name' => (string) ($project['name'] ?? ucfirst($slug)),
                    'locale' => (string) ($project['locale'] ?? 'en'),
                    'timezone' => (string) ($project['timezone'] ?? 'UTC'),
                    'settings' => json_encode($project['settings'] ?? [], JSON_THROW_ON_ERROR),
                    'created_at' => $now,
                    'updated_at' => $now,
                ]
            );
        }
    }

    private function seedModuleStates(): void
    {
        $now = (new \DateTimeImmutable())->format('Y-m-d H:i:s');

        $this->addSql(
            'INSERT OR IGNORE INTO app_module_state (name, enabled, metadata, updated_at) VALUES (:name, :enabled, :metadata, :updated_at)',
            [
                'name' => 'core',
                'enabled' => 1,
                'metadata' => json_encode(['seeded_at' => $now], JSON_THROW_ON_ERROR),
                'updated_at' => $now,
            ]
        );
    }

    private function seedThemeStates(): void
    {
        $now = (new \DateTimeImmutable())->format('Y-m-d H:i:s');

        $this->addSql(
            'INSERT OR IGNORE INTO app_theme_state (name, enabled, active, metadata, updated_at) VALUES (:name, :enabled, :active, :metadata, :updated_at)',
            [
                'name' => 'base',
                'enabled' => 1,
                'active' => 1,
                'metadata' => json_encode(['seeded_at' => $now], JSON_THROW_ON_ERROR),
                'updated_at' => $now,
            ]
        );
    }

    private function seedRoles(): void
    {
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
                    'metadata' => json_encode(['seeded_at' => $now], JSON_THROW_ON_ERROR),
                ]
            );
        }
    }
}

