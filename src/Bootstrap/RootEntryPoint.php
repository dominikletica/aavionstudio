<?php

declare(strict_types=1);

namespace App\Bootstrap;

final class RootEntryPoint
{
    public const FLAG_ROOT_ENTRY = 'AAVION_ROOT_ENTRY';
    public const FLAG_FORCED = 'AAVION_ROOT_ENTRY_FORCED';
    public const FLAG_ROUTE = 'AAVION_ROOT_ENTRY_ROUTE';
    public const FLAG_ORIGINAL_URI = 'AAVION_ROOT_ENTRY_ORIGINAL_URI';
    public const FLAG_REQUEST_URI = 'AAVION_ROOT_ENTRY_REQUEST_URI';
    public const FLAG_ORIGINAL_SCRIPT = 'AAVION_ROOT_ENTRY_ORIGINAL_SCRIPT';

    /**
     * @param array<string, mixed> $server
     * @param array<string, mixed> $get
     * @param array<string, mixed> $env
     */
    public static function prepare(string $projectDir, array &$server, array &$get, array &$env): RootEntryContext
    {
        $frontController = rtrim($projectDir, '/').'/public/index.php';

        if (!is_file($frontController)) {
            throw new \RuntimeException(sprintf('Front controller missing at "%s".', $frontController));
        }

        $forced = self::toBool(
            $env['APP_FORCE_ROOT_ENTRY']
                ?? $server['APP_FORCE_ROOT_ENTRY']
                ?? getenv('APP_FORCE_ROOT_ENTRY')
        );

        $originalScript = (string) ($server['SCRIPT_FILENAME'] ?? ($projectDir.'/index.php'));
        $server[self::FLAG_ORIGINAL_SCRIPT] = $originalScript;
        $env[self::FLAG_ORIGINAL_SCRIPT] = $originalScript;
        $server['SCRIPT_FILENAME'] = $frontController;

        $originalUri = (string) ($server['REQUEST_URI'] ?? '/');
        $route = self::determineRoute($get, $server, $originalUri);
        $queryString = http_build_query($get);

        $requestUri = $route.($queryString !== '' ? '?'.$queryString : '');

        $server['PATH_INFO'] = $route;
        $server['REQUEST_URI'] = $requestUri;
        $server['QUERY_STRING'] = $queryString;
        $server[self::FLAG_ROOT_ENTRY] = '1';
        $server[self::FLAG_FORCED] = $forced ? '1' : '0';
        $server[self::FLAG_ROUTE] = $route;
        $server[self::FLAG_ORIGINAL_URI] = $originalUri;
        $server[self::FLAG_REQUEST_URI] = $requestUri;

        $env[self::FLAG_ROOT_ENTRY] = '1';
        $env[self::FLAG_FORCED] = $forced ? '1' : '0';
        $env[self::FLAG_ROUTE] = $route;
        $env[self::FLAG_ORIGINAL_URI] = $originalUri;
        $env[self::FLAG_REQUEST_URI] = $requestUri;

        return new RootEntryContext(
            frontController: $frontController,
            route: $route,
            queryString: $queryString,
            compatibilityMode: true,
            forced: $forced,
            originalUri: $originalUri,
        );
    }

    /**
     * @param array<string, mixed> $get
     * @param array<string, mixed> $server
     */
    private static function determineRoute(array &$get, array $server, string $originalUri): string
    {
        $routeParam = $get['route'] ?? null;

        if (\is_array($routeParam)) {
            $routeParam = reset($routeParam);
        }

        if (\is_string($routeParam) && $routeParam !== '') {
            unset($get['route']);

            return self::sanitizeRoute($routeParam);
        }

        $path = $server['PATH_INFO'] ?? null;

        if (!\is_string($path) || $path === '') {
            $parsed = parse_url($originalUri, PHP_URL_PATH);
            $path = \is_string($parsed) ? $parsed : '/';
        }

        return self::sanitizeRoute($path);
    }

    private static function sanitizeRoute(string $route): string
    {
        $path = parse_url($route, PHP_URL_PATH);

        if (!\is_string($path) || $path === '') {
            return '/';
        }

        $segments = explode('/', $path);
        $stack = [];

        foreach ($segments as $segment) {
            if ($segment === '' || $segment === '.') {
                continue;
            }

            if ($segment === '..') {
                array_pop($stack);

                continue;
            }

            $stack[] = $segment;
        }

        return '/'.implode('/', $stack);
    }

    private static function toBool(mixed $value): bool
    {
        if (\is_bool($value)) {
            return $value;
        }

        if (\is_int($value)) {
            return $value === 1;
        }

        if ($value === null) {
            return false;
        }

        if (\is_string($value)) {
            $value = strtolower(trim($value));

            return \in_array($value, ['1', 'true', 'on', 'yes'], true);
        }

        return false;
    }
}
