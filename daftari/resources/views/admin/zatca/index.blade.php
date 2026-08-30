@extends('layouts.admin')

@section('title', __('ZATCA'))

@section('content')
<div class="grid grid-cols-2 gap-4 sm:gap-5 lg:grid-cols-4">
    @foreach ([
        ['label' => __('ZATCA-enabled companies'), 'value' => number_format($stats['total_enabled']), 'icon' => 'zatca', 'accent' => 'from-brand-500 to-emerald-500'],
        ['label' => __('Connected'), 'value' => number_format($stats['connected']), 'icon' => 'check-circle', 'accent' => 'from-emerald-500 to-teal-500'],
        ['label' => __('Pending onboarding'), 'value' => number_format($stats['pending_onboarding']), 'icon' => 'clock', 'accent' => 'from-amber-500 to-orange-500'],
        ['label' => __('Failed connections'), 'value' => number_format($stats['failed_connections']), 'icon' => 'trend-down', 'accent' => 'from-red-500 to-rose-500'],
        ['label' => __('Total submitted'), 'value' => number_format($stats['total_submitted']), 'icon' => 'clipboard', 'accent' => 'from-sky-500 to-blue-500'],
        ['label' => __('Accepted'), 'value' => number_format($stats['accepted']), 'icon' => 'check-circle', 'accent' => 'from-teal-500 to-cyan-500'],
        ['label' => __('Rejected by ZATCA'), 'value' => number_format($stats['rejected']), 'icon' => 'trend-down', 'accent' => 'from-orange-500 to-red-500'],
        ['label' => __('Failed submissions'), 'value' => number_format($stats['failed_submissions']), 'icon' => 'trend-down', 'accent' => 'from-rose-500 to-pink-500'],
    ] as $card)
        <div class="card-hover rounded-2xl border border-slate-100 bg-white p-5 shadow-card">
            <div class="flex items-center justify-between">
                <span class="text-xs font-medium text-slate-500">{{ $card['label'] }}</span>
                <span class="inline-flex h-8 w-8 items-center justify-center rounded-lg bg-gradient-to-br {{ $card['accent'] }} text-white">
                    @include('partials.icon', ['name' => $card['icon'], 'class' => 'h-4 w-4'])
                </span>
            </div>
            <div class="mt-3 text-2xl font-bold text-slate-900">{{ $card['value'] }}</div>
        </div>
    @endforeach
</div>

<div class="mt-6 flex items-center justify-between">
    <h2 class="text-base font-semibold text-slate-900">{{ __('Company ZATCA status') }}</h2>
    <a href="{{ route('admin.zatca.logs') }}" class="text-sm font-semibold text-brand-700 hover:underline">{{ __('View integration logs') }} →</a>
</div>

