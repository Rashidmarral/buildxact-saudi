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
        ['label' => __('ARR'), 'value' => 'SAR '.number_format($stats['arr'], 0), 'icon' => 'trend-up', 'accent' => 'from-emerald-500 to-teal-500'],
        ['label' => __('Revenue this month'), 'value' => 'SAR '.number_format($stats['revenue_this_month'], 0), 'icon' => 'billing', 'accent' => 'from-sky-500 to-blue-500'],
        ['label' => __('Total companies'), 'value' => number_format($stats['total_companies']), 'icon' => 'building', 'accent' => 'from-violet-500 to-purple-500'],
        ['label' => __('Active companies'), 'value' => number_format($stats['active_companies']), 'icon' => 'shield', 'accent' => 'from-teal-500 to-cyan-500'],
        ['label' => __('Active subscriptions'), 'value' => number_format($stats['active_subscriptions']), 'icon' => 'shield', 'accent' => 'from-cyan-500 to-sky-500'],
        ['label' => __('Trial companies'), 'value' => number_format($stats['trialing_companies']), 'icon' => 'building', 'accent' => 'from-indigo-500 to-violet-500'],
        ['label' => __('Trial conversion rate'), 'value' => number_format($stats['trial_conversion_rate'], 1).'%', 'icon' => 'trend-up', 'accent' => 'from-purple-500 to-fuchsia-500'],
        ['label' => __('Avg. revenue / company'), 'value' => 'SAR '.number_format($stats['arpc'], 0), 'icon' => 'sales', 'accent' => 'from-amber-500 to-orange-500'],
        ['label' => __('Avg. revenue / customer'), 'value' => 'SAR '.number_format($stats['arpu_this_month'], 0), 'icon' => 'sales', 'accent' => 'from-orange-500 to-amber-500'],
        ['label' => __('Outstanding revenue'), 'value' => 'SAR '.number_format($stats['outstanding_revenue'], 0), 'icon' => 'billing', 'accent' => 'from-yellow-500 to-amber-500'],
        ['label' => __('Failed payments'), 'value' => number_format($stats['failed_payments_total']), 'icon' => 'trend-down', 'accent' => 'from-red-500 to-rose-500'],
        ['label' => __('Past-due subscriptions'), 'value' => number_format($stats['past_due_subscriptions']), 'icon' => 'trend-down', 'accent' => 'from-orange-500 to-red-500'],
        ['label' => __('Suspended companies'), 'value' => number_format($stats['suspended_companies']), 'icon' => 'building', 'accent' => 'from-slate-500 to-slate-600'],
        ['label' => __('Cancelled subscriptions'), 'value' => number_format($stats['cancelled_subscriptions']), 'icon' => 'trend-down', 'accent' => 'from-rose-500 to-pink-500'],
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

@php
    $hasAttentionItems = $failedZatcaCompanies->isNotEmpty() || $failedPayments->isNotEmpty()
        || $trialsEndingSoon->isNotEmpty() || $expiredSubscriptionCompanies->isNotEmpty()
        || $failedJobsCount > 0 || $noRecentLoginCompanies->isNotEmpty()
        || $recentErrorCount > 0 || ($storageCheck && $storageCheck['status'] !== 'healthy');
