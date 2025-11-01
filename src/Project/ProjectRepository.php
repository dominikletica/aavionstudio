<?php

declare(strict_types=1);

namespace App\Project;

use Doctrine\DBAL\Connection;

/**
 * Lightweight project lookup for admin tooling.
 */
final class ProjectRepository
{
    public function __construct(
        private readonly Connection $connection,
    ) {
    }

    /**
     * @return list<array{id:string,slug:string,name:string}>
     */
    public function listProjects(): array
    {
        $rows = $this->connection->fetchAllAssociative(
            'SELECT id, slug, name FROM app_project ORDER BY name ASC'
        );

        return array_map(static fn (array $row): array => [
            'id' => (string) $row['id'],
            'slug' => (string) $row['slug'],
            'name' => (string) $row['name'],
        ], $rows);
    }

    /**
     * @return array{id:string,slug:string,name:string}|null
     */
    public function find(string $projectId): ?array
    {
        try {
            $row = $this->connection->fetchAssociative(
                'SELECT id, slug, name, settings FROM app_project WHERE id = :id',
                ['id' => $projectId]
            );
        } catch (\Doctrine\DBAL\Exception) {
            return null;
        }

        if ($row === false) {
            return null;
        }

        return [
            'id' => (string) $row['id'],
            'slug' => (string) $row['slug'],
            'name' => (string) $row['name'],
            'settings' => $this->decodeSettings($row['settings'] ?? '{}'),
        ];
    }

    public function findBySlug(string $slug): ?array
    {
        try {
            $row = $this->connection->fetchAssociative(
                'SELECT id, slug, name, locale, timezone, settings FROM app_project WHERE slug = :slug',
                ['slug' => $slug]
            );
        } catch (\Doctrine\DBAL\Exception) {
            return null;
        }

        if ($row === false) {
            return null;
        }

        return [
            'id' => (string) $row['id'],
            'slug' => (string) $row['slug'],
            'name' => (string) $row['name'],
            'locale' => (string) $row['locale'],
            'timezone' => (string) $row['timezone'],
            'settings' => $this->decodeSettings($row['settings'] ?? '{}'),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function decodeSettings(?string $json): array
    {
        if ($json === null || $json === '') {
            return [];
        }

        try {
            $data = json_decode($json, true, flags: JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return [];
        }

        return is_array($data) ? $data : [];
    }
}
