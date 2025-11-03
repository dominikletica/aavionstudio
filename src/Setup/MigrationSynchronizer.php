<?php

declare(strict_types=1);

namespace App\Setup;

use Doctrine\Migrations\DependencyFactory;
use Doctrine\Migrations\Exception\MigrationException;
use Doctrine\Migrations\MigratorConfiguration;
use Psr\Log\LoggerInterface;

final class MigrationSynchronizer
{
    private bool $checked = false;

    public function __construct(
        private readonly SetupState $setupState,
        private readonly DependencyFactory $dependencyFactory,
        private readonly ?LoggerInterface $logger = null,
    ) {
    }

    public function synchronize(): void
    {
        if ($this->checked) {
            return;
        }

        if (! $this->setupState->isCompleted() || $this->setupState->missingDatabases()) {
            return;
        }

        $this->checked = true;

        try {
            $this->dependencyFactory->getMetadataStorage()->ensureInitialized();
            $version = $this->dependencyFactory->getVersionAliasResolver()->resolveVersionAlias('latest');
            $plan = $this->dependencyFactory->getMigrationPlanCalculator()->getPlanUntilVersion($version);

            if (count($plan) === 0) {
                return;
            }

            $configuration = (new MigratorConfiguration())
                ->setAllOrNothing($this->dependencyFactory->getConfiguration()->isAllOrNothing())
                ->setDryRun(false)
                ->setTimeAllQueries(false);

            $this->dependencyFactory->getMigrator()->migrate($plan, $configuration);
        } catch (MigrationException $exception) {
            $this->logger?->error('Automatic migration execution failed', [
                'exception' => $exception,
            ]);
        } catch (\Throwable $exception) {
            $this->logger?->critical('Unexpected error while applying pending migrations', [
                'exception' => $exception,
            ]);
        }
    }
}
