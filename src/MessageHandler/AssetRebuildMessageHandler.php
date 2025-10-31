<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\Asset\AssetPipelineRefresher;
use App\Message\AssetRebuildMessage;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final class AssetRebuildMessageHandler
{
    public function __construct(
        private readonly AssetPipelineRefresher $pipelineRefresher,
    ) {
    }

    public function __invoke(AssetRebuildMessage $message): void
    {
        $this->pipelineRefresher->refresh($message->force);
    }
}
