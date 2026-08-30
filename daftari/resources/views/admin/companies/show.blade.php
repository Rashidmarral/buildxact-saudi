@extends('layouts.admin')

@section('title', $company->name)

@section('content')
@php
    $owner = $company->users->firstWhere('role', 'owner') ?? $company->users->first();
    $storageLimitMb = $subscription?->plan?->max_storage_mb;
    $storageUsedMb = $storageUsedBytes / 1048576;
@endphp

<div x-data="{ tab: 'overview' }">

    <div class="flex flex-wrap items-center justify-between gap-3 mb-6">
        <div>
            <div class="flex items-center gap-2">
                <h2 class="text-lg font-semibold text-slate-900">{{ $company->name }}</h2>
                <span class="inline-flex items-center gap-1.5 rounded-full px-2 py-0.5 text-xs font-semibold {{ $company->status === 'active' ? 'bg-emerald-50 text-emerald-700' : 'bg-slate-100 text-slate-500' }}">
                    {{ $company->status === 'active' ? __('Active') : __('Suspended') }}
                </span>
                @if ($subscription)
                    <span class="inline-flex items-center gap-1.5 rounded-full px-2 py-0.5 text-xs font-semibold
                        {{ match($subscription->status) {
                            'active' => 'bg-emerald-50 text-emerald-700',
                            'trialing' => 'bg-amber-50 text-amber-700',
                            'cancelled' => 'bg-slate-100 text-slate-500',
                            default => 'bg-red-50 text-red-600',
                        } }}">{{ ucfirst($subscription->status) }}</span>
                    @if ($subscription->cancelled_at)
                        <span class="inline-flex items-center gap-1.5 rounded-full px-2 py-0.5 text-xs font-semibold bg-orange-50 text-orange-600">{{ __('Scheduled to cancel') }}</span>
                    @endif
                @endif
            </div>
            <p class="text-sm text-slate-500">{{ $company->email }} · {{ $company->vat_number ?: __('No VAT number') }} · {{ __('Created') }} {{ $company->created_at->format('Y-m-d') }}</p>
        </div>
        <div class="flex flex-wrap items-center gap-3">
            <form method="POST" action="{{ route('admin.companies.impersonate', $company) }}">
                @csrf
                <button type="submit" class="inline-flex items-center gap-1.5 rounded-lg border border-slate-200 px-4 py-2 text-sm font-semibold text-slate-600 hover:border-slate-300">
                    @include('partials.icon', ['name' => 'log-in', 'class' => 'h-4 w-4'])
                    {{ __('Log in as this company') }}
                </button>
            </form>
            @if ($company->status === 'active')
                <form method="POST" action="{{ route('admin.companies.suspend', $company) }}" onsubmit="return confirm('{{ __('Suspend this company? All of its users will be locked out immediately.') }}')">
                    @csrf
                    <button type="submit" class="rounded-lg border border-red-200 text-red-600 px-4 py-2 text-sm font-semibold hover:bg-red-50">{{ __('Suspend') }}</button>
                </form>
            @else
                <form method="POST" action="{{ route('admin.companies.activate', $company) }}">
                    @csrf
                    <button type="submit" class="rounded-lg bg-brand-600 px-4 py-2 text-sm font-semibold text-white hover:bg-brand-700">{{ __('Activate') }}</button>
                </form>
            @endif
        </div>
    </div>

    <div class="flex gap-1 border-b border-slate-200 mb-6 overflow-x-auto">
        @foreach ([
            'overview' => __('Overview'),
            'users' => __('Users'),
            'branches' => __('Branches'),
            'subscription' => __('Subscription'),
            'billing' => __('Billing'),
            'zatca' => __('ZATCA'),
            'storage' => __('Storage'),
            'activity' => __('Activity'),
        ] as $key => $label)
            <button type="button" @click="tab = '{{ $key }}'"
                :class="tab === '{{ $key }}' ? 'border-brand-600 text-brand-700' : 'border-transparent text-slate-500 hover:text-slate-700'"
                class="whitespace-nowrap px-4 py-2.5 text-sm font-semibold border-b-2 -mb-px transition-colors">{{ $label }}</button>
        @endforeach
    </div>

    {{-- ============ Overview ============ --}}
    <div x-show="tab === 'overview'" x-cloak class="space-y-6">
        <div class="grid md:grid-cols-3 gap-6">
            <div class="bg-white rounded-xl border border-slate-100 p-6">
                <h3 class="font-semibold text-slate-900 mb-4">{{ __('Company information') }}</h3>
                <dl class="space-y-2 text-sm">
                    <div class="flex justify-between"><dt class="text-slate-500">{{ __('Name') }}</dt><dd class="font-medium">{{ $company->name }}</dd></div>
                    <div class="flex justify-between"><dt class="text-slate-500">{{ __('VAT number') }}</dt><dd class="font-medium">{{ $company->vat_number ?: '—' }}</dd></div>
                    <div class="flex justify-between"><dt class="text-slate-500">{{ __('CR number') }}</dt><dd class="font-medium">{{ $company->cr_number ?: '—' }}</dd></div>
                    <div class="flex justify-between"><dt class="text-slate-500">{{ __('Phone') }}</dt><dd class="font-medium">{{ $company->phone ?: '—' }}</dd></div>
                    <div class="flex justify-between"><dt class="text-slate-500">{{ __('Email') }}</dt><dd class="font-medium">{{ $company->email ?: '—' }}</dd></div>
                    <div class="flex justify-between"><dt class="text-slate-500">{{ __('Industry') }}</dt><dd class="font-medium">{{ $company->industry ? ucfirst(str_replace('_', ' ', $company->industry)) : '—' }}</dd></div>
                </dl>
            </div>

            <div class="bg-white rounded-xl border border-slate-100 p-6">
                <h3 class="font-semibold text-slate-900 mb-4">{{ __('Owner') }}</h3>
                @if ($owner)
                    <dl class="space-y-2 text-sm">
                        <div class="flex justify-between"><dt class="text-slate-500">{{ __('Name') }}</dt><dd class="font-medium">{{ $owner->name }}</dd></div>
                        <div class="flex justify-between"><dt class="text-slate-500">{{ __('Email') }}</dt><dd class="font-medium">{{ $owner->email }}</dd></div>
                        <div class="flex justify-between"><dt class="text-slate-500">{{ __('Phone') }}</dt><dd class="font-medium">{{ $owner->phone ?: '—' }}</dd></div>
                    </dl>
                @else
                    <p class="text-sm text-slate-400">{{ __('No owner user found.') }}</p>
                @endif
            </div>

            <div class="bg-white rounded-xl border border-slate-100 p-6">
                <h3 class="font-semibold text-slate-900 mb-4">{{ __('Status & plan') }}</h3>
                <dl class="space-y-2 text-sm">
                    <div class="flex justify-between"><dt class="text-slate-500">{{ __('Company status') }}</dt><dd class="font-medium">{{ $company->status === 'active' ? __('Active') : __('Suspended') }}</dd></div>
                    <div class="flex justify-between"><dt class="text-slate-500">{{ __('Plan') }}</dt><dd class="font-medium">{{ $subscription?->plan?->name ?? '—' }}</dd></div>
                    <div class="flex justify-between"><dt class="text-slate-500">{{ __('Subscription') }}</dt><dd class="font-medium">{{ $subscription ? ucfirst($subscription->status) : '—' }}</dd></div>
                    <div class="flex justify-between"><dt class="text-slate-500">{{ __('Created') }}</dt><dd class="font-medium">{{ $company->created_at->format('Y-m-d') }}</dd></div>
                </dl>
            </div>
        </div>

        <div class="bg-white rounded-xl border border-red-100 p-6">
            <h3 class="font-semibold text-red-700 mb-1">{{ __('Danger zone') }}</h3>
            <p class="text-sm text-slate-500 mb-4">{{ __('Resets numbering, currency, locale, approval thresholds and ZATCA sync behavior to platform defaults. Users, invoices, ZATCA credentials, VAT/CR numbers and address are never touched.') }}</p>
            <form method="POST" action="{{ route('admin.companies.reset-settings', $company) }}" onsubmit="return confirm('{{ __('Reset this company\'s settings to platform defaults? This cannot be undone.') }}')">
                @csrf
                <button type="submit" class="rounded-lg border border-red-200 text-red-600 px-4 py-2 text-sm font-semibold hover:bg-red-50">{{ __('Reset company settings') }}</button>
            </form>
        </div>
    </div>

    {{-- ============ Users ============ --}}
    <div x-show="tab === 'users'" x-cloak class="space-y-6">
        <div class="grid sm:grid-cols-2 gap-6">
            <div class="bg-white rounded-xl border border-slate-100 p-6">
                <h3 class="font-semibold text-slate-900 mb-4">{{ __('Users') }}</h3>
                <dl class="space-y-2 text-sm">
                    <div class="flex justify-between"><dt class="text-slate-500">{{ __('Active users') }}</dt><dd class="font-medium text-emerald-600">{{ $activeUsersCount }}</dd></div>
                    <div class="flex justify-between"><dt class="text-slate-500">{{ __('Inactive users') }}</dt><dd class="font-medium text-slate-500">{{ $inactiveUsersCount }}</dd></div>
                </dl>
            </div>
            <div class="bg-white rounded-xl border border-slate-100 p-6">
                <h3 class="font-semibold text-slate-900 mb-4">{{ __('Roles') }}</h3>
                @if ($roles->isEmpty())
                    <p class="text-sm text-slate-400">{{ __('No custom roles.') }}</p>
                @else
                    <ul class="space-y-2 text-sm">
                        @foreach ($roles as $role)
                            <li class="flex justify-between"><span>{{ $role->name }}</span><span class="text-slate-500">{{ $role->users_count }} {{ __('users') }}</span></li>
                        @endforeach
                    </ul>
                @endif
            </div>
        </div>

        <div class="bg-white rounded-xl border border-slate-100">
            <table class="w-full text-sm">
                <thead>
                    <tr class="text-left text-slate-500 border-b border-slate-100">
                        <th class="px-6 py-3 font-medium">{{ __('Name') }}</th>
                        <th class="px-6 py-3 font-medium">{{ __('Role') }}</th>
                        <th class="px-6 py-3 font-medium">{{ __('Email') }}</th>
                        <th class="px-6 py-3 font-medium">{{ __('Status') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($company->users as $user)
                        <tr class="border-b border-slate-50 last:border-0">
                            <td class="px-6 py-3">{{ $user->name }}</td>
                            <td class="px-6 py-3 text-slate-500">{{ ucfirst($user->role) }}</td>
                            <td class="px-6 py-3">{{ $user->email }}</td>
                            <td class="px-6 py-3">
                                <span class="{{ $user->status === 'active' ? 'text-emerald-600' : 'text-slate-400' }}">{{ ucfirst($user->status) }}</span>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>

    {{-- ============ Branches ============ --}}
    <div x-show="tab === 'branches'" x-cloak>
        <div class="bg-white rounded-xl border border-slate-100">
            <div class="px-6 py-4 border-b border-slate-100 flex items-center justify-between">
                <h3 class="font-semibold text-slate-900">{{ __('Branches') }}</h3>
                <span class="text-sm text-slate-500">{{ $branches->count() }} {{ __('branches') }}</span>
            </div>
            @if ($branches->isEmpty())
                <p class="px-6 py-8 text-sm text-slate-500">{{ __('No branches yet.') }}</p>
            @else
                <table class="w-full text-sm">
                    <thead>
                        <tr class="text-left text-slate-500 border-b border-slate-100">
                            <th class="px-6 py-3 font-medium">{{ __('Code') }}</th>
                            <th class="px-6 py-3 font-medium">{{ __('Name') }}</th>
                            <th class="px-6 py-3 font-medium">{{ __('City') }}</th>
                            <th class="px-6 py-3 font-medium">{{ __('Phone') }}</th>
                            <th class="px-6 py-3 font-medium">{{ __('Email') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($branches as $branch)
                            <tr class="border-b border-slate-50 last:border-0">
                                <td class="px-6 py-3 font-mono text-xs text-slate-500">{{ $branch->code }}</td>
                                <td class="px-6 py-3">{{ $branch->name }}</td>
                                <td class="px-6 py-3">{{ $branch->city ?: '—' }}</td>
                                <td class="px-6 py-3">{{ $branch->phone ?: '—' }}</td>
                                <td class="px-6 py-3">{{ $branch->email ?: '—' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif
        </div>
    </div>

    {{-- ============ Subscription ============ --}}
    <div x-show="tab === 'subscription'" x-cloak class="space-y-6">
        <div class="bg-white rounded-xl border border-slate-100 p-6">
            <h3 class="font-semibold text-slate-900 mb-4">{{ __('Current subscription') }}</h3>
            <ul class="space-y-2 text-sm mb-4">
                @forelse ($company->subscriptions as $sub)
                    <li class="flex justify-between">
                        <span>{{ $sub->plan->name }} — {{ ucfirst($sub->status) }}</span>
                        <span class="text-slate-500">{{ ucfirst($sub->billing_cycle) }} · {{ __('renews') }} {{ optional($sub->current_period_end)->format('Y-m-d') ?? '—' }}</span>
                    </li>
                @empty
                    <li class="text-slate-400">{{ __('No subscriptions.') }}</li>
                @endforelse
            </ul>

            <details class="group" open>
                <summary class="cursor-pointer list-none text-sm font-semibold text-brand-700 hover:underline">{{ __('Change plan / status') }}</summary>
                <form method="POST" action="{{ route('admin.companies.change-plan', $company) }}" class="mt-3 grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                    @csrf
                    <div>
                        <label class="block text-xs font-medium text-slate-500 mb-1">{{ __('Plan') }}</label>
                        <select name="plan_id" required class="w-full rounded-lg border border-slate-200 text-sm">
                            @foreach ($plans as $plan)
                                <option value="{{ $plan->id }}" @selected($subscription?->plan_id === $plan->id)>{{ $plan->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-slate-500 mb-1">{{ __('Billing cycle') }}</label>
                        <select name="billing_cycle" required class="w-full rounded-lg border border-slate-200 text-sm">
                            <option value="monthly" @selected(($subscription?->billing_cycle ?? 'monthly') === 'monthly')>{{ __('Monthly') }}</option>
                            <option value="yearly" @selected($subscription?->billing_cycle === 'yearly')>{{ __('Yearly') }}</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-slate-500 mb-1">{{ __('Status') }}</label>
                        <select name="status" required class="w-full rounded-lg border border-slate-200 text-sm">
                            @foreach (['trialing', 'active', 'cancelled', 'expired'] as $status)
                                <option value="{{ $status }}" @selected(($subscription?->status ?? 'active') === $status)>{{ ucfirst($status) }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-slate-500 mb-1">{{ __('Renews / ends on') }}</label>
                        <input type="date" name="current_period_end" required value="{{ optional($subscription?->current_period_end)->format('Y-m-d') ?? now()->addMonth()->format('Y-m-d') }}" class="w-full rounded-lg border border-slate-200 text-sm">
                    </div>
                    <div class="sm:col-span-2 lg:col-span-4">
                        <button type="submit" class="rounded-lg bg-brand-600 px-4 py-2 text-sm font-semibold text-white hover:bg-brand-700">{{ __('Save subscription') }}</button>
                        <span class="ms-2 text-xs text-slate-400">{{ __('This is a support override — no payment record is created.') }}</span>
                    </div>
                </form>
            </details>
        </div>

        <div class="grid sm:grid-cols-2 gap-6">
            <div class="bg-white rounded-xl border border-slate-100 p-6">
                <h3 class="font-semibold text-slate-900 mb-2">{{ __('Extend trial') }}</h3>
                <p class="text-sm text-slate-500 mb-4">{{ __('Pushes the trial end date (and, if still trialing, the subscription period) forward.') }}</p>
                <form method="POST" action="{{ route('admin.companies.extend-trial', $company) }}" class="flex items-end gap-3">
                    @csrf
                    <div>
                        <label class="block text-xs font-medium text-slate-500 mb-1">{{ __('Days') }}</label>
                        <input type="number" name="days" min="1" max="365" value="14" required class="w-24 rounded-lg border border-slate-200 text-sm">
                    </div>
                    <button type="submit" class="rounded-lg bg-brand-600 px-4 py-2 text-sm font-semibold text-white hover:bg-brand-700">{{ __('Extend trial') }}</button>
                </form>
                <p class="mt-3 text-xs text-slate-400">{{ __('Trial ends') }}: {{ optional($company->trial_ends_at)->format('Y-m-d') ?? '—' }}</p>
            </div>

            <div class="bg-white rounded-xl border border-slate-100 p-6">
                <h3 class="font-semibold text-slate-900 mb-2">{{ __('Suspend / resume subscription') }}</h3>
                <p class="text-sm text-slate-500 mb-4">{{ __('Suspend locks the company out entirely. Cancel schedules the subscription to lapse at period end; resume undoes a pending cancellation.') }}</p>
                <div class="flex flex-wrap gap-3">
                    @if ($company->status === 'active')
                        <form method="POST" action="{{ route('admin.companies.suspend', $company) }}" onsubmit="return confirm('{{ __('Suspend this company? All of its users will be locked out immediately.') }}')">
                            @csrf
                            <button type="submit" class="rounded-lg border border-red-200 text-red-600 px-4 py-2 text-sm font-semibold hover:bg-red-50">{{ __('Suspend subscription') }}</button>
                        </form>
                    @else
                        <form method="POST" action="{{ route('admin.companies.activate', $company) }}">
                            @csrf
                            <button type="submit" class="rounded-lg bg-brand-600 px-4 py-2 text-sm font-semibold text-white hover:bg-brand-700">{{ __('Activate') }}</button>
                        </form>
                    @endif

                    @if ($subscription && ! $subscription->cancelled_at)
                        <form method="POST" action="{{ route('admin.companies.cancel-subscription', $company) }}" onsubmit="return confirm('{{ __('Schedule this subscription to cancel at the end of its current period?') }}')">
                            @csrf
                            <button type="submit" class="rounded-lg border border-red-200 text-red-600 px-4 py-2 text-sm font-semibold hover:bg-red-50">{{ __('Cancel subscription') }}</button>
                        </form>
                    @endif

                    @if ($subscription && $subscription->cancelled_at)
                        <form method="POST" action="{{ route('admin.companies.resume-subscription', $company) }}">
                            @csrf
                            <button type="submit" class="rounded-lg bg-brand-600 px-4 py-2 text-sm font-semibold text-white hover:bg-brand-700">{{ __('Resume subscription') }}</button>
                        </form>
                    @endif
                </div>
            </div>
        </div>
    </div>

    {{-- ============ Billing ============ --}}
    <div x-show="tab === 'billing'" x-cloak class="space-y-6">
        <div class="bg-white rounded-xl border border-slate-100">
            <div class="px-6 py-4 border-b border-slate-100">
                <h3 class="font-semibold text-slate-900">{{ __('Payments & invoices') }}</h3>
            </div>
            @if ($company->payments->isEmpty())
                <p class="px-6 py-8 text-sm text-slate-500">{{ __('No payments yet.') }}</p>
            @else
                <table class="w-full text-sm">
                    <thead>
                        <tr class="text-left text-slate-500 border-b border-slate-100">
                            <th class="px-6 py-3 font-medium">{{ __('Date') }}</th>
                            <th class="px-6 py-3 font-medium">{{ __('Amount') }}</th>
                            <th class="px-6 py-3 font-medium">{{ __('Method') }}</th>
                            <th class="px-6 py-3 font-medium">{{ __('Status') }}</th>
                            <th class="px-6 py-3 font-medium"></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($company->payments as $payment)
                            <tr class="border-b border-slate-50 last:border-0">
                                <td class="px-6 py-3">{{ optional($payment->paid_at)->format('Y-m-d') ?? '—' }}</td>
                                <td class="px-6 py-3">{{ $payment->currency }} {{ number_format($payment->amount, 2) }}</td>
                                <td class="px-6 py-3 text-slate-500">{{ $payment->method ? ucfirst($payment->method) : '—' }}</td>
                                <td class="px-6 py-3">
                                    <span class="{{ match($payment->status) { 'paid' => 'text-emerald-600', 'failed' => 'text-red-600', default => 'text-slate-500' } }}">{{ ucfirst($payment->status) }}</span>
                                </td>
                                <td class="px-6 py-3 text-right">
                                    @if ($payment->status === 'paid')
                                        <form method="POST" action="{{ route('admin.payments.refund', $payment->id) }}" onsubmit="return confirm('{{ __('Mark this payment as refunded?') }}')">
                                            @csrf
                                            <button type="submit" class="text-xs font-semibold text-red-600 hover:underline">{{ __('Mark refunded') }}</button>
                                        </form>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif
        </div>

        <div class="bg-white rounded-xl border border-red-100">
            <div class="px-6 py-4 border-b border-red-100">
                <h3 class="font-semibold text-red-700">{{ __('Failed payments') }}</h3>
            </div>
            @if ($failedPayments->isEmpty())
                <p class="px-6 py-8 text-sm text-slate-500">{{ __('No failed payments.') }}</p>
            @else
                <ul class="divide-y divide-slate-50">
                    @foreach ($failedPayments as $payment)
                        <li class="px-6 py-3 text-sm flex items-center justify-between">
                            <span>{{ $payment->currency }} {{ number_format($payment->amount, 2) }}</span>
                            <span class="text-slate-400 text-xs">{{ optional($payment->created_at)->format('Y-m-d H:i') }}</span>
                        </li>
                    @endforeach
                </ul>
            @endif
        </div>
    </div>

    {{-- ============ ZATCA ============ --}}
    <div x-show="tab === 'zatca'" x-cloak class="space-y-6">
        <div class="grid sm:grid-cols-2 gap-6">
            <div class="bg-white rounded-xl border border-slate-100 p-6">
                <h3 class="font-semibold text-slate-900 mb-4">{{ __('Connection status') }}</h3>
                <dl class="space-y-2 text-sm">
                    <div class="flex justify-between"><dt class="text-slate-500">{{ __('Onboarding') }}</dt><dd class="font-medium">{{ ucfirst(str_replace('_', ' ', $company->zatca_onboarding_status)) }}</dd></div>
                    <div class="flex justify-between"><dt class="text-slate-500">{{ __('Environment') }}</dt><dd class="font-medium">{{ ucfirst($company->zatca_environment) }}</dd></div>
                    <div class="flex justify-between"><dt class="text-slate-500">{{ __('Certificate status') }}</dt><dd class="font-medium">{{ ['production' => __('Production issued'), 'compliance_only' => __('Compliance only'), 'not_issued' => __('Not issued')][$zatca['certificate_status']] }}</dd></div>
                    <div class="flex justify-between"><dt class="text-slate-500">{{ __('Last sync') }}</dt><dd class="font-medium">{{ $company->zatca_last_sync_at?->format('Y-m-d H:i') ?? '—' }}</dd></div>
                    <div class="flex justify-between"><dt class="text-slate-500">{{ __('Last submission') }}</dt><dd class="font-medium">{{ $zatca['last_submission'] ? \Illuminate\Support\Carbon::parse($zatca['last_submission'])->format('Y-m-d H:i') : '—' }}</dd></div>
                </dl>
            </div>
            <div class="bg-white rounded-xl border border-slate-100 p-6">
                <h3 class="font-semibold text-slate-900 mb-4">{{ __('Submissions') }}</h3>
                <dl class="space-y-2 text-sm">
                    <div class="flex justify-between"><dt class="text-slate-500">{{ __('Cleared / reported') }}</dt><dd class="font-medium text-emerald-600">{{ $zatca['cleared'] + $zatca['reported'] }}</dd></div>
                    <div class="flex justify-between"><dt class="text-slate-500">{{ __('Failed submissions') }}</dt><dd class="font-medium {{ $zatca['failed'] > 0 ? 'text-red-600' : '' }}">{{ $zatca['failed'] }}</dd></div>
                </dl>
            </div>
        </div>

        <div class="bg-white rounded-xl border border-slate-100">
            <div class="px-6 py-4 border-b border-slate-100">
                <h3 class="font-semibold text-slate-900">{{ __('Recent failed submissions') }}</h3>
            </div>
            @if ($failedZatcaSubmissions->isEmpty())
                <p class="px-6 py-8 text-sm text-slate-500">{{ __('No failed submissions.') }}</p>
            @else
                <ul class="divide-y divide-slate-50">
                    @foreach ($failedZatcaSubmissions as $log)
                        <li class="px-6 py-3 text-sm flex items-center justify-between">
                            <span class="text-red-600">{{ $log->error_message ?? __('Submission failed') }}</span>
                            <span class="text-slate-400 text-xs">{{ $log->created_at->format('Y-m-d H:i') }}</span>
                        </li>
                    @endforeach
                </ul>
            @endif
        </div>
    </div>

    {{-- ============ Storage ============ --}}
    <div x-show="tab === 'storage'" x-cloak>
        <div class="bg-white rounded-xl border border-slate-100 p-6 max-w-md">
            <h3 class="font-semibold text-slate-900 mb-4">{{ __('Storage') }}</h3>
            <dl class="space-y-2 text-sm mb-4">
                <div class="flex justify-between"><dt class="text-slate-500">{{ __('Used') }}</dt><dd class="font-medium">{{ number_format($storageUsedMb, 1) }} {{ __('MB') }}</dd></div>
                <div class="flex justify-between"><dt class="text-slate-500">{{ __('Limit') }}</dt><dd class="font-medium">{{ $storageLimitMb ? number_format($storageLimitMb).' '.__('MB') : __('Unlimited') }}</dd></div>
            </dl>
            @if ($storageLimitMb)
                @php $pct = min(100, $storageLimitMb > 0 ? ($storageUsedMb / $storageLimitMb * 100) : 0); @endphp
                <div class="h-2 rounded-full bg-slate-100 overflow-hidden">
                    <div class="h-full {{ $pct >= 90 ? 'bg-red-500' : 'bg-brand-600' }}" style="width: {{ $pct }}%"></div>
                </div>
                <p class="mt-1 text-xs text-slate-400">{{ number_format($pct, 1) }}% {{ __('used') }}</p>
            @endif
        </div>
    </div>

    {{-- ============ Activity ============ --}}
    <div x-show="tab === 'activity'" x-cloak>
        <div class="bg-white rounded-xl border border-slate-100">
            <div class="px-6 py-4 border-b border-slate-100">
                <h3 class="font-semibold text-slate-900">{{ __('Company activity') }}</h3>
            </div>
            @if ($auditLogs->isEmpty())
                <p class="px-6 py-8 text-sm text-slate-500">{{ __('No activity recorded yet.') }}</p>
            @else
                <ul class="divide-y divide-slate-50">
                    @foreach ($auditLogs as $log)
                        <li class="px-6 py-3 text-sm flex items-center justify-between gap-4">
                            <span class="text-slate-700">{{ $log->description ?? $log->action }}</span>
                            <span class="text-slate-400 text-xs whitespace-nowrap">{{ $log->admin?->name ?? __('System') }} · {{ $log->created_at->format('Y-m-d H:i') }}</span>
                        </li>
                    @endforeach
                </ul>
            @endif
        </div>
    </div>
</div>
@endsection
