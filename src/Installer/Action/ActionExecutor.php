<?php

declare(strict_types=1);

namespace App\Installer\Action;

use App\Setup\SetupState;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Process\Process;
use ZipArchive;

final class ActionExecutor
{
    public function __construct(
        #[Autowire('%kernel.project_dir%')]
        private readonly string $projectDir,
        private readonly SetupState $setupState,
        private readonly Filesystem $filesystem,
    ) {
    }

    /**
     * @param callable(string,string=):void $emit
     */
    public function execute(array $steps, ?UploadedFile $package, callable $emit): void
    {
        foreach ($steps as $index => $rawStep) {
            if (!\is_array($rawStep) || !isset($rawStep['type'])) {
                throw new \InvalidArgumentException(sprintf('Invalid action definition at index %d.', $index));
            }

            $type = (string) $rawStep['type'];

            $emit('log', sprintf('> [%d/%d] %s', $index + 1, \count($steps), $this->describeStep($rawStep)));

            match ($type) {
                'log' => $this->emitMessageStep($rawStep, $emit),
                'extract_upload' => $this->extractUploadedPackage($package, $rawStep),
                'console' => $this->runConsoleCommand($rawStep, $emit),
                'shell' => $this->runShellCommand($rawStep, $emit),
                'init' => $this->runInit($rawStep, $emit),
                'lock' => $this->createLock(),
                default => throw new \InvalidArgumentException(sprintf('Unsupported action type "%s".', $type)),
            };
        }
    }

    private function describeStep(array $step): string
    {
        return match ($step['type']) {
            'log' => (string) ($step['message'] ?? 'Note'),
            'extract_upload' => 'Extracting uploaded package',
            'console' => sprintf('Run bin/console %s', $this->stringifyCommand($step['command'] ?? [])),
            'shell' => sprintf('Run %s', $this->stringifyCommand($step['command'] ?? [])),
            'init' => sprintf('Run bin/init (%s)', $step['environment'] ?? 'auto'),
            'lock' => 'Write setup.lock',
            default => 'Unknown step',
        };
    }

    private function stringifyCommand(array $command): string
    {
        return implode(' ', array_map(static fn (string $part): string => escapeshellarg($part), $command));
    }

    /**
     * @param callable(string,string=):void $emit
     */
    private function emitMessageStep(array $step, callable $emit): void
    {
        $message = (string) ($step['message'] ?? '');
        if ($message !== '') {
            $emit('log', $message);
        }
    }

    private function extractUploadedPackage(?UploadedFile $package, array $step): void
    {
        if ($package === null) {
            throw new \RuntimeException('No package uploaded for extraction.');
        }

        $target = $step['target'] ?? $this->projectDir;
        if (!\is_string($target) || $target === '') {
            $target = $this->projectDir;
        } elseif (!str_starts_with($target, '/')) {
            $target = $this->projectDir.'/'.$target;
        }

        $zip = new ZipArchive();
        if ($zip->open($package->getPathname()) !== true) {
            throw new \RuntimeException('Uploaded archive could not be opened.');
        }

        try {
            for ($i = 0; $i < $zip->numFiles; ++$i) {
                $entry = $zip->getNameIndex($i);
                if ($entry === false || str_contains($entry, '..')) {
                    continue;
                }
            }

            if (!$zip->extractTo($target)) {
                throw new \RuntimeException('Extraction of the archive failed.');
            }
        } finally {
            $zip->close();
        }
    }

    /**
     * @param callable(string,string=):void $emit
     */
    private function runConsoleCommand(array $step, callable $emit): void
    {
        $command = $step['command'] ?? [];
        if (!\is_array($command) || $command === []) {
            throw new \InvalidArgumentException('Console step requires a "command" parameter.');
        }

        $php = $this->resolvePhpBinary();
        $env = $this->resolveEnvironmentOverrides($step, ['APP_DEBUG' => '0']);
        $process = new Process(array_merge([$php, $this->projectDir.'/bin/console'], $command), $this->projectDir, $env);
        $this->runProcess($process, $emit);
    }

