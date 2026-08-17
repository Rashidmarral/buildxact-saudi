@extends('layouts.app')

@section('title', __('ZATCA Integration'))

@section('content')
@php
    $status = $company->zatca_onboarding_status;
    $steps = ['not_started', 'csr_generated', 'compliance_pending', 'compliance_verified', 'onboarded'];
    $stepIndex = array_search($status, $steps, true);
    $stepIndex = $stepIndex === false ? 0 : $stepIndex;
    $isReady = collect($readiness)->every(fn ($check) => $check['ok']);
@endphp

<div class="flex items-center justify-between mb-6">
    <div>
        <h2 class="text-lg font-semibold text-slate-900">{{ __('ZATCA Phase 2 Compliance') }}</h2>
        <p class="text-sm text-slate-500 mt-1">{{ __('Real-time e-invoicing integration with ZATCA — clearance for B2B, reporting for B2C.') }}</p>
    </div>
    @if ($company->isZatcaOnboarded())
        <span class="inline-flex items-center gap-2 rounded-full bg-emerald-50 text-emerald-700 border border-emerald-200 px-3 py-1.5 text-sm font-semibold">
            ✓ {{ __('Onboarded — :env', ['env' => ucfirst($company->zatca_environment)]) }}
        </span>
    @else
        <span class="inline-flex items-center gap-2 rounded-full bg-amber-50 text-amber-700 border border-amber-200 px-3 py-1.5 text-sm font-semibold">
            {{ __('Not connected yet') }}
        </span>
    @endif
</div>

<div class="grid sm:grid-cols-4 gap-4 mb-6">
    <div class="bg-white rounded-xl border border-slate-100 p-5">
        <p class="text-xs text-slate-400">{{ __('Cleared (B2B)') }}</p>
        <p class="text-2xl font-bold text-slate-900 mt-1">{{ $stats['cleared'] }}</p>
    </div>
    <div class="bg-white rounded-xl border border-slate-100 p-5">
        <p class="text-xs text-slate-400">{{ __('Reported (B2C)') }}</p>
        <p class="text-2xl font-bold text-slate-900 mt-1">{{ $stats['reported'] }}</p>
    </div>
    <div class="bg-white rounded-xl border border-slate-100 p-5">
        <p class="text-xs text-slate-400">{{ __('Failed') }}</p>
        <p class="text-2xl font-bold text-red-600 mt-1">{{ $stats['failed'] }}</p>
    </div>
    <div class="bg-white rounded-xl border border-slate-100 p-5">
        <p class="text-xs text-slate-400">{{ __('Pending sync') }}</p>
        <p class="text-2xl font-bold text-slate-900 mt-1">{{ $pendingCount + $pendingCreditNoteCount }}</p>
    </div>
</div>

