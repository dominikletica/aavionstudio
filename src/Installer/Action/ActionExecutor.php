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
            // $emit('log', $message);
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

        $this->filesystem->mkdir($target);
        $targetRoot = realpath($target);
        if ($targetRoot === false) {
            throw new \RuntimeException('Extraction target could not be resolved.');
        }

        $zip = new ZipArchive();
        if ($zip->open($package->getPathname()) !== true) {
            throw new \RuntimeException('Uploaded archive could not be opened.');
        }

        try {
            for ($i = 0; $i < $zip->numFiles; ++$i) {
                $entryName = $zip->getNameIndex($i);

                if ($entryName === false || $entryName === '') {
                    continue;
                }

                [$destination, $isDirectory] = $this->resolveExtractionPath($entryName, $targetRoot);

                if ($destination === null) {
                    continue;
                }

                if ($isDirectory) {
                    $this->filesystem->mkdir($destination);
                    continue;
                }

                $stream = $zip->getStream($entryName);
                if ($stream === false) {
                    throw new \RuntimeException(sprintf('Failed to open "%s" from the archive.', $entryName));
                }

                $this->filesystem->mkdir(\dirname($destination));
                $targetHandle = @fopen($destination, 'wb');
                if ($targetHandle === false) {
                    fclose($stream);
                    throw new \RuntimeException(sprintf('Failed to write "%s" during extraction.', $destination));
                }

                try {
                    if (stream_copy_to_stream($stream, $targetHandle) === false) {
                        throw new \RuntimeException(sprintf('Failed to extract "%s".', $entryName));
                    }
                } finally {
                    fclose($targetHandle);
                    fclose($stream);
                }
            }
        } finally {
            $zip->close();
        }
    }

    /**
     * @return array{0: string|null, 1: bool}
     */
    private function resolveExtractionPath(string $entry, string $targetRoot): array
    {
        if (str_contains($entry, "\0")) {
            throw new \RuntimeException('Archive entry contains invalid characters.');
        }

        $normalized = str_replace('\\', '/', $entry);
        $isDirectory = str_ends_with($normalized, '/');
        $normalized = trim($normalized, '/');

        if ($normalized === '') {
            return [null, $isDirectory];
        }

        if (preg_match('/^[A-Za-z]:/', $normalized) === 1) {
            throw new \RuntimeException(sprintf('Archive entry "%s" references an absolute path.', $entry));
        }

        $segments = [];
        foreach (explode('/', $normalized) as $segment) {
            if ($segment === '' || $segment === '.') {
                continue;
            }

            if ($segment === '..') {
                throw new \RuntimeException(sprintf('Archive entry "%s" attempts directory traversal.', $entry));
            }

            $segments[] = $segment;
        }

        if ($segments === []) {
            return [null, $isDirectory];
        }

        $root = rtrim($targetRoot, DIRECTORY_SEPARATOR);
        $relativePath = implode(DIRECTORY_SEPARATOR, $segments);
        $destination = $root.DIRECTORY_SEPARATOR.$relativePath;

        $normalizedTarget = $root.DIRECTORY_SEPARATOR;
        if (!str_starts_with($destination, $normalizedTarget) && $destination !== $root) {
            throw new \RuntimeException(sprintf('Archive entry "%s" resolves outside the extraction target.', $entry));
        }

        return [$destination, $isDirectory];
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
