<?php

declare(strict_types=1);

namespace App\Service;

use App\Asset\AssetPipelineRefresher;
use App\Asset\AssetStateTracker;
use App\Message\AssetRebuildMessage;
use Symfony\Component\Messenger\MessageBusInterface;

final class AssetRebuildScheduler
{
    public function __construct(
        private readonly AssetStateTracker $stateTracker,
        private readonly MessageBusInterface $messageBus,
        private readonly AssetPipelineRefresher $pipelineRefresher,
    ) {
    }

    /**
     * Dispatches an asynchronous rebuild when changes are detected.
     *
     * @return bool true when a message was dispatched
     */
    public function schedule(bool $force = false): bool
    {
        $currentState = $this->stateTracker->currentState();

        if (!$force && $this->stateTracker->isUpToDate($currentState)) {
            return false;
        }

        $this->messageBus->dispatch(new AssetRebuildMessage($force));

        return true;
    }

    /**
     * Runs the pipeline immediately in the current process.
     *
     * @return bool true when a rebuild was executed
     */
    public function runNow(bool $force = false): bool
    {
        return $this->pipelineRefresher->refresh($force);
    }
}
