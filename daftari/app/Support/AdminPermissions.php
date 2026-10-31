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
            'reports' => __('Reports'),
            'tickets' => __('Support Tickets'),
            'leads' => __('Sales & CRM'),
            'sales_center' => __('Sales Center'),
            'partners' => __('Partner Program'),
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
            'billing' => ['payments', 'plans', 'coupons', 'reports'],
            // Security audit finding CRIT-04: this used to be self::keys()
            // (a bare, full grant for every key), which — since
            // EnsureAdminPermission didn't distinguish read from write —
            // let a "Read-only auditor" account suspend companies, refund
            // payments, and change plans. The ":view" suffix (see
            // AdminRole::hasPermission()/hasManagePermission()) grants only
            // read access to every section, never a mutating action.
            'read_only' => array_map(fn (string $key) => "{$key}:view", self::keys()),
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
