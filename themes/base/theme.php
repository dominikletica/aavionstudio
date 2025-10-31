<?php

declare(strict_types=1);

use App\Theme\ThemeManifest;

return new ThemeManifest(
    slug: 'base',
    name: 'Base Theme',
    description: 'Default built-in theme containing repository fonts/icons and minimal Twig scaffolding.',
    basePath: __DIR__,
    version: '1.0.0-dev',
    priority: 1000,
    services: null,
    assets: 'assets',
    repository: 'https://github.com/dominikletica/aavionstudio',
    metadata: ['locked' => true],
);