@endphp
@if ($hasAttentionItems)
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
            <div>
                <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">{{ __('Expiring trials (next 3 days)') }}</p>
                <div class="mt-2 space-y-1">
                    @forelse ($trialsEndingSoon as $trial)
                        <a href="{{ $trial->company ? route('admin.companies.show', $trial->company) : '#' }}" class="flex items-center justify-between rounded-lg px-2 py-2 text-sm transition-colors hover:bg-white">
                            <span class="truncate font-medium text-slate-700">{{ $trial->company?->name ?? __('Unknown company') }}</span>
                            <span class="shrink-0 rounded-full bg-amber-100 px-2 py-0.5 text-xs font-semibold text-amber-700">{{ \App\Support\PlatformFormat::date($trial->current_period_end) }}</span>
                        </a>
                    @empty
                        <p class="px-2 text-sm text-slate-400">{{ __('None.') }}</p>
                    @endforelse
                </div>
            </div>
            <div>
                <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">{{ __('Expired subscriptions') }}</p>
                <div class="mt-2 space-y-1">
                    @forelse ($expiredSubscriptionCompanies as $company)
                        <a href="{{ route('admin.companies.show', $company) }}" class="flex items-center justify-between rounded-lg px-2 py-2 text-sm transition-colors hover:bg-white">
                            <span class="truncate font-medium text-slate-700">{{ $company->name }}</span>
                        </a>
                    @empty
                        <p class="px-2 text-sm text-slate-400">{{ __('None.') }}</p>
                    @endforelse
                </div>
            </div>
            <div>
                <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">{{ __('Companies with no recent login (30+ days)') }}</p>
                <div class="mt-2 space-y-1">
                    @forelse ($noRecentLoginCompanies as $company)
                        <a href="{{ route('admin.companies.show', $company) }}" class="flex items-center justify-between rounded-lg px-2 py-2 text-sm transition-colors hover:bg-white">
                            <span class="truncate font-medium text-slate-700">{{ $company->name }}</span>
                        </a>
                    @empty
                        <p class="px-2 text-sm text-slate-400">{{ __('None.') }}</p>
                    @endforelse
                </div>
            </div>
            <div>
                <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">{{ __('System') }}</p>
                <div class="mt-2 space-y-1">
                    @if ($failedJobsCount > 0)
                        <div class="flex items-center justify-between rounded-lg px-2 py-2 text-sm">
                            <span class="truncate font-medium text-slate-700">{{ __('Failed background jobs (24h)') }}</span>
                            <span class="shrink-0 rounded-full bg-red-100 px-2 py-0.5 text-xs font-semibold text-red-700">{{ number_format($failedJobsCount) }}</span>
                        </div>
                    @endif
                    @if ($recentErrorCount > 0)
                        <div class="flex items-center justify-between rounded-lg px-2 py-2 text-sm">
                            <span class="truncate font-medium text-slate-700">{{ __('System errors (24h)') }}</span>
                            <span class="shrink-0 rounded-full bg-red-100 px-2 py-0.5 text-xs font-semibold text-red-700">{{ number_format($recentErrorCount) }}</span>
                        </div>
                    @endif
                    @if ($storageCheck && $storageCheck['status'] !== 'healthy')
                        <a href="{{ route('admin.settings.edit') }}" class="flex items-center justify-between rounded-lg px-2 py-2 text-sm transition-colors hover:bg-white">
                            <span class="truncate font-medium text-slate-700">{{ __('Storage limit warning') }}</span>
                            <span class="shrink-0 rounded-full bg-amber-100 px-2 py-0.5 text-xs font-semibold text-amber-700">{{ $storageCheck['detail'] }}</span>
                        </a>
                    @endif
                    @if ($failedJobsCount === 0 && $recentErrorCount === 0 && (! $storageCheck || $storageCheck['status'] === 'healthy'))
                        <p class="px-2 text-sm text-slate-400">{{ __('None.') }}</p>
                    @endif
                </div>
            </div>
        </div>
    </div>
@endif

