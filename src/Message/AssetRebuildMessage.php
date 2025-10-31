<?php

declare(strict_types=1);

namespace App\Message;

final class AssetRebuildMessage
{
    public function __construct(
        public readonly bool $force = false,
    ) {
    }
}