    /**
     * @param callable(string,string=):void $emit
     */
    private function runShellCommand(array $step, callable $emit): void
    {
        $command = $step['command'] ?? [];
        if (!\is_array($command) || $command === []) {
            throw new \InvalidArgumentException('Shell step requires a "command" parameter.');
        }

        $env = $this->resolveEnvironmentOverrides($step, []);
        $process = new Process($command, $this->projectDir, $env);
        $this->runProcess($process, $emit);
    }

    /**
     * @param callable(string,string=):void $emit
     */
    private function runInit(array $step, callable $emit): void
    {
        $environment = $step['environment'] ?? null;
        if ($environment !== null && !\is_string($environment)) {
            throw new \InvalidArgumentException('Init step expects "environment" to be a string.');
        }

        $arguments = [$this->projectDir.'/bin/init'];
        if ($environment !== null && $environment !== '') {
            $arguments[] = $environment;
        }

        $process = new Process($arguments, $this->projectDir);
        $this->runProcess($process, $emit);
    }

    private function createLock(): void
    {
        $this->filesystem->mkdir(\dirname($this->setupState->lockFilePath()));
        $this->setupState->markCompleted();
    }

    /**
     * @param callable(string,string=):void $emit
     */
    private function runProcess(Process $process, callable $emit): void
    {
        $process->setTimeout(null);

        $errors = [];
        $partials = [
            Process::OUT => '',
            Process::ERR => '',
        ];

        $drain = function (string $chunk, string $type) use (&$partials, &$errors, $emit, &$drain): void {
            if ($chunk === '') {
                return;
            }

            $buffer = $partials[$type].$chunk;
            $lines = preg_split("/\r\n|\r|\n/", $buffer);
            if ($lines === false) {
                $lines = [$buffer];
                $partials[$type] = '';
            } else {
                $partials[$type] = array_pop($lines) ?? '';
            }

            foreach ($lines as $line) {
                if ($line === '') {
                    continue;
                }

                if ($type === Process::ERR) {
                    $errors[] = $line;
                }

                $emit('log', $line);
            }
        };

        $process->start();
        $process->wait(function (string $type, string $buffer) use (&$drain): void {
            $drain($buffer, $type);
        });

        foreach ($partials as $type => $remaining) {
            if ($remaining === '') {
                continue;
            }

            if ($type === Process::ERR) {
                $errors[] = $remaining;
            }

            $emit('log', $remaining);
        }

        if (!$process->isSuccessful()) {
            foreach ($errors as $line) {
                $emit('error', $line);
            }

            throw new ProcessFailedException($process);
        }
    }

    private function resolvePhpBinary(): string
    {
        $binary = PHP_BINARY;

        if ($binary !== '' && !str_contains(basename($binary), 'php-fpm')) {
            return $binary;
        }

        $candidate = PHP_BINDIR.'/php';
        if (is_file($candidate) && is_executable($candidate)) {
            return $candidate;
        }

        return 'php';
    }

    /**
     * @return array<string,string>
     */
    private function resolveEnvironmentOverrides(array $step, array $defaults): array
    {
        $env = $defaults;

        if (!isset($step['env'])) {
            return $env;
        }

        if (!\is_array($step['env'])) {
            throw new \InvalidArgumentException('Process environment overrides must be provided as an array.');
        }

        foreach ($step['env'] as $key => $value) {
            if (!\is_string($key) || $key === '') {
                throw new \InvalidArgumentException('Environment variable names must be non-empty strings.');
            }

            if ($value === null) {
                $env[$key] = '';
                continue;
            }

            if (!\is_scalar($value)) {
                throw new \InvalidArgumentException(sprintf('Environment variable "%s" must be scalar or null.', $key));
            }

            $env[$key] = (string) $value;
        }

        return $env;
    }
}
