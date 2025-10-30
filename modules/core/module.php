<?php

declare(strict_types=1);

use App\Module\ModuleManifest;

return new ModuleManifest(
    slug: 'core',
    name: 'Core Platform',
    basePath: __DIR__,
    priority: 1000,
    services: 'config/services.php',
    routes: null,
    repository: 'https://github.com/dominikletica/aavionstudio',
    navigation: [],
    capabilities: [],
    metadata: [
        'locked' => true,
    ],
);
