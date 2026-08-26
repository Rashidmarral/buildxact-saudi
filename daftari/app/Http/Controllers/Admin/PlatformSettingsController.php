<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Plan;
use App\Models\Setting;
use App\Support\PlatformBranding;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

/**
 * Runtime overrides for platform config that a super admin needs to change
 * without a deploy — organized into the same 7 sections as the tabbed
 * settings view (General, Platform Identity, Branding, Signup, Maintenance,
 * Storage, System). Every key falls back to config/daftari.php (or a sane
 * default) until a Setting row exists, so a fresh install behaves exactly
 * as before this page existed. Secrets (S3 key/secret) are never
 * re-rendered into the page — see Setting::isConfigured().
 */
class PlatformSettingsController extends Controller
{
    public const DATE_FORMATS = ['d/m/Y', 'm/d/Y', 'Y-m-d', 'd-m-Y'];

    public const TIME_FORMATS = ['24h', '12h'];

    public const COUNTRIES = [
        'SA' => 'Saudi Arabia', 'AE' => 'United Arab Emirates', 'KW' => 'Kuwait', 'QA' => 'Qatar',
        'BH' => 'Bahrain', 'OM' => 'Oman', 'EG' => 'Egypt', 'JO' => 'Jordan', 'LB' => 'Lebanon',
        'IQ' => 'Iraq', 'YE' => 'Yemen', 'US' => 'United States', 'GB' => 'United Kingdom',
        'DE' => 'Germany', 'FR' => 'France', 'IN' => 'India', 'PK' => 'Pakistan',
    ];

    public const CURRENCIES = ['SAR', 'USD', 'EUR', 'GBP', 'AED', 'KWD', 'QAR', 'BHD', 'OMR', 'EGP'];

    public const LANGUAGES = ['en' => 'English', 'ar' => 'العربية'];

    public function edit()
    {
        $settings = [
            // General
            'general_platform_name' => Setting::get('general_platform_name', config('app.name')),
            'general_platform_url' => Setting::get('general_platform_url', config('app.url')),
            'general_default_country' => Setting::get('general_default_country', 'SA'),
            'general_default_timezone' => Setting::get('general_default_timezone', config('app.timezone')),
            'general_default_language' => Setting::get('general_default_language', config('app.locale')),
            'general_default_currency' => Setting::get('general_default_currency', config('daftari.default_currency')),
            'general_date_format' => Setting::get('general_date_format', 'd/m/Y'),
            'general_time_format' => Setting::get('general_time_format', '24h'),
            'general_fiscal_year_start' => (int) Setting::get('general_fiscal_year_start', 1),
            'general_allow_registrations' => Setting::getBool('general_allow_registrations', true),
            'general_allow_demo_accounts' => Setting::getBool('general_allow_demo_accounts', false),

            // Platform Identity
            'platform_name' => Setting::get('platform_name', config('daftari.billing.company_name')),
            'platform_name_ar' => Setting::get('platform_name_ar', ''),
            'platform_vat_number' => Setting::get('platform_vat_number', config('daftari.billing.vat_number')),
            'platform_cr_number' => Setting::get('platform_cr_number', config('daftari.billing.cr_number')),
            'platform_address' => Setting::get('platform_address', config('daftari.billing.address')),
            'platform_phone' => Setting::get('platform_phone', ''),
            'platform_email' => Setting::get('platform_email', config('daftari.billing.email')),
            'platform_website' => Setting::get('platform_website', ''),

            // Signup
            'trial_days' => Setting::get('trial_days', (string) config('daftari.trial_days')),
            'signup_require_email_verification' => Setting::getBool('signup_require_email_verification', true),
            'signup_require_phone_verification' => Setting::getBool('signup_require_phone_verification', false),
            'signup_default_plan_id' => Setting::get('signup_default_plan_id', ''),
            'support_email' => Setting::get('support_email', config('mail.from.address')),

            // Maintenance
            'maintenance_mode' => Setting::getBool('maintenance_mode'),
            'maintenance_message' => Setting::get('maintenance_message', ''),
            'maintenance_scheduled_start' => Setting::get('maintenance_scheduled_start', ''),
            'maintenance_scheduled_end' => Setting::get('maintenance_scheduled_end', ''),
            'maintenance_allow_super_admin' => Setting::getBool('maintenance_allow_super_admin', true),

            // Storage
            'storage_driver' => Setting::get('storage_driver', 'local'),
            'storage_s3_region' => Setting::get('storage_s3_region', ''),
            'storage_s3_bucket' => Setting::get('storage_s3_bucket', ''),
            'storage_s3_endpoint' => Setting::get('storage_s3_endpoint', ''),
            'storage_s3_url' => Setting::get('storage_s3_url', ''),
            'storage_s3_key_configured' => Setting::isConfigured('storage_s3_key'),
            'storage_s3_secret_configured' => Setting::isConfigured('storage_s3_secret'),
            'storage_max_upload_size_mb' => (int) Setting::get('storage_max_upload_size_mb', 10),
        ];

        return view('admin.settings.edit', [
            'settings' => $settings,
            'branding' => PlatformBranding::all(),
            'plans' => Plan::orderBy('sort_order')->get(),
            'dateFormats' => self::DATE_FORMATS,
            'timeFormats' => self::TIME_FORMATS,
            'countries' => self::COUNTRIES,
            'currencies' => self::CURRENCIES,
            'languages' => self::LANGUAGES,
            'timezones' => \DateTimeZone::listIdentifiers(),
            'appVersion' => config('daftari.version'),
        ]);
    }

