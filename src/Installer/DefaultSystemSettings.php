<?php

declare(strict_types=1);

namespace App\Installer;

final class DefaultSystemSettings
{
    private const CONFIG_PATH = '/config/app/system_settings.php';

    /**
     * @return array<string, mixed>
     */
    public static function all(): array
    {
        $path = self::resolvePath();

        /** @var array<string, mixed> $settings */
        $settings = require $path;

        return $settings;
    }

    private static function resolvePath(): string
    {
        $root = \dirname(__DIR__, 2);
        $path = $root.self::CONFIG_PATH;

        if (!is_file($path)) {
            throw new \RuntimeException(\sprintf('Default system settings file missing at "%s".', $path));
        }

        return $path;
    }
}
