<?php

declare(strict_types=1);

return [
    'core.instance_name' => 'aavion Studio',
    'core.tagline' => 'Create. Preview. Ship.',
    'core.url' => 'http://localhost',
    'core.support_email' => 'support@example.com',
    'core.user_registration' => false,
    'core.maintenance_mode' => false,
    'core.cache.enabled' => true,
    'core.cache.default_ttl' => 900,
    'core.cache.bypass_on_debug' => true,
    'core.locale' => 'en',
    'core.timezone' => 'UTC',
    'core.theme.active' => 'default',
    'core.projects.login_required' => false,
    'core.projects.default_visibility' => 'public',
    'core.projects.default_locale_strategy' => 'inherit',
    'core.installer.completed' => false,
    'core.errors' => [
        'default' => [
            'mode' => 'default',
        ],
        '401' => [
            'mode' => 'default',
        ],
        '403' => [
            'mode' => 'default',
        ],
        '404' => [
            'mode' => 'default',
        ],
        '451' => [
            'mode' => 'default',
        ],
        '500' => [
            'mode' => 'default',
        ],
        '503' => [
            'mode' => 'default',
        ],
    ],
    'core.users.profile_fields' => [
        'website' => [
            'label' => 'Website',
            'type' => 'url',
            'required' => false,
            'public' => true,
        ],
        'company' => [
            'label' => 'Company',
            'type' => 'string',
            'required' => false,
            'public' => true,
            'max_length' => 190,
        ],
        'job_title' => [
            'label' => 'Job title',
            'type' => 'string',
            'required' => false,
            'public' => true,
            'max_length' => 190,
        ],
        'phone' => [
            'label' => 'Phone number',
            'type' => 'tel',
            'required' => false,
            'public' => false,
            'max_length' => 64,
        ],
        'location' => [
            'label' => 'Location',
            'type' => 'string',
            'required' => false,
            'public' => true,
            'max_length' => 190,
        ],
        'bio' => [
            'label' => 'Bio',
            'type' => 'textarea',
            'required' => false,
            'public' => true,
            'max_length' => 600,
        ],
        'avatar_url' => [
            'label' => 'Avatar URL',
            'type' => 'url',
            'required' => false,
            'public' => true,
        ],
        'twitter' => [
            'label' => 'Twitter handle',
            'type' => 'string',
            'required' => false,
            'public' => true,
            'max_length' => 64,
            'prefix' => '@',
        ],
        'linkedin' => [
            'label' => 'LinkedIn URL',
            'type' => 'url',
            'required' => false,
            'public' => true,
        ],
    ],
    'core.users.password_policy' => [
        'min_length' => 12,
        'require_numbers' => true,
        'require_mixed_case' => true,
        'require_special_characters' => false,
        'password_expiry_days' => null,
    ],
];
