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
        $row = $this->connection->fetchAssociative(
            'SELECT id, slug, name FROM app_project WHERE id = :id',
            ['id' => $projectId]
        );

        if ($row === false) {
            return null;
        }

        return [
            'id' => (string) $row['id'],
            'slug' => (string) $row['slug'],
            'name' => (string) $row['name'],
        ];
    }
}
