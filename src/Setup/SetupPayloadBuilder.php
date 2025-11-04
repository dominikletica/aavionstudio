<?php

declare(strict_types=1);

namespace App\Setup;

use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

final class SetupPayloadBuilder
{
    public function __construct(
        private readonly SetupConfiguration $configuration,
        #[Autowire('%kernel.project_dir%')]
        private readonly string $projectDir,
        private readonly Filesystem $filesystem,
    ) {
    }

    /**
     * @return string Absolute path to the payload file
     */
    public function build(): string
    {
        $payload = [
            'environment' => $this->configuration->getEnvironmentOverrides(),
            'storage' => $this->configuration->getStorageConfig(),
            'admin' => $this->configuration->getAdminAccount(),
            'settings' => $this->configuration->getSystemSettings(),
        ];

        $payloadDir = $this->projectDir.'/var/setup';
        $this->filesystem->mkdir($payloadDir);

        $path = $payloadDir.'/runtime.json';
        $encoded = json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
        $this->filesystem->dumpFile($path, $encoded);
        $this->filesystem->chmod($path, 0600);

        return $path;
    }

    public function cleanup(): void
    {
        $path = $this->projectDir.'/var/setup/runtime.json';
        if ($this->filesystem->exists($path)) {
            $this->filesystem->remove($path);
        }
    }
}
