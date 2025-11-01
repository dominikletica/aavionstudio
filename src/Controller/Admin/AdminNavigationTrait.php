<?php

declare(strict_types=1);

namespace App\Controller\Admin;

trait AdminNavigationTrait
{
    private function buildPrimaryMenu(string $section): array
    {
        return [
            'items' => [
                [
                    'label' => 'Users',
                    'url' => $this->generateUrl('admin_users_index'),
                    'icon' => 'users',
                    'active' => $section === 'users',
                ],
                [
                    'label' => 'System',
                    'url' => $this->generateUrl('admin_assets_overview'),
                    'icon' => 'settings',
                    'active' => $section === 'system',
                ],
                [
                    'label' => 'Security',
                    'url' => $this->generateUrl('admin_security_audit'),
                    'icon' => 'shield-lock',
                    'active' => $section === 'security',
                ],
            ],
        ];
    }

    private function buildSidebarMenu(string $section, ?string $page): array
    {
        return match ($section) {
            'users' => [
                'sections' => [[
                    'label' => 'Users',
                    'items' => [
                        [
                            'label' => 'All users',
                            'url' => $this->generateUrl('admin_users_index'),
                            'icon' => 'user',
                            'active' => $page === 'index',
                        ],
                        [
                            'label' => 'Invitations',
                            'url' => $this->generateUrl('admin_user_invitations'),
                            'icon' => 'mail-bolt',
                            'active' => $page === 'invitations',
                        ],
                    ],
                ]],
            ],
            'system' => [
                'sections' => [[
                    'label' => 'System',
                    'items' => [
                        [
                            'label' => 'Asset pipeline',
                            'url' => $this->generateUrl('admin_assets_overview'),
                            'icon' => 'settings-cog',
                            'active' => $page === 'assets',
                        ],
                    ],
                ]],
            ],
            'security' => [
                'sections' => [[
                    'label' => 'Security',
                    'items' => [
                        [
                            'label' => 'Audit log',
                            'url' => $this->generateUrl('admin_security_audit'),
                            'icon' => 'lock',
                            'active' => $page === 'audit',
                        ],
                    ],
                ]],
            ],
            default => ['sections' => []],
        };
    }

    private function adminNavigation(string $section, ?string $page = null): array
    {
        return [
            'primary_menu' => $this->buildPrimaryMenu($section),
            'sidebar_menu' => $this->buildSidebarMenu($section, $page),
        ];
    }
}