<form method="GET" class="mt-3 mb-6 bg-white rounded-xl border border-slate-100 p-4 space-y-3">
    <div class="flex flex-wrap items-end gap-3">
        <div>
            <label class="block text-xs font-medium text-slate-500 mb-1">{{ __('Company or VAT number') }}</label>
            <input type="text" name="q" value="{{ request('q') }}" class="rounded-lg border border-slate-200 text-sm">
        </div>
        <div>
            <label class="block text-xs font-medium text-slate-500 mb-1">{{ __('Environment') }}</label>
            <select name="environment" class="rounded-lg border border-slate-200 text-sm">
                <option value="">{{ __('All environments') }}</option>
                @foreach (array_keys(\App\Services\Zatca\ZatcaApiClient::BASE_URLS) as $env)
                    <option value="{{ $env }}" @selected(request('environment') === $env)>{{ ucfirst($env) }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label class="block text-xs font-medium text-slate-500 mb-1">{{ __('Integration status') }}</label>
            <select name="status" class="rounded-lg border border-slate-200 text-sm">
                <option value="">{{ __('All') }}</option>
                <option value="connected" @selected(request('status') === 'connected')>{{ __('Connected') }}</option>
                <option value="pending" @selected(request('status') === 'pending')>{{ __('Pending') }}</option>
                <option value="failed" @selected(request('status') === 'failed')>{{ __('Failed') }}</option>
                <option value="not_connected" @selected(request('status') === 'not_connected')>{{ __('Not connected') }}</option>
            </select>
        </div>
        <button type="submit" class="rounded-lg bg-brand-600 px-4 py-2 text-sm font-semibold text-white hover:bg-brand-700">{{ __('Filter') }}</button>
        @if (request()->anyFilled(['q', 'environment', 'status']))
            <a href="{{ route('admin.zatca.index') }}" class="text-sm font-semibold text-slate-500 hover:underline">{{ __('Clear') }}</a>
        @endif
    </div>
</form>

<div class="bg-white rounded-xl border border-slate-100 overflow-x-auto">
    <table class="w-full text-sm">
        <thead>
            <tr class="text-left text-slate-500 border-b border-slate-100 whitespace-nowrap">
                <th class="px-6 py-3 font-medium">{{ __('Company') }}</th>
                <th class="px-4 py-3 font-medium">{{ __('VAT number') }}</th>
                <th class="px-4 py-3 font-medium">{{ __('Environment') }}</th>
                <th class="px-4 py-3 font-medium">{{ __('Onboarding status') }}</th>
                <th class="px-4 py-3 font-medium">{{ __('Certificate status') }}</th>
                <th class="px-4 py-3 font-medium">{{ __('Integration status') }}</th>
                <th class="px-4 py-3 font-medium">{{ __('Last submission') }}</th>
                <th class="px-4 py-3 font-medium">{{ __('Last successful submission') }}</th>
                <th class="px-4 py-3 font-medium">{{ __('Failed submissions') }}</th>
                <th class="px-6 py-3"></th>
            </tr>
        </thead>
        <tbody>
            @forelse ($companies as $company)
                @php
                    $certificateStatus = $company->zatca_production_csid ? 'production' : ($company->zatca_compliance_csid ? 'compliance_only' : 'not_issued');
                    $integration = $company->zatca_onboarding_status === 'onboarded' && $company->zatca_production_csid ? 'connected'
                        : ($company->zatca_onboarding_status === 'failed' ? 'failed'
                        : ($company->zatca_onboarding_status === 'not_started' ? 'not_connected' : 'pending'));
                    $integrationLabel = ['connected' => __('Connected'), 'failed' => __('Failed'), 'pending' => __('Pending'), 'not_connected' => __('Not connected')][$integration];
                    $integrationClasses = ['connected' => 'bg-emerald-50 text-emerald-700', 'failed' => 'bg-red-50 text-red-700', 'pending' => 'bg-amber-50 text-amber-700', 'not_connected' => 'bg-slate-100 text-slate-500'][$integration];
                    $lastSub = $lastSubmission[$company->id] ?? null;
                    $lastOk = $lastSuccessful[$company->id] ?? null;
                @endphp
                <tr class="border-b border-slate-50 last:border-0 hover:bg-slate-50 align-top">
                    <td class="px-6 py-3">
                        <a href="{{ route('admin.companies.show', $company) }}" class="font-medium text-brand-700 hover:underline">{{ $company->name }}</a>
                    </td>
                    <td class="px-4 py-3">{{ $company->vat_number ?: '—' }}</td>
                    <td class="px-4 py-3">{{ ucfirst($company->zatca_environment) }}</td>
                    <td class="px-4 py-3">{{ ucfirst(str_replace('_', ' ', $company->zatca_onboarding_status)) }}</td>
                    <td class="px-4 py-3">{{ ['production' => __('Production issued'), 'compliance_only' => __('Compliance only'), 'not_issued' => __('Not issued')][$certificateStatus] }}</td>
                    <td class="px-4 py-3"><span class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-semibold {{ $integrationClasses }}">{{ $integrationLabel }}</span></td>
                    <td class="px-4 py-3">{{ $lastSub ? \Illuminate\Support\Carbon::parse($lastSub)->format('Y-m-d H:i') : '—' }}</td>
                    <td class="px-4 py-3">{{ $lastOk ? \Illuminate\Support\Carbon::parse($lastOk)->format('Y-m-d H:i') : '—' }}</td>
                    <td class="px-4 py-3 {{ ($failedCounts[$company->id] ?? 0) > 0 ? 'text-red-600 font-medium' : '' }}">{{ $failedCounts[$company->id] ?? 0 }}</td>
                    <td class="px-6 py-3 text-right whitespace-nowrap">
                        <div x-data="{ open: false }" class="relative inline-block text-start">
                            <button type="button" @click="open = !open" class="text-brand-700 hover:underline">{{ __('Actions') }}</button>
                            <div x-show="open" x-cloak @click.outside="open = false" class="absolute end-0 z-10 mt-2 w-56 rounded-lg border border-slate-200 bg-white p-2 shadow-lg space-y-1 text-start">
                                <a href="{{ route('admin.companies.show', $company) }}" class="block rounded-lg px-3 py-1.5 text-sm text-slate-600 hover:bg-slate-50">{{ __('View company') }}</a>
                                <a href="{{ route('admin.zatca.logs', ['company_id' => $company->id]) }}" class="block rounded-lg px-3 py-1.5 text-sm text-slate-600 hover:bg-slate-50">{{ __('View logs') }}</a>
                                <form method="POST" action="{{ route('admin.zatca.companies.test-connection', $company) }}">
                                    @csrf
                                    <button type="submit" class="w-full text-start rounded-lg px-3 py-1.5 text-sm text-slate-600 hover:bg-slate-50">{{ __('Test connection') }}</button>
                                </form>
                                @if ($company->zatca_onboarding_status !== 'not_started')
                                    <form method="POST" action="{{ route('admin.zatca.companies.reset-onboarding', $company) }}" onsubmit="return confirm('{{ __('Reset this company\'s ZATCA onboarding? They will need to restart from CSR generation.') }}')">
                                        @csrf
                                        <button type="submit" class="w-full text-start rounded-lg px-3 py-1.5 text-sm text-red-600 hover:bg-red-50">{{ __('Re-onboarding workflow (reset)') }}</button>
                                    </form>
                                @endif
                            </div>
                        </div>
                    </td>
                </tr>
            @empty
                <tr><td colspan="10" class="px-6 py-8 text-center text-slate-400">{{ __('No companies match these filters.') }}</td></tr>
            @endforelse
        </tbody>
    </table>
</div>
<div class="mt-4">{{ $companies->links() }}</div>

<div class="mt-6 bg-white rounded-xl border border-slate-100">
    <div class="px-6 py-4 border-b border-slate-100">
        <h3 class="font-semibold text-slate-900">{{ __('Recent ZATCA errors') }}</h3>
    </div>
    @if ($recentErrors->isEmpty())
        <p class="px-6 py-8 text-sm text-slate-500">{{ __('No recent ZATCA errors.') }}</p>
    @else
        <ul class="divide-y divide-slate-50">
            @foreach ($recentErrors as $error)
                <li class="px-6 py-3 text-sm">
                    <div class="flex items-center justify-between gap-4">
                        <span class="font-medium text-slate-700">{{ $error['company'] ?? __('Unknown company') }} · {{ $error['reference'] }}</span>
                        <span class="text-slate-400 text-xs whitespace-nowrap">{{ $error['created_at']->format('Y-m-d H:i') }}</span>
                    </div>
                    <div class="mt-1 flex items-center justify-between gap-4">
                        <span class="text-red-600">{{ \Illuminate\Support\Str::limit($error['error'] ?? __('Submission failed'), 140) }}</span>
                        <a href="{{ route('admin.zatca.logs.show', ['type' => $error['type'], 'log' => $error['log_id']]) }}" class="text-brand-700 hover:underline text-xs whitespace-nowrap">{{ __('View') }}</a>
                    </div>
                </li>
            @endforeach
        </ul>
    @endif
</div>
@endsection
