<?php

declare(strict_types=1);

namespace App\Installer;

final class DefaultProjects
{
    private const CONFIG_PATH = '/config/app/projects.php';

    /**
     * @return list<array{slug:string,name:string,locale:string,timezone:string,settings:array<string,mixed>}>
     */
    public static function all(): array
    {
        $path = self::resolvePath();

        /** @var list<array{slug:string,name:string,locale:string,timezone:string,settings:array<string,mixed>}> $projects */
        $projects = require $path;

        return $projects;
    }

    private static function resolvePath(): string
    {
        $root = \dirname(__DIR__, 2);
        $path = $root.self::CONFIG_PATH;

        if (!is_file($path)) {
            throw new \RuntimeException(\sprintf('Default project seed file missing at "%s".', $path));
        }

        return $path;
    }
}
