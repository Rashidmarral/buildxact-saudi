@extends('layouts.admin')

@section('title', __('Companies'))

@section('content')
<form method="GET" class="mb-6 bg-white rounded-xl border border-slate-100 p-4 space-y-3">
    <input type="text" name="q" value="{{ request('q') }}" placeholder="{{ __('Search by company, owner, email, phone, VAT or CR number...') }}" class="w-full rounded-lg border border-slate-200 text-sm focus:border-brand-500 focus:ring-brand-500">

    <div class="flex flex-wrap items-end gap-3">
        <div>
            <label class="block text-xs font-medium text-slate-500 mb-1">{{ __('Status') }}</label>
            <select name="status" class="rounded-lg border border-slate-200 text-sm">
                <option value="">{{ __('All statuses') }}</option>
                @foreach ([
                    'active' => __('Active'),
                    'trial' => __('Trial'),
                    'expired' => __('Expired'),
                    'suspended' => __('Suspended'),
                    'cancelled' => __('Cancelled'),
                    'past_due' => __('Past due'),
                    'grace_period' => __('Grace period'),
                    'sub_suspended' => __('Subscription suspended'),
                    'paid' => __('Paid'),
                ] as $value => $label)
                    <option value="{{ $value }}" @selected(request('status') === $value)>{{ $label }}</option>
                @endforeach
            </select>
        </div>

        <div>
            <label class="block text-xs font-medium text-slate-500 mb-1">{{ __('ZATCA') }}</label>
            <select name="zatca" class="rounded-lg border border-slate-200 text-sm">
                <option value="">{{ __('All') }}</option>
                <option value="connected" @selected(request('zatca') === 'connected')>{{ __('ZATCA connected') }}</option>
                <option value="failed" @selected(request('zatca') === 'failed')>{{ __('ZATCA failed') }}</option>
            </select>
        </div>

        <div>
            <label class="block text-xs font-medium text-slate-500 mb-1">{{ __('Plan') }}</label>
            <select name="plan_id" class="rounded-lg border border-slate-200 text-sm">
                <option value="">{{ __('All plans') }}</option>
                @foreach ($plans as $plan)
                    <option value="{{ $plan->id }}" @selected((string) request('plan_id') === (string) $plan->id)>{{ $plan->name }}</option>
                @endforeach
            </select>
        </div>

        <div>
            <label class="block text-xs font-medium text-slate-500 mb-1">{{ __('Registered from') }}</label>
            <input type="date" name="date_from" value="{{ request('date_from') }}" class="rounded-lg border border-slate-200 text-sm">
        </div>

        <div>
            <label class="block text-xs font-medium text-slate-500 mb-1">{{ __('Registered to') }}</label>
            <input type="date" name="date_to" value="{{ request('date_to') }}" class="rounded-lg border border-slate-200 text-sm">
        </div>

        <button type="submit" class="rounded-lg bg-brand-600 px-4 py-2 text-sm font-semibold text-white hover:bg-brand-700">{{ __('Filter') }}</button>
        @if (request()->anyFilled(['q', 'status', 'zatca', 'plan_id', 'date_from', 'date_to']))
            <a href="{{ route('admin.companies.index') }}" class="text-sm font-semibold text-slate-500 hover:underline">{{ __('Clear') }}</a>
        @endif
    </div>
</form>

