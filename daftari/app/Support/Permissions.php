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
            'audit' => __('Company Audit'),
            'accounting' => __('Accounting'),
            'branches' => __('Branches'),
            'members_roles' => __('Members & Roles'),
            'settings' => __('Settings'),
            'zatca' => __('ZATCA Integration'),
            'approvals' => __('Approve purchase orders & expenses'),
            'support' => __('Support Tickets'),
            'payroll' => __('Payroll'),
            'pos' => __('Point of Sale'),
            'restaurant' => __('Restaurant Management'),
            'repair_shop' => __('Repair Shop'),
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
            // Security audit finding M-08: 'zatca' isn't just "view
            // compliance status" — it also gates CSR generation, CSID
            // issuance, environment switching, and onboarding reset, any
            // of which can knock out live e-invoicing. A bookkeeping role
            // has no routine need to touch that, same reasoning that
            // already keeps 'settings'/'members_roles' out of this preset.
            'accountant' => ['dashboard', 'clients', 'items', 'invoices', 'quotations', 'expenses', 'purchases', 'cash_banks', 'projects', 'reports', 'audit', 'accounting', 'approvals', 'support'],
            'sales' => ['dashboard', 'clients', 'items', 'invoices', 'quotations', 'salespersons', 'projects', 'reports', 'support'],
            'member' => ['dashboard', 'reports', 'support'],
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
