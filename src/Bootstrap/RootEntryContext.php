<?php

declare(strict_types=1);

namespace App\Bootstrap;

final class RootEntryContext
{
    public function __construct(
        public readonly string $frontController,
        public readonly string $route,
        public readonly string $queryString,
        public readonly bool $compatibilityMode,
        public readonly bool $forced,
        public readonly string $originalUri,
    ) {
    }

    public function requestUri(): string
    {
        return $this->route.($this->queryString !== '' ? '?'.$this->queryString : '');
    }
}