<div class="bg-white rounded-xl border border-slate-100 overflow-x-auto">
    <table class="w-full text-sm">
        <thead>
            <tr class="text-left text-slate-500 border-b border-slate-100 whitespace-nowrap">
                <th class="px-6 py-3 font-medium">{{ __('Company') }}</th>
                <th class="px-4 py-3 font-medium">{{ __('Owner') }}</th>
                <th class="px-4 py-3 font-medium">{{ __('Email') }}</th>
                <th class="px-4 py-3 font-medium">{{ __('Phone') }}</th>
                <th class="px-4 py-3 font-medium">{{ __('Plan') }}</th>
                <th class="px-4 py-3 font-medium">{{ __('Subscription') }}</th>
                <th class="px-4 py-3 font-medium">{{ __('Users') }}</th>
                <th class="px-4 py-3 font-medium">{{ __('Branches') }}</th>
                <th class="px-4 py-3 font-medium">{{ __('ZATCA') }}</th>
                <th class="px-4 py-3 font-medium">{{ __('Storage') }}</th>
                <th class="px-4 py-3 font-medium">{{ __('Created') }}</th>
                <th class="px-4 py-3 font-medium">{{ __('Last login') }}</th>
                <th class="px-4 py-3 font-medium">{{ __('Status') }}</th>
                <th class="px-6 py-3"></th>
            </tr>
        </thead>
        <tbody>
            @forelse ($companies as $company)
                @php
                    $owner = $owners->get($company->id);
                    $sub = $latestSubscriptions->get($company->id);
                    $storageBytes = (int) ($storageByCompany[$company->id] ?? 0);
                    $lastActivity = $lastLoginByCompany[$company->id] ?? null;
                @endphp
                <tr class="border-b border-slate-50 last:border-0 hover:bg-slate-50 align-top">
                    <td class="px-6 py-3">
                        <a href="{{ route('admin.companies.show', $company) }}" class="font-medium text-brand-700 hover:underline">{{ $company->name }}</a>
                        <div class="text-xs text-slate-400">{{ $company->vat_number ?: __('No VAT') }}</div>
                    </td>
                    <td class="px-4 py-3">{{ $owner?->name ?? '—' }}</td>
                    <td class="px-4 py-3">{{ $owner?->email ?? $company->email ?? '—' }}</td>
                    <td class="px-4 py-3">{{ $owner?->phone ?? $company->phone ?? '—' }}</td>
                    <td class="px-4 py-3">{{ $sub?->plan?->name ?? '—' }}</td>
                    <td class="px-4 py-3">
                        @if ($sub)
                            <span class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-semibold {{ $sub->statusBadgeClasses() }}">{{ $sub->statusLabel() }}</span>
                        @else
                            <span class="text-slate-400">—</span>
                        @endif
                    </td>
                    <td class="px-4 py-3">{{ $company->users_count }}</td>
                    <td class="px-4 py-3">{{ $company->branches_count }}</td>
                    <td class="px-4 py-3">
                        @php $zatcaLabel = ['onboarded' => __('Connected'), 'failed' => __('Failed')][$company->zatca_onboarding_status] ?? ucfirst(str_replace('_', ' ', $company->zatca_onboarding_status)); @endphp
                        <span class="{{ $company->zatca_onboarding_status === 'onboarded' ? 'text-emerald-600' : ($company->zatca_onboarding_status === 'failed' ? 'text-red-600' : 'text-slate-400') }}">{{ $zatcaLabel }}</span>
                    </td>
                    <td class="px-4 py-3">{{ number_format($storageBytes / 1048576, 1) }} {{ __('MB') }}</td>
                    <td class="px-4 py-3">{{ $company->created_at->format('Y-m-d') }}</td>
                    <td class="px-4 py-3">{{ $lastActivity ? \Illuminate\Support\Carbon::createFromTimestamp($lastActivity)->diffForHumans() : __('Never') }}</td>
                    <td class="px-4 py-3">
                        @if ($company->status === 'active')
                            <span class="text-emerald-700">{{ __('Active') }}</span>
                        @else
                            <span class="text-red-600">{{ __('Suspended') }}</span>
                        @endif
                    </td>
                    <td class="px-6 py-3 text-right whitespace-nowrap">
                        <a href="{{ route('admin.companies.show', $company) }}" class="text-brand-700 hover:underline">{{ __('View') }}</a>
                    </td>
                </tr>
            @empty
                <tr><td colspan="14" class="px-6 py-8 text-center text-slate-400">{{ __('No companies match these filters.') }}</td></tr>
            @endforelse
        </tbody>
    </table>
</div>

<div class="mt-4">{{ $companies->links() }}</div>
@endsection
