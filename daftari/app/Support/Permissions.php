<?php

namespace App\Support;

class Permissions
{
    /**
     * Every permission key maps to full access (view + manage) over one
     * module. Kept intentionally coarse (one key per module, not a key per
     * action) so every key here is actually enforced by route middleware —
     * no checkbox in the role editor exists that doesn't gate something real.
     */
    public static function keys(): array
    {
        return array_keys(self::catalog());
    }

    public static function catalog(): array
    {
        return [
            'dashboard' => __('Dashboard'),
            'clients' => __('Clients'),
            'items' => __('Items & Services'),
            'invoices' => __('Invoices'),
            'quotations' => __('Quotations & Proforma Invoices'),
            'expenses' => __('Expenses'),
            'purchases' => __('Purchases (suppliers, bills, purchase orders)'),
            'cash_banks' => __('Cash & Banks'),
            'inventory' => __('Inventory'),
            'salespersons' => __('Salespersons'),
            'projects' => __('Projects'),
            'reports' => __('Reports'),
            'accounting' => __('Accounting'),
            'branches' => __('Branches'),
            'members_roles' => __('Members & Roles'),
            'settings' => __('Settings'),
            'zatca' => __('ZATCA Integration'),
        ];
    }

    public static function isValid(string $key): bool
    {
        return in_array($key, self::keys(), true);
    }

    /**
     * Permission sets for the system roles seeded into every new company.
     * "owner" isn't listed: the account owner bypasses permission checks
     * entirely (see User::hasPermission()).
     */
    public static function systemRolePresets(): array
    {
        return [
            'admin' => self::keys(),
            'accountant' => ['dashboard', 'clients', 'items', 'invoices', 'quotations', 'expenses', 'purchases', 'cash_banks', 'projects', 'reports', 'zatca', 'accounting'],
            'sales' => ['dashboard', 'clients', 'items', 'invoices', 'quotations', 'salespersons', 'projects', 'reports'],
            'member' => ['dashboard', 'reports'],
        ];
    }

    public static function systemRoleLabels(): array
    {
        return [
            'admin' => __('Admin'),
            'accountant' => __('Accountant'),
            'sales' => __('Sales'),
            'member' => __('Member'),
        ];
    }
}
