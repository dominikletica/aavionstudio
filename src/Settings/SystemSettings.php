<?php

declare(strict_types=1);

namespace App\Settings;

use App\Installer\DefaultSystemSettings;
use Doctrine\DBAL\Connection;
use JsonException;

final class SystemSettings
{
    private ?array $cache = null;

    public function __construct(
        private readonly Connection $connection,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function all(): array
    {
        if ($this->cache === null) {
            $this->cache = $this->load();
        }

        return $this->cache;
    }

    public function get(string $key, mixed $default = null): mixed
    {
        $settings = $this->all();

        return $settings[$key] ?? $default;
    }

    public function reload(): void
    {
        $this->cache = null;
    }

    /**
     * @return array<string, mixed>
     */
    private function load(): array
    {
        $defaults = DefaultSystemSettings::all();
        $values = [];

        try {
            $rows = $this->connection->fetchAllAssociative('SELECT key, value FROM app_system_setting');
        } catch (\Throwable) {
            return $defaults;
        }

        foreach ($rows as $row) {
            $key = (string) $row['key'];
            $values[$key] = $this->decodeValue($row['value'] ?? null);
        }

        foreach ($values as $key => $value) {
            $defaults[$key] = $value;
        }

        return $defaults;
    }

    private function decodeValue(mixed $raw): mixed
    {
        if (!\is_string($raw) || $raw === '') {
            return null;
        }

        try {
            return json_decode($raw, true, flags: JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            return $raw;
        }
    }
}
