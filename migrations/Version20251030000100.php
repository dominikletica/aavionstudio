<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use App\Installer\DefaultProjects;
use App\Installer\DefaultSystemSettings;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;
use Symfony\Component\Uid\Ulid;

final class Version20251030000100 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Bootstrap core platform tables across system and user SQLite databases with baseline data.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE IF NOT EXISTS app_project (
            id CHAR(26) NOT NULL PRIMARY KEY,
            slug VARCHAR(190) NOT NULL UNIQUE,
            name VARCHAR(190) NOT NULL,
            locale VARCHAR(12) NOT NULL,
            timezone VARCHAR(64) NOT NULL,
            settings TEXT NOT NULL,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL
        )');

        $this->addSql('CREATE TABLE IF NOT EXISTS app_schema (
            id CHAR(26) NOT NULL PRIMARY KEY,
            slug VARCHAR(190) NOT NULL,
            scope VARCHAR(32) NOT NULL,
            version VARCHAR(20) NOT NULL,
            status VARCHAR(16) NOT NULL,
            definition TEXT NOT NULL,
            metadata TEXT NOT NULL,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            UNIQUE(slug, scope)
        )');

        $this->addSql('CREATE TABLE IF NOT EXISTS app_template (
            id CHAR(26) NOT NULL PRIMARY KEY,
            schema_id CHAR(26) NOT NULL,
            channel VARCHAR(32) NOT NULL,
            twig_source TEXT NOT NULL,
            checksum VARCHAR(128) NOT NULL,
            metadata TEXT NOT NULL,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            FOREIGN KEY(schema_id) REFERENCES app_schema(id) ON DELETE CASCADE
        )');

        $this->addSql('CREATE TABLE IF NOT EXISTS app_user (
            id CHAR(26) NOT NULL PRIMARY KEY,
            email VARCHAR(190) NOT NULL UNIQUE,
            password VARCHAR(255) NOT NULL,
            display_name VARCHAR(190) NOT NULL,
            locale VARCHAR(12) NOT NULL,
            timezone VARCHAR(64) NOT NULL,
            flags TEXT NOT NULL,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL
        )');

        $this->addSql('CREATE TABLE IF NOT EXISTS app_api_key (
            id CHAR(26) NOT NULL PRIMARY KEY,
            user_id CHAR(26) NOT NULL,
            label VARCHAR(190) NOT NULL,
            hashed_key VARCHAR(128) NOT NULL,
            scopes TEXT NOT NULL,
            last_used_at DATETIME DEFAULT NULL,
            created_at DATETIME NOT NULL,
            revoked_at DATETIME DEFAULT NULL,
            FOREIGN KEY(user_id) REFERENCES app_user(id) ON DELETE CASCADE
        )');

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
            metadata TEXT NOT NULL,
            updated_at DATETIME NOT NULL
        )');

        // Content tables in the attached user database.
        $this->addSql('CREATE TABLE IF NOT EXISTS user_brain.app_entity (
            id CHAR(26) NOT NULL PRIMARY KEY,
            project_id CHAR(26) NOT NULL,
            slug VARCHAR(190) NOT NULL,
            type VARCHAR(190) NOT NULL,
            status VARCHAR(32) NOT NULL,
            flags TEXT NOT NULL,
            meta TEXT NOT NULL,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            UNIQUE(project_id, slug)
        )');

        $this->addSql('CREATE TABLE IF NOT EXISTS user_brain.app_entity_version (
            id CHAR(26) NOT NULL PRIMARY KEY,
            entity_id CHAR(26) NOT NULL,
            version VARCHAR(26) NOT NULL,
            payload TEXT NOT NULL,
            committed_at DATETIME NOT NULL,
            committed_by CHAR(26) NOT NULL,
            commit_message TEXT,
            is_active INTEGER NOT NULL DEFAULT 0,
            checksum VARCHAR(64) NOT NULL,
            FOREIGN KEY(entity_id) REFERENCES app_entity(id) ON DELETE CASCADE
        )');

        $this->addSql('CREATE TABLE IF NOT EXISTS user_brain.app_draft (
            id CHAR(26) NOT NULL PRIMARY KEY,
            entity_id CHAR(26) NOT NULL,
            payload TEXT NOT NULL,
            updated_at DATETIME NOT NULL,
            updated_by CHAR(26) NOT NULL,
            autosave INTEGER NOT NULL DEFAULT 0,
            FOREIGN KEY(entity_id) REFERENCES app_entity(id) ON DELETE CASCADE
        )');

        $this->addSql('CREATE TABLE IF NOT EXISTS user_brain.app_relation (
            id CHAR(26) NOT NULL PRIMARY KEY,
            project_id CHAR(26) NOT NULL,
            source_entity_id CHAR(26) NOT NULL,
            target_entity_id CHAR(26) NOT NULL,
            relation_type VARCHAR(64) NOT NULL,
            payload TEXT NOT NULL,
            created_at DATETIME NOT NULL,
            FOREIGN KEY(source_entity_id) REFERENCES app_entity(id) ON DELETE CASCADE,
            FOREIGN KEY(target_entity_id) REFERENCES app_entity(id) ON DELETE CASCADE
        )');

        $this->seedSystemSettings();
        $this->seedDefaultProjects();
        $this->seedModuleStates();
        $this->seedThemeStates();
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE IF EXISTS app_module_state');
        $this->addSql('DROP TABLE IF EXISTS app_system_setting');
        $this->addSql('DROP TABLE IF EXISTS app_api_key');
        $this->addSql('DROP TABLE IF EXISTS app_log');
        $this->addSql('DROP TABLE IF EXISTS app_user');
        $this->addSql('DROP TABLE IF EXISTS app_template');
        $this->addSql('DROP TABLE IF EXISTS app_schema');
        $this->addSql('DROP TABLE IF EXISTS app_project');
        $this->addSql('DROP TABLE IF EXISTS app_theme_state');

        $this->addSql('DROP TABLE IF EXISTS user_brain.app_relation');
        $this->addSql('DROP TABLE IF EXISTS user_brain.app_draft');
        $this->addSql('DROP TABLE IF EXISTS user_brain.app_entity_version');
        $this->addSql('DROP TABLE IF EXISTS user_brain.app_entity');
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
                    'type' => $type,
                    'created_at' => $now,
                    'updated_at' => $now,
                ],
                [
                    'key' => \PDO::PARAM_STR,
                    'value' => \PDO::PARAM_STR,
                    'type' => \PDO::PARAM_STR,
                    'created_at' => \PDO::PARAM_STR,
                    'updated_at' => \PDO::PARAM_STR,
                ]
            );
        }
    }

    private function seedDefaultProjects(): void
    {
        $now = (new \DateTimeImmutable())->format('Y-m-d H:i:s');

        foreach (DefaultProjects::all() as $project) {
            $id = (new Ulid())->toBase32();
            $settings = json_encode($project['settings'], JSON_THROW_ON_ERROR);

            $this->addSql(
                'INSERT OR IGNORE INTO app_project (id, slug, name, locale, timezone, settings, created_at, updated_at)
                 VALUES (:id, :slug, :name, :locale, :timezone, :settings, :created_at, :updated_at)',
                [
                    'id' => $id,
                    'slug' => $project['slug'],
                    'name' => $project['name'],
                    'locale' => $project['locale'],
                    'timezone' => $project['timezone'],
                    'settings' => $settings,
                    'created_at' => $now,
                    'updated_at' => $now,
                ],
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
                'metadata' => json_encode(['locked' => true], JSON_THROW_ON_ERROR),
                'updated_at' => $now,
            ],
            [
                'name' => \PDO::PARAM_STR,
                'enabled' => \PDO::PARAM_INT,
                'metadata' => \PDO::PARAM_STR,
                'updated_at' => \PDO::PARAM_STR,
            ]
        );
    }

    private function seedThemeStates(): void
    {
        $now = (new \DateTimeImmutable())->format('Y-m-d H:i:s');

        $this->addSql(
            'INSERT OR IGNORE INTO app_theme_state (name, enabled, metadata, updated_at) VALUES (:name, :enabled, :metadata, :updated_at)',
            [
                'name' => 'base',
                'enabled' => 1,
                'metadata' => json_encode(['locked' => true], JSON_THROW_ON_ERROR),
                'updated_at' => $now,
            ],
            [
                'name' => \PDO::PARAM_STR,
                'enabled' => \PDO::PARAM_INT,
                'metadata' => \PDO::PARAM_STR,
                'updated_at' => \PDO::PARAM_STR,
            ]
        );
    }
}
