<?php

namespace App\Support;

/**
 * Single source of truth for Demo Mode's user-facing copy (Module 23) —
 * every guard that refuses an action for a demo company (see
 * PreventDemoDestruction, BillingController::upgrade,
 * ZatcaSyncService::submit/submitCreditNote, ZatcaController) builds its
 * error message through here, so the wording only ever needs to change in
 * one place.
 */
class DemoMode
{
    public static function blockedMessage(string $action): string
    {
        return __('Demo mode: :action is disabled to keep this shared sample account safe.', ['action' => $action]);
    }

    public static function deletingRecords(): string
    {
        return self::blockedMessage(__('Deleting records'));
    }

    public static function realPayments(): string
    {
        return self::blockedMessage(__('Real payment processing'));
    }

    public static function zatcaSubmissions(): string
    {
        return self::blockedMessage(__('ZATCA submissions'));
    }
}
