<?php

namespace App\Support;

use App\Models\User;

/**
 * Single source of truth for the admin-panel sidebar (resources/views/
 * layouts/admin.blade.php). Adding a future Super Admin module means
 * adding one entry here — the layout just loops over items() — instead
 * of editing the layout's markup directly for every new module.
 *
 * 'visible' receives the authenticated User and decides whether the item
 * shows for them; it's a closure rather than a fixed permission key so an
 * item can be gated by AdminPermissions::catalog(), isSuperAdmin(), a
 * feature flag, or any combination, same as routes/web.php already does.
 */
class AdminNav
{
    /**
     * @return array<int, array{route: string, label: string, icon: string, visible: \Closure(User): bool}>
     */
    public static function items(): array
    {
        return [
            [
                'route' => 'admin.dashboard',
                'label' => __('Overview'),
                'icon' => 'dashboard',
                'visible' => fn (User $user) => true,
            ],
            [
                'route' => 'admin.companies.index',
                'label' => __('Companies'),
                'icon' => 'building',
                'visible' => fn (User $user) => $user->hasAdminPermission('companies'),
            ],
            [
                'route' => 'admin.plans.index',
                'label' => __('Plans'),
                'icon' => 'plans',
                'visible' => fn (User $user) => $user->hasAdminPermission('plans'),
            ],
            [
                'route' => 'admin.payments.index',
                'label' => __('Payments'),
                'icon' => 'billing',
                'visible' => fn (User $user) => $user->hasAdminPermission('payments'),
            ],
            [
                'route' => 'admin.activity.index',
                'label' => __('Activity log'),
                'icon' => 'activity',
                'visible' => fn (User $user) => $user->hasAdminPermission('activity'),
            ],
            [
                'route' => 'admin.admins.index',
                'label' => __('Admin Users'),
                'icon' => 'shield',
                'visible' => fn (User $user) => $user->isSuperAdmin(),
            ],
            [
                'route' => 'admin.admin-roles.index',
                'label' => __('Admin Roles'),
                'icon' => 'shield',
                'visible' => fn (User $user) => $user->isSuperAdmin(),
            ],
            [
                'route' => 'admin.certificates.index',
                'label' => __('Certificates'),
                'icon' => 'templates',
                'visible' => fn (User $user) => $user->isSuperAdmin(),
            ],
            [
                'route' => 'admin.currencies.index',
                'label' => __('Currencies'),
                'icon' => 'billing',
                'visible' => fn (User $user) => $user->isSuperAdmin(),
            ],
            [
                'route' => 'admin.settings.edit',
                'label' => __('Platform settings'),
                'icon' => 'settings',
                'visible' => fn (User $user) => $user->isSuperAdmin(),
            ],
        ];
    }

    /**
     * @return array<int, array{route: string, label: string, icon: string}>
     */
    public static function visibleItems(User $user): array
    {
        return array_values(array_filter(
            self::items(),
            fn (array $item) => $item['visible']($user)
        ));
    }
}