    public function updateGeneral(Request $request)
    {
        $data = $request->validate([
            'general_platform_name' => ['required', 'string', 'max:255'],
            'general_platform_url' => ['required', 'url', 'max:255'],
            'general_default_country' => ['required', 'string', Rule::in(array_keys(self::COUNTRIES))],
            'general_default_timezone' => ['required', 'string', Rule::in(\DateTimeZone::listIdentifiers())],
            'general_default_language' => ['required', 'string', Rule::in(array_keys(self::LANGUAGES))],
            'general_default_currency' => ['required', 'string', Rule::in(self::CURRENCIES)],
            'general_date_format' => ['required', 'string', Rule::in(self::DATE_FORMATS)],
            'general_time_format' => ['required', 'string', Rule::in(self::TIME_FORMATS)],
            'general_fiscal_year_start' => ['required', 'integer', 'min:1', 'max:12'],
            'general_allow_registrations' => ['nullable', 'boolean'],
            'general_allow_demo_accounts' => ['nullable', 'boolean'],
        ]);

        foreach (['general_platform_name', 'general_platform_url', 'general_default_country', 'general_default_timezone', 'general_default_language', 'general_default_currency', 'general_date_format', 'general_time_format', 'general_fiscal_year_start'] as $key) {
            Setting::set($key, (string) $data[$key]);
        }

        Setting::set('general_allow_registrations', $request->boolean('general_allow_registrations') ? '1' : '0');
        Setting::set('general_allow_demo_accounts', $request->boolean('general_allow_demo_accounts') ? '1' : '0');

        AuditLog::record('settings.update_general', null, __('Updated general platform settings'));

        return back()->with('status', __('General settings saved.'));
    }

