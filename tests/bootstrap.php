<?php

use Symfony\Component\Dotenv\Dotenv;

require dirname(__DIR__).'/vendor/autoload.php';

if (method_exists(Dotenv::class, 'bootEnv')) {
    (new Dotenv())->bootEnv(dirname(__DIR__).'/.env');
}

if ($_SERVER['APP_DEBUG']) {
    umask(0000);
}

$projectDir = dirname(__DIR__);
$testVarDir = $projectDir.'/var/test';

if (!is_dir($testVarDir)) {
    mkdir($testVarDir, 0777, true);
}

$setupLock = $testVarDir.'/.setup.lock';

if (!file_exists($setupLock)) {
    touch($setupLock);
}
