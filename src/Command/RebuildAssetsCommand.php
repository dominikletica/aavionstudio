<?php

declare(strict_types=1);

namespace App\Command;

use App\Service\AssetRebuildScheduler;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:assets:rebuild',
    description: 'Rebuilds mirrored assets, import map, Tailwind output, and Symfony caches.',
)]
final class RebuildAssetsCommand extends Command
{
    public function __construct(
        private readonly AssetRebuildScheduler $scheduler,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('force', 'f', InputOption::VALUE_NONE, 'Force rebuild even when no changes were detected')
            ->addOption('async', 'a', InputOption::VALUE_NONE, 'Dispatch rebuild asynchronously via Messenger');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $force = (bool) $input->getOption('force');
        $async = (bool) $input->getOption('async');

        if ($async) {
            $dispatched = $this->scheduler->schedule($force);
            if ($dispatched) {
                $io->success('Queued asset rebuild job.');
            } else {
                $io->note('Assets already up to date; no rebuild queued.');
            }

            return Command::SUCCESS;
        }

        $executed = $this->scheduler->runNow($force);
        if ($executed) {
            $io->success('Asset pipeline rebuilt successfully.');
        } else {
            $io->note('Assets already up to date; nothing to rebuild.');
        }

        return Command::SUCCESS;
    }
}