    public function updateIdentity(Request $request)
    {
        $maxKb = $this->maxUploadKb();

        $data = $request->validate([
            'platform_name' => ['required', 'string', 'max:255'],
            'platform_name_ar' => ['nullable', 'string', 'max:255'],
            'platform_vat_number' => ['nullable', 'string', 'max:20'],
            'platform_cr_number' => ['nullable', 'string', 'max:20'],
            'platform_address' => ['nullable', 'string', 'max:255'],
            'platform_phone' => ['nullable', 'string', 'max:30'],
            'platform_email' => ['nullable', 'email', 'max:255'],
            'platform_website' => ['nullable', 'url', 'max:255'],
            'logo' => ['nullable', 'image', 'max:'.$maxKb],
            'favicon' => ['nullable', 'image', 'mimes:png,ico,jpg,jpeg,webp', 'max:'.$maxKb],
        ]);

        foreach (['platform_name', 'platform_name_ar', 'platform_vat_number', 'platform_cr_number', 'platform_address', 'platform_phone', 'platform_email', 'platform_website'] as $key) {
            Setting::set($key, $data[$key] ?? '');
        }

        $this->replaceUpload($request, 'logo', 'platform_logo_path', 'platform');
        $this->replaceUpload($request, 'favicon', 'platform_favicon_path', 'platform');

        AuditLog::record('settings.update_identity', null, __('Updated platform identity settings'));

        return back()->with('status', __('Platform identity saved.'));
    }

