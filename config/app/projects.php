<?php

declare(strict_types=1);

return [
    [
        'slug' => 'default',
        'name' => 'Default Project',
        'locale' => 'en',
        'timezone' => 'UTC',
        'settings' => [
            'description' => 'The default project for new installations.',
            'navigation' => [
                'auto_include' => true,
            ],
            'errors' => [],
            'accent_color' => null,
            'login_required' => false,
            'cache' => [
                'enabled' => true,
                'default_ttl' => 600,
            ],
            'features' => [
                'drafts' => true,
                'comments' => false,
                'previews' => true,
            ],
        ],
    ],
];
