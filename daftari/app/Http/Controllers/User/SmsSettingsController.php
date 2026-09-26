<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\SmsConfig;
use App\Services\SmsService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class SmsSettingsController extends Controller
{
    public function show()
    {
        $config = SmsConfig::first();

        return view('user.settings.sms', ['config' => $config]);
    }

    public function update(Request $request)
    {
        $data = $request->validate([
            'app_sid' => ['required', 'string', 'max:2000'],
            'sender_id' => ['required', 'string', 'max:20'],
            'is_enabled' => ['nullable', 'boolean'],
        ]);

        SmsConfig::updateOrCreate(
            ['company_id' => Auth::user()->company_id],
            [
                'app_sid' => $data['app_sid'],
                'sender_id' => $data['sender_id'],
                'is_enabled' => $request->boolean('is_enabled'),
            ]
        );

        AuditLog::record('sms.update', null, __('Updated SMS settings'));

        return back()->with('status', __('SMS settings saved.'));
    }

    public function test(Request $request, SmsService $sms)
    {
        $data = $request->validate(['phone' => ['required', 'string', 'max:30']]);

        $config = SmsConfig::first();
        abort_unless($config && $config->is_enabled, 404);

        $result = $sms->send($config, $data['phone'], __('This is a test message from Daftari.'));

        if (! $result['success']) {
            return back()->withErrors(['phone' => __('Send failed: :error', ['error' => $result['error']])]);
        }

        return back()->with('status', __('Test message sent.'));
    }
}
