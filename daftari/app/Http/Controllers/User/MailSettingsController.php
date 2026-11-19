<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Mail\CompanySmtpTestMail;
use App\Models\AuditLog;
use App\Models\CompanyMailConfig;
use App\Services\CompanyMailService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\Rule;

class MailSettingsController extends Controller
{
    public function show()
    {
        $config = CompanyMailConfig::first();

        return view('user.settings.mail', ['config' => $config]);
    }

    public function update(Request $request)
    {
        $data = $request->validate([
            'is_enabled' => ['nullable', 'boolean'],
            'smtp_host' => ['nullable', 'string', 'max:255', 'required_if:is_enabled,1'],
            'smtp_port' => ['nullable', 'integer', 'min:1', 'max:65535'],
            'smtp_encryption' => ['required', Rule::in(['tls', 'ssl', 'none'])],
            'smtp_username' => ['nullable', 'string', 'max:255'],
            'smtp_password' => ['nullable', 'string', 'max:255'],
            'from_address' => ['nullable', 'email', 'max:255', 'required_if:is_enabled,1'],
            'from_name' => ['nullable', 'string', 'max:255'],
        ]);

        $config = CompanyMailConfig::updateOrCreate(
            ['company_id' => Auth::user()->company_id],
            [
                'is_enabled' => $request->boolean('is_enabled'),
                'smtp_host' => $data['smtp_host'] ?? null,
                'smtp_port' => $data['smtp_port'] ?? 587,
                'smtp_encryption' => $data['smtp_encryption'],
                'smtp_username' => $data['smtp_username'] ?? null,
                'from_address' => $data['from_address'] ?? null,
                'from_name' => $data['from_name'] ?? null,
            ]
        );

        // Never re-displayed, so a blank field means "keep the existing
        // password", not "clear it" — same convention as the platform
        // mail settings page.
        if (filled($data['smtp_password'] ?? null)) {
            $config->update(['smtp_password' => $data['smtp_password']]);
        }

        AuditLog::record('mail.update', null, __('Updated company email settings'));

        return back()->with('status', __('Email settings saved.'));
    }

    /**
     * Sends one real message through whatever's currently saved —
     * the only way to actually confirm SMTP credentials are correct
     * short of waiting for a real invoice to silently fail to send.
     */
    public function test(Request $request, CompanyMailService $mailService)
    {
        $data = $request->validate(['test_email' => ['required', 'email', 'max:255']]);

        $company = Auth::user()->company;
        $config = CompanyMailConfig::first();

        if (! $config || blank($config->smtp_host)) {
            return back()->withErrors(['mail_test' => __('Enter and save an SMTP host before sending a test email.')]);
        }

        // Applies the just-saved config (not a stale request override) —
        // simpler than threading unsaved form values through, and update()
        // just ran immediately before this in the normal "Save, then Send
        // test" flow.
        if (! $mailService->applyFor($company)) {
            return back()->withErrors(['mail_test' => __('These settings are saved but not enabled — turn on "Enabled" and save first.')]);
        }

        try {
            Mail::to($data['test_email'])->send(new CompanySmtpTestMail($company->name));
        } catch (\Throwable $e) {
            AuditLog::record('mail.test_failed', null, __('SMTP test email failed'));

            return back()->withErrors(['mail_test' => __('Failed to send: :message', ['message' => $e->getMessage()])]);
        }

        AuditLog::record('mail.test_sent', null, __('Sent a test email to verify SMTP settings'));

        return back()->with('status', __('Test email sent to :email.', ['email' => $data['test_email']]));
    }
}
