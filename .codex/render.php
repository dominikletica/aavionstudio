#!/usr/bin/env php
<?php

declare(strict_types=1);

use App\Kernel;
use Symfony\Component\Dotenv\Dotenv;
use Symfony\Component\HttpFoundation\Request;

$projectRoot = dirname(__DIR__);

require $projectRoot . '/vendor/autoload.php';

(new Dotenv())
    ->usePutenv()
    ->bootEnv($projectRoot . '/.env', 'APP_ENV', ['test']);

$isCli = PHP_SAPI === 'cli';

if ($isCli) {
    $route = $argv[1] ?? '/';
    $method = $argv[2] ?? 'GET';
} else {
    $route = $_GET['route'] ?? '/';
    $method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
}

$env = $_SERVER['APP_ENV'] ?? 'dev';
$debug = filter_var($_SERVER['APP_DEBUG'] ?? ($env !== 'prod'), FILTER_VALIDATE_BOOL);

$kernel = new Kernel($env, $debug);

$request = Request::create(
    $route,
    $method,
    $isCli ? [] : $_GET,
    [],
    [],
    [
        'HTTP_HOST' => $_SERVER['HTTP_HOST'] ?? 'localhost',
        'HTTPS' => ($_SERVER['HTTPS'] ?? 'off') === 'on' ? 'on' : 'off',
    ],
);

$response = $kernel->handle($request);

if ($isCli) {
    fwrite(STDOUT, $response->getContent());
} else {
    http_response_code($response->getStatusCode());
    foreach ($response->headers->allPreserveCaseWithoutCookies() as $name => $values) {
        foreach ($values as $value) {
            header($name . ': ' . $value, false);
        }
    }
    echo $response->getContent();
}

$kernel->terminate($request, $response);

