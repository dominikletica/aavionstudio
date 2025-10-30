<?php

declare(strict_types=1);

use App\Bootstrap\RootEntryPoint;

require __DIR__.'/vendor/autoload.php';

$context = RootEntryPoint::prepare(__DIR__, $_SERVER, $_GET, $_ENV);

// Rebuild $_REQUEST to reflect the modified $_GET payload (with "route" stripped).
$_REQUEST = $_GET + $_POST + $_COOKIE;

return require $context->frontController;
