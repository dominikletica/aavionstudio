<?php

declare(strict_types=1);

namespace App\Doctrine\Health;

final class SqliteHealthReport
{
    public function __construct(
        public readonly string $primaryPath,
        public readonly string $secondaryPath,
        public readonly bool $primaryExists,
        public readonly bool $secondaryExists,
        public readonly bool $secondaryAttached,
        public readonly int $busyTimeoutMs,
    ) {
    }

    /**
        * @return array<string, int|string|bool>
        */
    public function toArray(): array
    {
        return [
            'primary_path' => $this->primaryPath,
            'secondary_path' => $this->secondaryPath,
            'primary_exists' => $this->primaryExists,
            'secondary_exists' => $this->secondaryExists,
            'secondary_attached' => $this->secondaryAttached,
            'busy_timeout_ms' => $this->busyTimeoutMs,
        ];
    }
}
