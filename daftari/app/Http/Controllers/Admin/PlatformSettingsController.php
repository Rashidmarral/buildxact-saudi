<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Setting;
use App\Support\PlatformBranding;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

/**
 * Runtime overrides for platform config that a super admin needs to change
 * without a deploy — trial length, the support contact shown to users,
 * maintenance mode, and Daftari's own public branding/legal identity
 * (shown on the marketing site footer and SaaS receipts). Each falls back
 * to config/daftari.php (or a sane default) until a Setting row exists, so
 * a fresh install behaves exactly as before this page existed.
 */
class PlatformSettingsController extends Controller
{
    public function edit()
    {
        $settings = [
            'trial_days' => Setting::get('trial_days', (string) config('daftari.trial_days')),
            'support_email' => Setting::get('support_email', config('mail.from.address')),
            'maintenance_mode' => Setting::getBool('maintenance_mode'),
            'maintenance_message' => Setting::get('maintenance_message', ''),
        ];

        return view('admin.settings.edit', [
            'settings' => $settings,
            'branding' => PlatformBranding::all(),
        ]);
    }

    public function update(Request $request)
    {
        $data = $request->validate([
            'trial_days' => ['required', 'integer', 'min:1', 'max:365'],
            'support_email' => ['required', 'email', 'max:255'],
            'maintenance_mode' => ['nullable', 'boolean'],
            'maintenance_message' => ['nullable', 'string', 'max:1000'],
        ]);

        Setting::set('trial_days', (string) $data['trial_days']);
        Setting::set('support_email', $data['support_email']);
        Setting::set('maintenance_mode', $request->boolean('maintenance_mode') ? '1' : '0');
        Setting::set('maintenance_message', $data['maintenance_message'] ?? '');

        AuditLog::record('settings.update', null, __('Updated platform settings'));

        return back()->with('status', __('Settings saved.'));
    }

    public function updateBranding(Request $request)
    {
        $data = $request->validate([
            'platform_name' => ['required', 'string', 'max:255'],
            'platform_name_ar' => ['nullable', 'string', 'max:255'],
            'platform_vat_number' => ['nullable', 'string', 'max:20'],
            'platform_cr_number' => ['nullable', 'string', 'max:20'],
            'platform_address' => ['nullable', 'string', 'max:255'],
            'platform_phone' => ['nullable', 'string', 'max:30'],
            'platform_email' => ['nullable', 'email', 'max:255'],
            'logo' => ['nullable', 'image', 'max:2048'],
        ]);

        unset($data['logo']);

        foreach ($data as $key => $value) {
            Setting::set($key, $value ?? '');
        }

        if ($request->hasFile('logo')) {
            $existing = Setting::get('platform_logo_path');
            if ($existing) {
                Storage::disk('public')->delete($existing);
            }
            Setting::set('platform_logo_path', $request->file('logo')->store('platform', 'public'));
        }

        AuditLog::record('settings.update_branding', null, __('Updated platform branding'));

        return back()->with('status', __('Branding saved.'));
    }
}
