<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\WhatsappConfig;
use App\Services\WhatsAppService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class WhatsappSettingsController extends Controller
{
    public function show()
    {
        $config = WhatsappConfig::first();

        return view('user.settings.whatsapp', ['config' => $config]);
    }

    public function update(Request $request)
    {
        $data = $request->validate([
            'phone_number_id' => ['required', 'string', 'max:255'],
            'access_token' => ['required', 'string', 'max:2000'],
            'template_name' => ['required', 'string', 'max:255'],
            'template_language' => ['required', 'string', 'max:10'],
            'is_enabled' => ['nullable', 'boolean'],
        ]);

        WhatsappConfig::updateOrCreate(
            ['company_id' => Auth::user()->company_id],
            [
                'phone_number_id' => $data['phone_number_id'],
                'access_token' => $data['access_token'],
                'template_name' => $data['template_name'],
                'template_language' => $data['template_language'],
                'is_enabled' => $request->boolean('is_enabled'),
            ]
        );

        AuditLog::record('whatsapp.update', null, __('Updated WhatsApp settings'));

        return back()->with('status', __('WhatsApp settings saved.'));
    }

    public function test(Request $request, WhatsAppService $whatsapp)
    {
        $data = $request->validate(['phone' => ['required', 'string', 'max:30']]);

        $config = WhatsappConfig::first();
        abort_unless($config && $config->is_enabled, 404);

        $result = $whatsapp->sendTemplateMessage($config, $data['phone'], [
            Auth::user()->company->name,
            __('This is a test message from Daftari.'),
        ]);

        if (! $result['success']) {
            return back()->withErrors(['phone' => __('Send failed: :error', ['error' => $result['error']])]);
        }

        return back()->with('status', __('Test message sent.'));
    }
}