<div class="grid lg:grid-cols-3 gap-6">
    <div class="lg:col-span-2 space-y-6">
        @if ($stepIndex < 1)
            <div class="bg-white rounded-xl border {{ $isReady ? 'border-emerald-100' : 'border-amber-200' }} p-6">
                <div class="flex items-center justify-between mb-1">
                    <h3 class="font-semibold text-slate-900">{{ __('Before you start: readiness check') }}</h3>
                    @if ($isReady)
                        <span class="text-xs font-semibold text-emerald-600">✓ {{ __('Ready') }}</span>
                    @else
                        <span class="text-xs font-semibold text-amber-600">{{ __('Action needed') }}</span>
                    @endif
                </div>
                <p class="text-xs text-slate-500 mb-4">{{ __('ZATCA requires this information on every tax invoice. Fix anything marked below in Settings before generating a CSR — an incomplete profile is the most common reason CSR generation or Fatoora Portal submission fails.') }}</p>
                <ul class="space-y-2">
                    @foreach ($readiness as $check)
                        <li class="flex items-start gap-2 text-sm">
                            <span class="mt-0.5 shrink-0 {{ $check['ok'] ? 'text-emerald-600' : 'text-amber-600' }}">
                                @if ($check['ok'])
                                    @include('partials.icon', ['name' => 'check-circle', 'class' => 'h-4 w-4'])
                                @else
                                    @include('partials.icon', ['name' => 'clock', 'class' => 'h-4 w-4'])
                                @endif
                            </span>
                            <span>
                                <span class="font-medium {{ $check['ok'] ? 'text-slate-700' : 'text-slate-800' }}">{{ $check['label'] }}</span>
                                @unless ($check['ok'])
                                    <span class="block text-xs text-slate-500">{{ $check['hint'] }}</span>
                                @endunless
                            </span>
                        </li>
                    @endforeach
                </ul>
                @unless ($isReady)
                    <a href="{{ route('app.settings.index') }}" class="mt-4 inline-block rounded-lg bg-brand-600 px-4 py-2 text-sm font-semibold text-white hover:bg-brand-700">{{ __('Go to Settings') }}</a>
                @endunless
            </div>
        @endif

        <div class="bg-white rounded-xl border border-slate-100 p-6">
            <h3 class="font-semibold text-slate-900 mb-1">{{ __('Onboarding steps') }}</h3>
            <p class="text-xs text-slate-500 mb-4">{{ __('Each ZATCA environment (Developer, Simulation, Production) has its own credentials. Switching environments resets this checklist.') }}</p>

            <div class="space-y-4">
                {{-- Step 1: CSR --}}
                <div class="border border-slate-100 rounded-lg p-4">
                    <div class="flex items-center justify-between">
                        <p class="text-sm font-semibold text-slate-800">1. {{ __('Generate CSR & private key') }}</p>
                        @if ($stepIndex >= 1)<span class="text-xs text-emerald-600 font-semibold">✓ {{ __('Done') }}</span>@endif
                    </div>
                    <p class="text-xs text-slate-500 mt-1">{{ __('Generates an EC key pair and a Certificate Signing Request. Copy the CSR into the ZATCA Fatoora Portal to request an OTP.') }}</p>
                    @if ($stepIndex >= 1)
                        <textarea readonly rows="3" class="mt-3 w-full rounded-lg border border-slate-200 text-xs font-mono text-slate-500 bg-slate-50">{{ $company->zatca_csr }}</textarea>
                    @else
                        <form method="POST" action="{{ route('app.zatca.csr') }}" class="mt-3">
                            @csrf
                            <button type="submit" {{ $isReady ? '' : 'disabled' }} class="rounded-lg bg-brand-600 px-4 py-2 text-sm font-semibold text-white hover:bg-brand-700 disabled:opacity-50 disabled:cursor-not-allowed">{{ __('Generate CSR') }}</button>
                            @unless ($isReady)
                                <span class="text-xs text-amber-600 ms-2">{{ __('Complete the readiness check above first.') }}</span>
                            @endunless
                        </form>
                    @endif
                </div>

                {{-- Step 2: OTP -> compliance CSID --}}
                <div class="border border-slate-100 rounded-lg p-4 {{ $stepIndex < 1 ? 'opacity-50' : '' }}">
                    <div class="flex items-center justify-between">
                        <p class="text-sm font-semibold text-slate-800">2. {{ __('Enter OTP to get compliance CSID') }}</p>
                        @if ($stepIndex >= 2)<span class="text-xs text-emerald-600 font-semibold">✓ {{ __('Done') }}</span>@endif
                    </div>
                    <p class="text-xs text-slate-500 mt-1">{{ __('The Fatoora Portal issues a one-time password after you submit the CSR. Enter it here to exchange the CSR for a compliance CSID.') }}</p>
                    @if ($stepIndex < 2)
                        <form method="POST" action="{{ route('app.zatca.compliance-csid') }}" class="mt-3 flex gap-2">
                            @csrf
                            <input type="text" name="otp" placeholder="{{ __('OTP from Fatoora Portal') }}" required {{ $stepIndex < 1 ? 'disabled' : '' }} class="w-56 rounded-lg border border-slate-200 text-sm focus:border-brand-500 focus:ring-brand-500">
                            <button type="submit" {{ $stepIndex < 1 ? 'disabled' : '' }} class="rounded-lg bg-brand-600 px-4 py-2 text-sm font-semibold text-white hover:bg-brand-700 disabled:opacity-50">{{ __('Submit OTP') }}</button>
                        </form>
                    @endif
                </div>

                {{-- Step 3: compliance checks --}}
                <div class="border border-slate-100 rounded-lg p-4 {{ $stepIndex < 2 ? 'opacity-50' : '' }}">
                    <div class="flex items-center justify-between">
                        <p class="text-sm font-semibold text-slate-800">3. {{ __('Run compliance checks') }}</p>
                        @if ($stepIndex >= 3)<span class="text-xs text-emerald-600 font-semibold">✓ {{ __('Done') }}</span>@endif
                    </div>
                    <p class="text-xs text-slate-500 mt-1">{{ __('Submits your most recent invoice to ZATCA for structural/cryptographic validation, as required before production access.') }}</p>
                    @if ($stepIndex < 3)
                        <form method="POST" action="{{ route('app.zatca.compliance-check') }}" class="mt-3">
                            @csrf
                            <button type="submit" {{ $stepIndex < 2 ? 'disabled' : '' }} class="rounded-lg bg-brand-600 px-4 py-2 text-sm font-semibold text-white hover:bg-brand-700 disabled:opacity-50">{{ __('Run compliance checks') }}</button>
                        </form>
                    @endif
                </div>

                {{-- Step 4: production CSID (production env only) --}}
                @if ($company->zatca_environment === 'production')
                    <div class="border border-slate-100 rounded-lg p-4 {{ $stepIndex < 3 ? 'opacity-50' : '' }}">
                        <div class="flex items-center justify-between">
                            <p class="text-sm font-semibold text-slate-800">4. {{ __('Issue production CSID') }}</p>
                            @if ($status === 'onboarded')<span class="text-xs text-emerald-600 font-semibold">✓ {{ __('Done') }}</span>@endif
                        </div>
                        <p class="text-xs text-slate-500 mt-1">{{ __('Exchanges the verified compliance CSID for the long-lived production certificate used to clear/report live invoices.') }}</p>
                        @if ($status !== 'onboarded')
                            <form method="POST" action="{{ route('app.zatca.production-csid') }}" class="mt-3">
                                @csrf
                                <button type="submit" {{ $stepIndex < 3 ? 'disabled' : '' }} class="rounded-lg bg-brand-600 px-4 py-2 text-sm font-semibold text-white hover:bg-brand-700 disabled:opacity-50">{{ __('Issue production CSID') }}</button>
                            </form>
                        @endif
                    </div>
                @endif
            </div>

            @if ($status !== 'not_started')
                <form method="POST" action="{{ route('app.zatca.reset') }}" class="mt-4" onsubmit="return confirm('{{ __('Reset onboarding and clear stored credentials for this environment?') }}')">
                    @csrf
                    <button type="submit" class="text-xs text-red-600 hover:underline">{{ __('Reset onboarding') }}</button>
                </form>
            @endif
        </div>

        <div class="bg-white rounded-xl border border-slate-100 p-6">
            <div class="flex items-center justify-between mb-4">
                <h3 class="font-semibold text-slate-900">{{ __('Invoice sync log') }}</h3>
                <form method="POST" action="{{ route('app.zatca.sync') }}">
                    @csrf
                    <button type="submit" class="rounded-lg border border-slate-200 px-4 py-2 text-sm font-semibold text-slate-600 hover:border-slate-300">{{ __('Sync now') }} ({{ $pendingCount + $pendingCreditNoteCount }})</button>
                </form>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="text-left text-slate-500 border-b border-slate-100">
                            <th class="py-2">{{ __('Invoice') }}</th>
                            <th class="py-2">{{ __('Type') }}</th>
                            <th class="py-2">{{ __('Environment') }}</th>
                            <th class="py-2">{{ __('Status') }}</th>
                            <th class="py-2">{{ __('Submitted') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($logs as $log)
                            <tr class="border-b border-slate-50 last:border-0">
                                <td class="py-2">{{ $log->invoice?->invoice_number }}</td>
                                <td class="py-2">{{ $log->invoice_type === 'b2b' ? __('B2B') : __('B2C') }}</td>
                                <td class="py-2">{{ ucfirst($log->environment) }}</td>
                                <td class="py-2">
                                    @php
                                        $badge = match ($log->status) {
                                            'cleared', 'reported' => 'bg-emerald-50 text-emerald-700',
                                            'failed' => 'bg-red-50 text-red-700',
                                            default => 'bg-slate-100 text-slate-600',
                                        };
                                    @endphp
                                    <span class="inline-block rounded-full px-2 py-0.5 text-xs font-semibold {{ $badge }}">{{ ucfirst($log->status) }}</span>
                                    @if ($log->status === 'failed' && $log->error_message)
                                        <p class="mt-1 max-w-md text-xs text-red-600 break-words">{{ $log->error_message }}</p>
                                    @endif
                                </td>
                                <td class="py-2 text-slate-500">{{ $log->submitted_at?->format('Y-m-d H:i') }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="py-8 text-center text-slate-400">{{ __('No sync activity yet.') }}</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="mt-4">{{ $logs->links() }}</div>
        </div>

        <div class="bg-white rounded-xl border border-slate-100 p-6">
            <h3 class="font-semibold text-slate-900 mb-4">{{ __('Credit note sync log') }}</h3>
            <p class="text-xs text-slate-500 mb-4">{{ __('Credit notes are submitted through the same clearance/reporting flow as invoices (InvoiceTypeCode 381), cryptographically linked to the invoice they correct.') }}</p>
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="text-left text-slate-500 border-b border-slate-100">
                            <th class="py-2">{{ __('Credit note') }}</th>
                            <th class="py-2">{{ __('Type') }}</th>
                            <th class="py-2">{{ __('Environment') }}</th>
                            <th class="py-2">{{ __('Status') }}</th>
                            <th class="py-2">{{ __('Submitted') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($creditNoteLogs as $log)
                            <tr class="border-b border-slate-50 last:border-0">
                                <td class="py-2">{{ $log->creditNote?->credit_note_number }}</td>
                                <td class="py-2">{{ $log->invoice_type === 'b2b' ? __('B2B') : __('B2C') }}</td>
                                <td class="py-2">{{ ucfirst($log->environment) }}</td>
                                <td class="py-2">
                                    @php
                                        $badge = match ($log->status) {
                                            'cleared', 'reported' => 'bg-emerald-50 text-emerald-700',
                                            'failed' => 'bg-red-50 text-red-700',
                                            default => 'bg-slate-100 text-slate-600',
                                        };
                                    @endphp
                                    <span class="inline-block rounded-full px-2 py-0.5 text-xs font-semibold {{ $badge }}">{{ ucfirst($log->status) }}</span>
                                    @if ($log->status === 'failed' && $log->error_message)
                                        <p class="mt-1 max-w-md text-xs text-red-600 break-words">{{ $log->error_message }}</p>
                                    @endif
                                </td>
                                <td class="py-2 text-slate-500">{{ $log->submitted_at?->format('Y-m-d H:i') }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="py-8 text-center text-slate-400">{{ __('No sync activity yet.') }}</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="mt-4">{{ $creditNoteLogs->links() }}</div>
        </div>
    </div>

    <div class="space-y-6">
        <div class="bg-white rounded-xl border border-slate-100 p-6">
            <h3 class="font-semibold text-slate-900 mb-4">{{ __('Sync settings') }}</h3>
            <form method="POST" action="{{ route('app.zatca.settings.update') }}" class="space-y-4">
                @csrf
                @method('PUT')
                <div>
                    <label class="block text-xs font-medium text-slate-500">{{ __('Environment') }}</label>
                    <select name="zatca_environment" class="mt-1 w-full rounded-lg border border-slate-200 text-sm focus:border-brand-500 focus:ring-brand-500">
                        <option value="developer" @selected($company->zatca_environment === 'developer')>{{ __('Developer (sandbox)') }}</option>
                        <option value="simulation" @selected($company->zatca_environment === 'simulation')>{{ __('Simulation') }}</option>
                        <option value="production" @selected($company->zatca_environment === 'production')>{{ __('Production (live)') }}</option>
                    </select>
                    <p class="mt-1 text-xs text-slate-400">{{ __('Start on Developer or Simulation. Only switch to Production once compliance checks pass consistently.') }}</p>
                </div>
                <div>
                    <label class="block text-xs font-medium text-slate-500">{{ __('Sync frequency') }}</label>
                    <select name="zatca_sync_frequency" class="mt-1 w-full rounded-lg border border-slate-200 text-sm focus:border-brand-500 focus:ring-brand-500">
                        <option value="instant" @selected($company->zatca_sync_frequency === 'instant')>{{ __('Instant (on issue)') }}</option>
                        <option value="hourly" @selected($company->zatca_sync_frequency === 'hourly')>{{ __('Hourly') }}</option>
                        <option value="daily" @selected($company->zatca_sync_frequency === 'daily')>{{ __('Daily') }}</option>
                        <option value="weekly" @selected($company->zatca_sync_frequency === 'weekly')>{{ __('Weekly') }}</option>
                        <option value="manual" @selected($company->zatca_sync_frequency === 'manual')>{{ __('Manual only') }}</option>
                    </select>
                </div>
                <div class="space-y-2">
                    <label class="flex items-center gap-2 text-sm text-slate-700">
                        <input type="checkbox" name="zatca_sync_b2b" value="1" @checked($company->zatca_sync_b2b) class="rounded border-slate-300 text-brand-600 focus:ring-brand-500">
                        {{ __('Sync B2B (standard, clearance) invoices') }}
                    </label>
                    <label class="flex items-center gap-2 text-sm text-slate-700">
                        <input type="checkbox" name="zatca_sync_b2c" value="1" @checked($company->zatca_sync_b2c) class="rounded border-slate-300 text-brand-600 focus:ring-brand-500">
                        {{ __('Sync B2C (simplified, reporting) invoices') }}
                    </label>
                </div>
                <button type="submit" class="w-full rounded-lg bg-brand-600 px-4 py-2 text-sm font-semibold text-white hover:bg-brand-700">{{ __('Save sync settings') }}</button>
            </form>
        </div>

        <div class="bg-slate-900 text-slate-200 rounded-xl p-6 text-xs leading-relaxed">
            <h4 class="font-semibold text-white mb-2">{{ __('How this works') }}</h4>
            <ul class="space-y-2 list-disc ps-4">
                <li>{{ __('Standard (B2B) invoices are sent for clearance — ZATCA validates and cryptographically stamps them before delivery.') }}</li>
                <li>{{ __('Simplified (B2C) invoices are reported to ZATCA after delivery to the buyer.') }}</li>
                <li>{{ __('Every invoice hash is chained to the previous one, matching the sequence integrity ZATCA requires.') }}</li>
                <li>{{ __('Production mode only becomes active once ZATCA actually issues a production CSID — Daftari never fabricates a connected status.') }}</li>
            </ul>
        </div>
    </div>
</div>
@endsection
