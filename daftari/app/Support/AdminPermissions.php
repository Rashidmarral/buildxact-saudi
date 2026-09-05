<?php

namespace App\Support;

class AdminPermissions
{
    /**
     * Platform-level permission keys — one per admin route group. Deliberately
     * excludes "admins" (managing other admin accounts) and "settings"
     * (platform-wide config, incl. maintenance mode): those stay super_admin
     * only so a granular admin role can never be used to escalate itself or
     * another account to full platform control.
     */
    public static function keys(): array
    {
        return array_keys(self::catalog());
    }

    public static function catalog(): array
    {
        return [
            'companies' => __('Companies'),
            'plans' => __('Plans'),
            'coupons' => __('Coupons'),
            'payments' => __('Payments'),
            'zatca' => __('ZATCA'),
            'tickets' => __('Support Tickets'),
            'activity' => __('Activity log'),
        ];
    }

    public static function isValid(string $key): bool
    {
        return in_array($key, self::keys(), true);
    }

    public static function systemRolePresets(): array
    {
        return [
            'support' => ['companies', 'zatca', 'tickets', 'activity'],
            'billing' => ['payments', 'plans', 'coupons'],
            'read_only' => self::keys(),
        ];
    }

    public static function systemRoleLabels(): array
    {
        return [
            'support' => __('Support'),
            'billing' => __('Billing'),
            'read_only' => __('Read-only auditor'),
        ];
    }
}
