@extends('layouts.admin')

@section('title', __('Overview'))

@section('content')
@php
    $revenueMax = max(1, $revenueTrend->max('value'));
    $signupsMax = max(1, $signupsTrend->max('value'));
    $planMax = max(1, $planDistribution->max('subscriptions_count') ?: 1);
@endphp

<div class="grid grid-cols-2 gap-4 sm:gap-5 lg:grid-cols-3 xl:grid-cols-6">
    @foreach ([
        ['label' => __('MRR'), 'value' => 'SAR '.number_format($stats['mrr'], 0), 'icon' => 'trend-up', 'accent' => 'from-brand-500 to-emerald-500'],
        ['label' => __('Revenue this month'), 'value' => 'SAR '.number_format($stats['revenue_this_month'], 0), 'icon' => 'billing', 'accent' => 'from-sky-500 to-blue-500'],
        ['label' => __('Total companies'), 'value' => number_format($stats['total_companies']), 'icon' => 'building', 'accent' => 'from-violet-500 to-purple-500'],
        ['label' => __('Active companies'), 'value' => number_format($stats['active_companies']), 'icon' => 'shield', 'accent' => 'from-teal-500 to-cyan-500'],
        ['label' => __('Avg. revenue / company'), 'value' => 'SAR '.number_format($stats['arpc'], 0), 'icon' => 'sales', 'accent' => 'from-amber-500 to-orange-500'],
        ['label' => __('Churned this month'), 'value' => number_format($stats['churned_this_month']), 'icon' => 'trend-down', 'accent' => 'from-rose-500 to-red-500'],
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

@if ($failedZatcaCompanies->isNotEmpty() || $failedPayments->isNotEmpty())
    <div class="mt-6 rounded-2xl border border-red-100 bg-red-50/40 p-6 shadow-card">
        <div class="flex items-center gap-2">
            @include('partials.icon', ['name' => 'alert', 'class' => 'h-5 w-5 text-red-600'])
            <h2 class="font-semibold text-slate-900">{{ __('Needs attention') }}</h2>
        </div>
        <div class="mt-4 grid gap-5 md:grid-cols-2">
            <div>
                <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">{{ __('Failed ZATCA syncs (last 30 days)') }}</p>
                <div class="mt-2 space-y-1">
                    @forelse ($failedZatcaCompanies as $row)
                        <a href="{{ $row->company ? route('admin.companies.show', $row->company) : '#' }}" class="flex items-center justify-between rounded-lg px-2 py-2 text-sm transition-colors hover:bg-white">
                            <span class="truncate font-medium text-slate-700">{{ $row->company?->name ?? __('Unknown company') }}</span>
                            <span class="shrink-0 rounded-full bg-red-100 px-2 py-0.5 text-xs font-semibold text-red-700">{{ __(':count failed', ['count' => $row->failed_count]) }}</span>
                        </a>
                    @empty
                        <p class="px-2 text-sm text-slate-400">{{ __('None.') }}</p>
                    @endforelse
                </div>
            </div>
            <div>
                <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">{{ __('Failed payments') }}</p>
                <div class="mt-2 space-y-1">
                    @forelse ($failedPayments as $payment)
                        <a href="{{ $payment->company ? route('admin.companies.show', $payment->company) : '#' }}" class="flex items-center justify-between rounded-lg px-2 py-2 text-sm transition-colors hover:bg-white">
                            <span class="truncate font-medium text-slate-700">{{ $payment->company?->name ?? __('Unknown company') }}</span>
                            <span class="shrink-0 text-xs font-semibold text-red-700">SAR {{ number_format($payment->amount, 2) }}</span>
                        </a>
                    @empty
                        <p class="px-2 text-sm text-slate-400">{{ __('None.') }}</p>
                    @endforelse
                </div>
            </div>
        </div>
    </div>
@endif

<div class="mt-6 grid gap-5 lg:grid-cols-3">
    <div class="rounded-2xl border border-slate-100 bg-white p-6 shadow-card lg:col-span-2">
        <div class="flex items-center justify-between">
            <h2 class="font-semibold text-slate-900">{{ __('Revenue — last 6 months') }}</h2>
            <span class="text-xs text-slate-400">{{ __('SAR, collected payments') }}</span>
        </div>
        <div class="mt-6 flex h-40 items-end gap-3 sm:gap-5">
            @foreach ($revenueTrend as $point)
                <div class="flex flex-1 flex-col items-center gap-2">
                    <div class="relative flex h-32 w-full items-end justify-center rounded-lg bg-slate-50">
                        <div class="w-full max-w-10 rounded-t-md bg-gradient-to-t from-brand-600 to-brand-400 transition-all duration-700 ease-smooth" style="height: {{ max(4, round($point['value'] / $revenueMax * 100)) }}%" title="SAR {{ number_format($point['value'], 0) }}"></div>
                    </div>
                    <span class="text-xs font-medium text-slate-400">{{ $point['label'] }}</span>
                </div>
            @endforeach
        </div>
    </div>

    <div class="rounded-2xl border border-slate-100 bg-white p-6 shadow-card">
        <h2 class="font-semibold text-slate-900">{{ __('Plan distribution') }}</h2>
        <p class="text-xs text-slate-400">{{ __('Active subscriptions') }}</p>
        <div class="mt-5 space-y-4">
            @forelse ($planDistribution as $plan)
                <div>
                    <div class="flex items-center justify-between text-sm">
                        <span class="font-medium text-slate-700">{{ app()->getLocale() === 'ar' && $plan->name_ar ? $plan->name_ar : $plan->name }}</span>
                        <span class="text-slate-400">{{ $plan->subscriptions_count }}</span>
                    </div>
                    <div class="mt-1.5 h-2 w-full overflow-hidden rounded-full bg-slate-100">
                        <div class="h-full rounded-full bg-gradient-to-r from-brand-500 to-emerald-400 transition-all duration-700 ease-smooth" style="width: {{ round($plan->subscriptions_count / $planMax * 100) }}%"></div>
                    </div>
                </div>
            @empty
                <p class="text-sm text-slate-400">{{ __('No active subscriptions yet.') }}</p>
            @endforelse
        </div>
    </div>
</div>

<div class="mt-6 grid gap-5 lg:grid-cols-3">
    <div class="rounded-2xl border border-slate-100 bg-white p-6 shadow-card lg:col-span-1">
        <h2 class="font-semibold text-slate-900">{{ __('New signups — last 6 months') }}</h2>
        <div class="mt-6 flex h-32 items-end gap-2.5">
            @foreach ($signupsTrend as $point)
                <div class="flex flex-1 flex-col items-center gap-2">
                    <div class="relative flex h-24 w-full items-end justify-center rounded-lg bg-slate-50">
                        <div class="w-full max-w-8 rounded-t-md bg-gradient-to-t from-sky-600 to-sky-400 transition-all duration-700 ease-smooth" style="height: {{ max(4, round($point['value'] / $signupsMax * 100)) }}%" title="{{ $point['value'] }}"></div>
                    </div>
                    <span class="text-xs font-medium text-slate-400">{{ $point['label'] }}</span>
                </div>
            @endforeach
        </div>
    </div>

    <div class="rounded-2xl border border-slate-100 bg-white p-6 shadow-card lg:col-span-1">
        <div class="flex items-center justify-between">
            <h2 class="font-semibold text-slate-900">{{ __('Trials ending soon') }}</h2>
        </div>
        <div class="mt-4 space-y-1">
            @forelse ($trialsEndingSoon as $trial)
                @php($daysLeft = max(0, now()->diffInDays($trial->current_period_end, false)))
                <a href="{{ route('admin.companies.show', $trial->company) }}" class="flex items-center justify-between rounded-lg px-2 py-2.5 text-sm transition-colors hover:bg-slate-50">
                    <span class="truncate font-medium text-slate-700">{{ $trial->company?->name }}</span>
                    <span class="shrink-0 rounded-full px-2 py-0.5 text-xs font-semibold {{ $daysLeft <= 1 ? 'bg-red-50 text-red-600' : 'bg-amber-50 text-amber-700' }}">
                        {{ __(':count days left', ['count' => $daysLeft]) }}
                    </span>
                </a>
            @empty
                <p class="px-2 text-sm text-slate-400">{{ __('No trials ending in the next 3 days.') }}</p>
            @endforelse
        </div>
    </div>

    <div class="rounded-2xl border border-slate-100 bg-white p-6 shadow-card lg:col-span-1">
        <h2 class="font-semibold text-slate-900">{{ __('Quick actions') }}</h2>
        <div class="mt-4 grid grid-cols-2 gap-3">
            @if (auth()->user()->hasAdminPermission('plans'))
                <a href="{{ route('admin.plans.index') }}" class="card-hover flex flex-col items-center justify-center gap-2 rounded-xl border border-slate-100 bg-slate-50 px-3 py-4 text-center text-xs font-medium text-slate-600">
                    @include('partials.icon', ['name' => 'plans', 'class' => 'h-5 w-5 text-brand-600'])
                    {{ __('Manage plans') }}
                </a>
            @endif
            @if (auth()->user()->hasAdminPermission('companies'))
                <a href="{{ route('admin.companies.index') }}" class="card-hover flex flex-col items-center justify-center gap-2 rounded-xl border border-slate-100 bg-slate-50 px-3 py-4 text-center text-xs font-medium text-slate-600">
                    @include('partials.icon', ['name' => 'building', 'class' => 'h-5 w-5 text-brand-600'])
                    {{ __('View companies') }}
                </a>
            @endif
            @if (auth()->user()->hasAdminPermission('payments'))
                <a href="{{ route('admin.payments.index') }}" class="card-hover flex flex-col items-center justify-center gap-2 rounded-xl border border-slate-100 bg-slate-50 px-3 py-4 text-center text-xs font-medium text-slate-600">
                    @include('partials.icon', ['name' => 'billing', 'class' => 'h-5 w-5 text-brand-600'])
                    {{ __('Payment ledger') }}
                </a>
            @endif
            @if (auth()->user()->isSuperAdmin())
                <a href="{{ route('admin.admins.index') }}" class="card-hover flex flex-col items-center justify-center gap-2 rounded-xl border border-slate-100 bg-slate-50 px-3 py-4 text-center text-xs font-medium text-slate-600">
                    @include('partials.icon', ['name' => 'shield', 'class' => 'h-5 w-5 text-brand-600'])
                    {{ __('Admin users') }}
                </a>
            @endif
            @if (auth()->user()->hasAdminPermission('activity'))
                <a href="{{ route('admin.activity.index') }}" class="card-hover flex flex-col items-center justify-center gap-2 rounded-xl border border-slate-100 bg-slate-50 px-3 py-4 text-center text-xs font-medium text-slate-600">
                    @include('partials.icon', ['name' => 'activity', 'class' => 'h-5 w-5 text-brand-600'])
                    {{ __('Activity log') }}
                </a>
            @endif
            @if (auth()->user()->isSuperAdmin())
                <a href="{{ route('admin.settings.edit') }}" class="card-hover flex flex-col items-center justify-center gap-2 rounded-xl border border-slate-100 bg-slate-50 px-3 py-4 text-center text-xs font-medium text-slate-600">
                    @include('partials.icon', ['name' => 'settings', 'class' => 'h-5 w-5 text-brand-600'])
                    {{ __('Platform settings') }}
                </a>
            @endif
        </div>
    </div>
</div>

<div class="mt-6 grid gap-5 lg:grid-cols-2">
    <div class="overflow-hidden rounded-2xl border border-slate-100 bg-white shadow-card">
        <div class="flex items-center justify-between border-b border-slate-100 px-6 py-4">
            <h2 class="font-semibold text-slate-900">{{ __('Recent companies') }}</h2>
            <a href="{{ route('admin.companies.index') }}" class="text-sm font-semibold text-brand-700 hover:underline">{{ __('View all') }}</a>
        </div>
        <table class="w-full text-sm">
            <thead>
                <tr class="border-b border-slate-100 text-left text-slate-500">
                    <th class="px-6 py-3 font-medium">{{ __('Company') }}</th>
                    <th class="px-6 py-3 font-medium">{{ __('Status') }}</th>
                    <th class="px-6 py-3 font-medium">{{ __('Signed up') }}</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($recentCompanies as $company)
                    <tr class="cursor-pointer border-b border-slate-50 transition-colors last:border-0 hover:bg-slate-50" onclick="window.location='{{ route('admin.companies.show', $company) }}'">
                        <td class="px-6 py-3 font-medium text-brand-700">{{ $company->name }}</td>
                        <td class="px-6 py-3">
                            <span class="inline-flex items-center gap-1.5 rounded-full px-2 py-0.5 text-xs font-semibold {{ $company->status === 'active' ? 'bg-emerald-50 text-emerald-700' : 'bg-slate-100 text-slate-500' }}">
                                <span class="h-1.5 w-1.5 rounded-full {{ $company->status === 'active' ? 'bg-emerald-500' : 'bg-slate-400' }}"></span>
                                {{ $company->status === 'active' ? __('Active') : __('Suspended') }}
                            </span>
                        </td>
                        <td class="px-6 py-3 text-slate-500">{{ $company->created_at->format('Y-m-d') }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    <div class="overflow-hidden rounded-2xl border border-slate-100 bg-white shadow-card">
        <div class="flex items-center justify-between border-b border-slate-100 px-6 py-4">
            <h2 class="font-semibold text-slate-900">{{ __('Recent payments') }}</h2>
            <a href="{{ route('admin.payments.index') }}" class="text-sm font-semibold text-brand-700 hover:underline">{{ __('View all') }}</a>
        </div>
        <table class="w-full text-sm">
            <thead>
                <tr class="border-b border-slate-100 text-left text-slate-500">
                    <th class="px-6 py-3 font-medium">{{ __('Company') }}</th>
                    <th class="px-6 py-3 font-medium">{{ __('Amount') }}</th>
                    <th class="px-6 py-3 font-medium">{{ __('Status') }}</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($recentPayments as $payment)
                    <tr class="border-b border-slate-50 transition-colors last:border-0 hover:bg-slate-50">
                        <td class="px-6 py-3 font-medium text-slate-700">{{ $payment->company?->name ?? '—' }}</td>
                        <td class="px-6 py-3 text-slate-600">SAR {{ number_format($payment->amount, 2) }}</td>
                        <td class="px-6 py-3">
                            <span class="inline-flex items-center gap-1.5 rounded-full px-2 py-0.5 text-xs font-semibold {{ $payment->status === 'paid' ? 'bg-emerald-50 text-emerald-700' : 'bg-amber-50 text-amber-700' }}">
                                {{ ucfirst($payment->status) }}
                            </span>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="3" class="px-6 py-6 text-center text-slate-400">{{ __('No payments yet.') }}</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
