<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\PaymentGateway;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Daftari's OWN gateway credentials, used only to collect SUBSCRIPTION
 * payments from companies (company_id is always null on these rows). Each
 * company configures its own separate credentials, under its own Settings,
 * for collecting payments on ITS invoices — see User\PaymentGatewayController.
 */
class PaymentGatewaySettingsController extends Controller
{
    public function index()
    {
        $gateways = PaymentGateway::whereNull('company_id')->get()->keyBy('provider');

        return view('admin.settings.payment-gateways', ['gateways' => $gateways]);
    }

    public function update(Request $request, string $provider)
    {
        $allProviders = [...PaymentGateway::PROVIDERS, PaymentGateway::BANK_TRANSFER];
        abort_unless(in_array($provider, $allProviders, true), 404);

        $data = $request->validate($this->rulesFor($provider));

        $credentials = collect($data)->except(['is_enabled', 'mode'])->all();

        PaymentGateway::updateOrCreate(
            ['company_id' => null, 'provider' => $provider],
            [
                'mode' => $data['mode'] ?? 'live',
                'is_enabled' => $request->boolean('is_enabled'),
                'credentials' => $credentials,
            ]
        );

        AuditLog::record('payment_gateway.update', null, __('Updated platform :provider gateway settings', ['provider' => $provider]));

        return back()->with('status', __('Gateway settings saved.'));
    }

    private function rulesFor(string $provider): array
    {
        // Bank transfer has no test/live distinction — it's just a bank
        // account.
        $modeRule = $provider === PaymentGateway::BANK_TRANSFER
            ? ['nullable']
            : ['required', Rule::in(['test', 'live'])];

        return [
            'mode' => $modeRule,
            'is_enabled' => ['nullable', 'boolean'],
        ] + PaymentGateway::credentialRulesFor($provider, isPlatform: true);
    }
}
