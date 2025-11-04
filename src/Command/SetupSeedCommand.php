<?php

declare(strict_types=1);

namespace App\Command;

use App\Security\User\UserCreator;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Filesystem\Filesystem;

#[AsCommand(name: 'app:setup:seed', description: 'Seeds installer payload (administrator account, settings) after bin/init completes.')]
final class SetupSeedCommand extends Command
{
    public function __construct(
        private readonly UserCreator $userCreator,
        private readonly Connection $connection,
        private readonly Filesystem $filesystem,
        #[Autowire('%kernel.project_dir%')]
        private readonly string $projectDir,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('payload', null, InputOption::VALUE_REQUIRED, 'Path to the installer payload JSON generated before bin/init runs.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $payloadPath = (string) ($input->getOption('payload') ?? '');
        if ($payloadPath === '') {
            $payloadPath = $this->projectDir.'/var/setup/runtime.json';
        }

        if (!is_file($payloadPath)) {
            $io->warning(sprintf('Payload file not found (%s). Nothing to seed.', $payloadPath));
            return Command::SUCCESS;
        }

        try {
            $raw = (string) file_get_contents($payloadPath);
            $data = json_decode($raw, true, flags: JSON_THROW_ON_ERROR);
        } catch (\Throwable $exception) {
            $io->error(sprintf('Failed to decode payload: %s', $exception->getMessage()));
            return Command::FAILURE;
        }

        $admin = \is_array($data['admin'] ?? null) ? $data['admin'] : [];
        $email = strtolower(trim((string) ($admin['email'] ?? '')));
        $displayName = trim((string) ($admin['display_name'] ?? ''));
        $password = (string) ($admin['password'] ?? '');
        $locale = (string) ($admin['locale'] ?? 'en');
        $timezone = (string) ($admin['timezone'] ?? 'UTC');

        if ($email === '' || $password === '') {
            $io->error('Payload is missing administrator credentials.');
            return Command::FAILURE;
        }

        try {
            $schemaManager = $this->connection->createSchemaManager();
        } catch (\Throwable $exception) {
            $io->error(sprintf('Unable to inspect database schema: %s', $exception->getMessage()));
            return Command::FAILURE;
        }

        if (! $schemaManager->tablesExist(['app_user'])) {
            $io->warning('User table missing; skipping admin seeding.');
            $this->cleanup($payloadPath);
            return Command::SUCCESS;
        }

        $existing = $this->connection->fetchOne('SELECT 1 FROM app_user WHERE email = :email', [
            'email' => $email,
        ]);

        if ($existing !== false) {
            $io->comment(sprintf('Administrator with email %s already exists, skipping seeding.', $email));
            $this->cleanup($payloadPath);
            return Command::SUCCESS;
        }

        $flags = [];
        if (!empty($admin['require_mfa'])) {
            $flags['require_mfa'] = true;
        }
        if (!empty($admin['recovery_email'])) {
            $flags['recovery_email'] = (string) $admin['recovery_email'];
        }
        if (!empty($admin['recovery_phone'])) {
            $flags['recovery_phone'] = (string) $admin['recovery_phone'];
        }

        $displayName = $displayName !== '' ? $displayName : $email;

        try {
            $this->userCreator->create(
                $email,
                $displayName,
                $password,
                roles: ['ROLE_ADMIN'],
                locale: $locale,
                timezone: $timezone,
                flags: $flags,
            );
        } catch (UniqueConstraintViolationException) {
            $io->comment(sprintf('Administrator with email %s already exists (unique constraint).', $email));
            $this->cleanup($payloadPath);
            return Command::SUCCESS;
        } catch (\Throwable $exception) {
            $io->error(sprintf('Failed to create administrator: %s', $exception->getMessage()));
            return Command::FAILURE;
        }

        $io->success(sprintf('Administrator account %s seeded successfully.', $email));
        $this->cleanup($payloadPath);

        return Command::SUCCESS;
    }

    private function cleanup(string $payloadPath): void
    {
        if ($this->filesystem->exists($payloadPath)) {
            $this->filesystem->remove($payloadPath);
        }
    }
}
