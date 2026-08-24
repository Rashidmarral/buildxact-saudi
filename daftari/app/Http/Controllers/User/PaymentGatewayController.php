<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\PaymentGateway;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

/**
 * Each company configures its OWN gateway credentials here to receive
 * ONLINE PAYMENTS ON ITS OWN INVOICES — this money goes to the company's
 * merchant account, never to Daftari. Only providers the platform admin
 * has enabled (Admin\PaymentGatewaySettingsController) are selectable,
 * since Daftari has to actually support the integration either way.
 */
class PaymentGatewayController extends Controller
{
    public function index()
    {
        $companyId = Auth::user()->company_id;

        $availableProviders = PaymentGateway::whereNull('company_id')->where('is_enabled', true)->pluck('provider')->all();
        $gateways = PaymentGateway::where('company_id', $companyId)->get()->keyBy('provider');

        return view('user.settings.payment-gateways', [
            'availableProviders' => $availableProviders,
            'gateways' => $gateways,
        ]);
    }

    public function update(Request $request, string $provider)
    {
        $companyId = Auth::user()->company_id;
        $platformGateway = PaymentGateway::whereNull('company_id')->where('provider', $provider)->first();

        abort_unless($platformGateway && $platformGateway->is_enabled, 404);

        $data = $request->validate([
            'mode' => ['required', Rule::in(['test', 'live'])],
            'is_enabled' => ['nullable', 'boolean'],
        ] + PaymentGateway::credentialRulesFor($provider));

        $credentials = collect($data)->except(['is_enabled', 'mode'])->all();

        PaymentGateway::updateOrCreate(
            ['company_id' => $companyId, 'provider' => $provider],
            [
                'mode' => $data['mode'],
                'is_enabled' => $request->boolean('is_enabled'),
                'credentials' => $credentials,
            ]
        );

        AuditLog::record('payment_gateway.update', null, __('Updated :provider payment gateway', ['provider' => $provider]));

        return back()->with('status', __('Gateway settings saved.'));
    }
}
