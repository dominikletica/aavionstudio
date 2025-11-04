<?php

declare(strict_types=1);

namespace App\Setup;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Filesystem\Filesystem;

final class SetupPayloadBuilder
{
    public function __construct(
        private readonly SetupConfiguration $configuration,
        private readonly Filesystem $filesystem,
        #[Autowire('%app.setup.payload_path%')]
        private readonly string $payloadPath,
    ) {
    }

    /**
     * @return string Absolute path to the payload file
     */
    public function build(): string
    {
        $payload = [
            'storage' => $this->configuration->getStorageConfig(),
            'admin' => $this->configuration->getAdminAccount(),
            'settings' => $this->configuration->getSystemSettings(),
            'projects' => $this->configuration->getProjects(),
        ];

        $payloadDir = \dirname($this->payloadPath);
        $this->filesystem->mkdir($payloadDir);

        $encoded = json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
        $this->filesystem->dumpFile($this->payloadPath, $encoded);
        $this->filesystem->chmod($this->payloadPath, 0600);

        return $this->payloadPath;
    }

    public function cleanup(): void
    {
        if ($this->filesystem->exists($this->payloadPath)) {
            $this->filesystem->remove($this->payloadPath);
        }
    }
}

