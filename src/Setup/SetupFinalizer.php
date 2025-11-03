<?php

declare(strict_types=1);

namespace App\Setup;

use Doctrine\Migrations\DependencyFactory;
use Doctrine\Migrations\Exception\MigrationException;
use Doctrine\Migrations\MigratorConfiguration;
use Symfony\Component\Filesystem\Filesystem;

final class SetupFinalizer
{
    public function __construct(
        private readonly SetupState $setupState,
        private readonly DependencyFactory $dependencyFactory,
        private readonly Filesystem $filesystem,
    ) {
    }

    /**
     * @throws MigrationException
     */
    public function finalize(): void
    {
        if ($this->setupState->isCompleted()) {
            return;
        }

        $this->filesystem->mkdir(\dirname($this->setupState->primaryDatabasePath()));
        $this->filesystem->mkdir(\dirname($this->setupState->userDatabasePath()));

        $connection = $this->dependencyFactory->getConnection();
        $connection->connect();

        $this->dependencyFactory->getMetadataStorage()->ensureInitialized();

        $version = $this->dependencyFactory->getVersionAliasResolver()->resolveVersionAlias('latest');
        $plan = $this->dependencyFactory->getMigrationPlanCalculator()->getPlanUntilVersion($version);

        if (count($plan) > 0) {
            $configuration = (new MigratorConfiguration())
                ->setAllOrNothing($this->dependencyFactory->getConfiguration()->isAllOrNothing())
                ->setDryRun(false)
                ->setTimeAllQueries(false);

            $this->dependencyFactory->getMigrator()->migrate($plan, $configuration);
        }

        $this->setupState->markCompleted();
    }
}
