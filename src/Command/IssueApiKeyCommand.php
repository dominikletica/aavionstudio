<?php

declare(strict_types=1);

namespace App\Command;

use App\Security\Api\ApiKeyManager;
use App\Security\User\AppUserRepository;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:api-key:issue',
    description: 'Issue a new API key for a user and print the secret once.'
)]
final class IssueApiKeyCommand extends Command
{
    public function __construct(
        private readonly ApiKeyManager $apiKeyManager,
        private readonly AppUserRepository $userRepository,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('user', InputArgument::REQUIRED, 'User ULID or email address')
            ->addOption('label', null, InputOption::VALUE_REQUIRED, 'Label for the API key', 'CLI issued key')
            ->addOption('scope', null, InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY, 'Capability scope (repeatable)')
            ->addOption('expires', null, InputOption::VALUE_REQUIRED, 'Expiration date (Y-m-d or ISO8601)');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $identifier = (string) $input->getArgument('user');

        $user = $this->userRepository->findById($identifier);
        if ($user === null) {
            $user = $this->userRepository->findActiveByEmail($identifier);
        }

        if ($user === null) {
            $io->error(sprintf('User "%s" not found or inactive.', $identifier));

            return Command::FAILURE;
        }

        $label = (string) $input->getOption('label');
        $scopes = array_map(static fn ($scope): string => (string) $scope, $input->getOption('scope') ?? []);

        $expiresAtInput = $input->getOption('expires');
        $expiresAt = null;

        if (is_string($expiresAtInput) && $expiresAtInput !== '') {
            try {
                $expiresAt = new \DateTimeImmutable($expiresAtInput);
            } catch (\Exception $exception) {
                $io->error(sprintf('Invalid expires value: %s', $exception->getMessage()));

                return Command::INVALID;
            }
        }

        $apiKey = $this->apiKeyManager->issue($user['id'], $label, $scopes, $expiresAt);

        $io->success('API key created successfully. Copy the secret now—it will not be shown again.');
        $io->table(
            ['ID', 'Label', 'Secret', 'Scopes', 'Expires At'],
            [[
                $apiKey['id'],
                $apiKey['label'],
                $apiKey['secret'],
                $scopes !== [] ? implode(', ', $scopes) : '(none)',
                $expiresAt?->format(DATE_ATOM) ?? 'never',
            ]]
        );

        return Command::SUCCESS;
    }
}
