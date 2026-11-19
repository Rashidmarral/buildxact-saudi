<?php

namespace App\Services;

use App\Models\Company;
use App\Models\CompanyMailConfig;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Mail;

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
     * QA audit finding: applying tenant A's SMTP config and then calling
     * this again for tenant B (who has none configured) left tenant A's
     * host sitting in the shared runtime mail config — Config::set() only
     * ever wrote the company's values, it never had anything to put back
     * when there was nothing to apply. A single process handling more
     * than one company's mail in sequence (exactly what
     * SendOverdueInvoiceReminders does, looping over every overdue
     * invoice across every company in one run) could silently send
     * company B's reminder through company A's mail account.
     *
     * Captured lazily, once per instance, from whatever the platform
     * mailer config was BEFORE this service ever touched it — i.e. after
     * AppServiceProvider::configureMailDriver() has already applied the
     * platform-wide settings at boot, but before any company-specific
     * override. An instance property rather than a static one
     * deliberately: SendOverdueInvoiceReminders resolves exactly one
     * CompanyMailService for its whole run (via handle()'s method
     * injection) and reuses it across every company in the loop — which
     * is the only place this needs to survive more than one call — while
     * every other caller (a single HTTP request's controller action)
     * gets its own fresh instance per request, same as before.
     */
    private ?array $platformDefaults = null;

    /**
     * Returns true if the company's own SMTP config was applied (so the
     * caller can log/decide accordingly); false if nothing changed — the
     * platform's default mailer config is (now, always) back in effect.
     */
    public function applyFor(Company $company): bool
    {
        $this->capturePlatformDefaultsOnce();

        $config = CompanyMailConfig::withoutGlobalScope('company')
            ->where('company_id', $company->id)
            ->where('is_enabled', true)
            ->first();

        if (! $config || blank($config->smtp_host) || blank($config->from_address)) {
            Config::set($this->platformDefaults);
            Mail::purge('smtp');

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

        // QA follow-up finding: Config::set() alone leaves the Mail
        // manager's already-resolved "smtp" mailer (and the Symfony
        // transport it built) pointed at whichever tenant's host was
        // active the first time anything resolved it this process —
        // Mail::mailer('smtp') only re-reads config for a mailer it
        // hasn't resolved yet. Purging it here forces the next
        // Mail::mailer('smtp') call (and the queued-mail path, which
        // resolves its own mailer inside the worker) to rebuild the
        // transport from the config just written above, every time this
        // method switches which company's mail it's sending.
        Mail::purge('smtp');

        return true;
    }

    private function capturePlatformDefaultsOnce(): void
    {
        if ($this->platformDefaults !== null) {
            return;
        }

        $this->platformDefaults = [
            'mail.default' => Config::get('mail.default'),
            'mail.mailers.smtp.host' => Config::get('mail.mailers.smtp.host'),
            'mail.mailers.smtp.port' => Config::get('mail.mailers.smtp.port'),
            'mail.mailers.smtp.encryption' => Config::get('mail.mailers.smtp.encryption'),
            'mail.mailers.smtp.username' => Config::get('mail.mailers.smtp.username'),
            'mail.mailers.smtp.password' => Config::get('mail.mailers.smtp.password'),
            'mail.from.address' => Config::get('mail.from.address'),
            'mail.from.name' => Config::get('mail.from.name'),
        ];
    }
}