<div class="mt-6 rounded-2xl border border-slate-100 bg-white p-6 shadow-card">
    <h2 class="font-semibold text-slate-900">{{ __('SaaS growth') }}</h2>
    <div class="mt-4 grid grid-cols-2 gap-4 sm:gap-5 md:grid-cols-4 lg:grid-cols-7">
        @foreach ([
            ['label' => __('New companies today'), 'value' => number_format($stats['new_companies_today'])],
            ['label' => __('New companies this week'), 'value' => number_format($stats['new_companies_this_week'])],
            ['label' => __('New companies this month'), 'value' => number_format($stats['new_companies_this_month'])],
            ['label' => __('Revenue this month'), 'value' => 'SAR '.number_format($stats['revenue_this_month'], 0)],
            ['label' => __('Revenue previous month'), 'value' => 'SAR '.number_format($stats['revenue_previous_month'], 0)],
            ['label' => __('Growth'), 'value' => ($stats['growth_percentage'] >= 0 ? '+' : '').number_format($stats['growth_percentage'], 1).'%', 'tone' => $stats['growth_percentage'] >= 0 ? 'text-emerald-600' : 'text-red-600'],
            ['label' => __('Churn'), 'value' => number_format($stats['churn_percentage'], 1).'%', 'tone' => $stats['churn_percentage'] > 0 ? 'text-red-600' : 'text-slate-900'],
        ] as $tile)
            <div class="rounded-xl bg-slate-50 px-4 py-3">
                <p class="text-xs font-medium text-slate-500">{{ $tile['label'] }}</p>
                <p class="mt-1 text-lg font-bold {{ $tile['tone'] ?? 'text-slate-900' }}">{{ $tile['value'] }}</p>
            </div>
        @endforeach
    </div>
</div>

<div class="mt-6 grid gap-5 lg:grid-cols-3">
    <div class="rounded-2xl border border-slate-100 bg-white p-6 shadow-card lg:col-span-2">
        <h2 class="font-semibold text-slate-900">{{ __('System health') }}</h2>
        <div class="mt-4 grid gap-2.5 sm:grid-cols-2">
            @foreach ($systemHealthChecks as $check)
                @php
                    $badge = match ($check['status']) {
                        'healthy' => ['bg-emerald-50 text-emerald-700', 'bg-emerald-500', __('Healthy')],
                        'warning' => ['bg-amber-50 text-amber-700', 'bg-amber-500', __('Warning')],
                        default => ['bg-red-50 text-red-700', 'bg-red-500', __('Failed')],
                    };
                @endphp
                <div class="flex items-center justify-between rounded-lg border border-slate-100 px-3 py-2.5" title="{{ $check['detail'] }}">
                    <span class="text-sm font-medium text-slate-700">{{ $check['label'] }}</span>
                    <span class="inline-flex shrink-0 items-center gap-1.5 rounded-full px-2 py-0.5 text-xs font-semibold {{ $badge[0] }}">
                        <span class="h-1.5 w-1.5 rounded-full {{ $badge[1] }}"></span>
                        {{ $badge[2] }}
                    </span>
                </div>
            @endforeach
        </div>
    </div>

    <div class="rounded-2xl border border-slate-100 bg-white p-6 shadow-card">
        <h2 class="font-semibold text-slate-900">{{ __('Subscription status') }}</h2>
        <p class="text-xs text-slate-400">{{ __('Current status per company') }}</p>
        <div class="mt-5 space-y-4">
            @php $subStatusMax = max(1, $subscriptionStatusDistribution->max('count') ?: 1); @endphp
            @forelse ($subscriptionStatusDistribution as $row)
                <div>
                    <div class="flex items-center justify-between text-sm">
                        <span class="font-medium text-slate-700">{{ $row['label'] }}</span>
                        <span class="text-slate-400">{{ $row['count'] }}</span>
                    </div>
                    <div class="mt-1.5 h-2 w-full overflow-hidden rounded-full bg-slate-100">
                        <div class="h-full rounded-full bg-gradient-to-r from-brand-500 to-emerald-400 transition-all duration-700 ease-smooth" style="width: {{ round($row['count'] / $subStatusMax * 100) }}%"></div>
                    </div>
                </div>
            @empty
                <p class="text-sm text-slate-400">{{ __('No subscriptions yet.') }}</p>
            @endforelse
        </div>
    </div>
</div>

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
                        <td class="px-6 py-3 text-slate-500">{{ \App\Support\PlatformFormat::date($company->created_at) }}</td>
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
