<?php

namespace App\Services;

use App\Models\Company;
use App\Models\CompanyMailConfig;
use Illuminate\Support\Facades\Config;

/**
 * Swaps Laravel's runtime mail config to a company's own SMTP account
 * (see CompanyMailConfig) right before a "this company is emailing its
 * own customer" document goes out — mirrors exactly how
 * Admin\PlatformSettingsController::testMail() swaps config for the
 * platform-wide account, just scoped to one tenant and read from the
 * database instead of Setting::get().
 *
 * Deliberately NOT applied to every outbound email in the app — team
 * invites, the client portal's magic-login link, and Daftari's own
 * subscription billing/receipt stay on the platform mailer regardless of
 * whether a company has its own SMTP configured, since those are
 * platform-to-user communications (an auth-critical or billing email
 * silently failing because a tenant's own SMTP is misconfigured would be
 * a real problem) rather than the company talking to its own client.
 */
class CompanyMailService
{
    /**
     * Returns true if the company's own SMTP config was applied (so the
     * caller can log/decide accordingly); false if nothing changed and
     * the platform's default mailer config is still in effect.
     */
    public function applyFor(Company $company): bool
    {
        $config = CompanyMailConfig::withoutGlobalScope('company')
            ->where('company_id', $company->id)
            ->where('is_enabled', true)
            ->first();

        if (! $config || blank($config->smtp_host) || blank($config->from_address)) {
            return false;
        }

        Config::set([
            'mail.default' => 'smtp',
            'mail.mailers.smtp.host' => $config->smtp_host,
            'mail.mailers.smtp.port' => $config->smtp_port,
            'mail.mailers.smtp.encryption' => $config->smtp_encryption === 'none' ? null : $config->smtp_encryption,
            'mail.mailers.smtp.username' => $config->smtp_username,
            'mail.mailers.smtp.password' => $config->smtp_password,
            'mail.from.address' => $config->from_address,
            'mail.from.name' => $config->from_name ?: $company->name,
        ]);

        return true;
    }
}