    public function updateBranding(Request $request)
    {
        $maxKb = $this->maxUploadKb();

        $data = $request->validate([
            'branding_primary_color' => ['nullable', 'string', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'branding_secondary_color' => ['nullable', 'string', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'branding_sidebar_color' => ['nullable', 'string', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'login_logo' => ['nullable', 'image', 'max:'.$maxKb],
            'pdf_logo' => ['nullable', 'image', 'max:'.$maxKb],
            'email_logo' => ['nullable', 'image', 'max:'.$maxKb],
            'favicon' => ['nullable', 'image', 'mimes:png,ico,jpg,jpeg,webp', 'max:'.$maxKb],
        ]);

        foreach (['branding_primary_color', 'branding_secondary_color', 'branding_sidebar_color'] as $key) {
            Setting::set($key, $data[$key] ?? '');
        }

        $this->replaceUpload($request, 'login_logo', 'branding_login_logo_path', 'branding');
        $this->replaceUpload($request, 'pdf_logo', 'branding_pdf_logo_path', 'branding');
        $this->replaceUpload($request, 'email_logo', 'branding_email_logo_path', 'branding');
        $this->replaceUpload($request, 'favicon', 'platform_favicon_path', 'platform');

        AuditLog::record('settings.update_branding', null, __('Updated platform branding'));

        return back()->with('status', __('Branding saved.'));
    }

    public function updateSignup(Request $request)
    {
        $data = $request->validate([
            'trial_days' => ['required', 'integer', 'min:1', 'max:365'],
            'general_allow_registrations' => ['nullable', 'boolean'],
            'signup_require_email_verification' => ['nullable', 'boolean'],
            'signup_require_phone_verification' => ['nullable', 'boolean'],
            'signup_default_plan_id' => ['nullable', 'exists:plans,id'],
            'support_email' => ['required', 'email', 'max:255'],
        ]);

        Setting::set('trial_days', (string) $data['trial_days']);
        Setting::set('support_email', $data['support_email']);
        Setting::set('signup_default_plan_id', $data['signup_default_plan_id'] ?? '');
        Setting::set('general_allow_registrations', $request->boolean('general_allow_registrations') ? '1' : '0');
        Setting::set('signup_require_email_verification', $request->boolean('signup_require_email_verification') ? '1' : '0');
        Setting::set('signup_require_phone_verification', $request->boolean('signup_require_phone_verification') ? '1' : '0');

        AuditLog::record('settings.update_signup', null, __('Updated signup settings'));

        return back()->with('status', __('Signup settings saved.'));
    }

    public function updateMaintenance(Request $request)
    {
        $data = $request->validate([
            'maintenance_mode' => ['nullable', 'boolean'],
            'maintenance_message' => ['nullable', 'string', 'max:1000'],
            'maintenance_scheduled_start' => ['nullable', 'date'],
            'maintenance_scheduled_end' => ['nullable', 'date', 'after:maintenance_scheduled_start'],
            'maintenance_allow_super_admin' => ['nullable', 'boolean'],
        ]);

        Setting::set('maintenance_mode', $request->boolean('maintenance_mode') ? '1' : '0');
        Setting::set('maintenance_message', $data['maintenance_message'] ?? '');
        Setting::set('maintenance_scheduled_start', $data['maintenance_scheduled_start'] ?? '');
        Setting::set('maintenance_scheduled_end', $data['maintenance_scheduled_end'] ?? '');
        Setting::set('maintenance_allow_super_admin', $request->boolean('maintenance_allow_super_admin') ? '1' : '0');

        AuditLog::record('settings.update_maintenance', null, __('Updated maintenance settings'));

        return back()->with('status', __('Maintenance settings saved.'));
    }

    public function updateStorage(Request $request)
    {
        $data = $request->validate([
            'storage_driver' => ['required', Rule::in(['local', 's3'])],
            'storage_s3_key' => ['nullable', 'string', 'max:255'],
            'storage_s3_secret' => ['nullable', 'string', 'max:255'],
            'storage_s3_region' => ['nullable', 'string', 'max:100', 'required_if:storage_driver,s3'],
            'storage_s3_bucket' => ['nullable', 'string', 'max:255', 'required_if:storage_driver,s3'],
            'storage_s3_endpoint' => ['nullable', 'url', 'max:255'],
            'storage_s3_url' => ['nullable', 'url', 'max:255'],
            'storage_max_upload_size_mb' => ['required', 'integer', 'min:1', 'max:100'],
        ]);

        Setting::set('storage_driver', $data['storage_driver']);
        Setting::set('storage_s3_region', $data['storage_s3_region'] ?? '');
        Setting::set('storage_s3_bucket', $data['storage_s3_bucket'] ?? '');
        Setting::set('storage_s3_endpoint', $data['storage_s3_endpoint'] ?? '');
        Setting::set('storage_s3_url', $data['storage_s3_url'] ?? '');
        Setting::set('storage_max_upload_size_mb', (string) $data['storage_max_upload_size_mb']);

        // Only overwrite a stored secret when the admin actually typed a new
        // one — never re-displayed, so a blank field means "keep the
        // existing value", not "clear it".
        if (filled($data['storage_s3_key'] ?? null)) {
            Setting::set('storage_s3_key', $data['storage_s3_key'], encrypted: true);
        }

        if (filled($data['storage_s3_secret'] ?? null)) {
            Setting::set('storage_s3_secret', $data['storage_s3_secret'], encrypted: true);
        }

        AuditLog::record('settings.update_storage', null, __('Updated storage settings'));

        return back()->with('status', __('Storage settings saved.'));
    }

    public function runSystemAction(Request $request, string $action)
    {
        $commands = [
            'cache-clear' => 'cache:clear',
            'config-cache' => 'config:cache',
            'route-cache' => 'route:cache',
            'view-cache' => 'view:cache',
        ];

        abort_unless(array_key_exists($action, $commands), 404);

        try {
            Artisan::call($commands[$action]);
            $output = trim(Artisan::output());
        } catch (\Throwable $e) {
            AuditLog::record('settings.system_action_failed', null, __('System action failed: :action', ['action' => $action]));

            return back()->withErrors(['system' => __('Action failed: :message', ['message' => $e->getMessage()])]);
        }

        AuditLog::record('settings.system_action', null, __('Ran system action: :action', ['action' => $action]));

        return back()->with('status', $output !== '' ? $output : __('Action completed: :action', ['action' => $action]));
    }

    private function maxUploadKb(): int
    {
        return ((int) Setting::get('storage_max_upload_size_mb', 10)) * 1024;
    }

    private function replaceUpload(Request $request, string $field, string $settingKey, string $folder): void
    {
        if (! $request->hasFile($field)) {
            return;
        }

        $existing = Setting::get($settingKey);
        if ($existing) {
            Storage::disk('public')->delete($existing);
        }

        Setting::set($settingKey, $request->file($field)->store($folder, 'public'));
    }
}
