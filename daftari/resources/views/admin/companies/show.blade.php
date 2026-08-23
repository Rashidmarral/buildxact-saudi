@extends('layouts.admin')

@section('title', $company->name)

@section('content')
<div class="flex flex-wrap items-center justify-between gap-3 mb-6">
    <div>
        <div class="flex items-center gap-2">
            <h2 class="text-lg font-semibold text-slate-900">{{ $company->name }}</h2>
            <span class="inline-flex items-center gap-1.5 rounded-full px-2 py-0.5 text-xs font-semibold {{ $company->status === 'active' ? 'bg-emerald-50 text-emerald-700' : 'bg-slate-100 text-slate-500' }}">
                {{ $company->status === 'active' ? __('Active') : __('Suspended') }}
            </span>
        </div>
        <p class="text-sm text-slate-500">{{ $company->email }} · {{ $company->vat_number ?: __('No VAT number') }}</p>
    </div>
    <div class="flex items-center gap-3">
        <form method="POST" action="{{ route('admin.companies.impersonate', $company) }}">
            @csrf
            <button type="submit" class="inline-flex items-center gap-1.5 rounded-lg border border-slate-200 px-4 py-2 text-sm font-semibold text-slate-600 hover:border-slate-300">
                @include('partials.icon', ['name' => 'log-in', 'class' => 'h-4 w-4'])
                {{ __('Log in as this company') }}
            </button>
        </form>
        @if ($company->status === 'active')
            <form method="POST" action="{{ route('admin.companies.suspend', $company) }}" onsubmit="return confirm('{{ __('Suspend this company?') }}')">
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

<div class="grid md:grid-cols-3 gap-6">
    <div class="bg-white rounded-xl border border-slate-100 p-6">
        <h3 class="font-semibold text-slate-900 mb-4">{{ __('Users') }}</h3>
        <ul class="space-y-2 text-sm">
            @foreach ($company->users as $user)
                <li class="flex justify-between">
                    <span>{{ $user->name }} <span class="text-slate-400">({{ $user->role }})</span></span>
                    <span class="text-slate-500">{{ $user->email }}</span>
                </li>
            @endforeach
        </ul>
    </div>

    <div class="bg-white rounded-xl border border-slate-100 p-6">
        <h3 class="font-semibold text-slate-900 mb-4">{{ __('ZATCA status') }}</h3>
        <dl class="space-y-2 text-sm">
            <div class="flex justify-between"><dt class="text-slate-500">{{ __('Onboarding') }}</dt><dd class="font-medium">{{ ucfirst(str_replace('_', ' ', $company->zatca_onboarding_status)) }}</dd></div>
            <div class="flex justify-between"><dt class="text-slate-500">{{ __('Environment') }}</dt><dd class="font-medium">{{ ucfirst($company->zatca_environment) }}</dd></div>
            <div class="flex justify-between"><dt class="text-slate-500">{{ __('Last sync') }}</dt><dd class="font-medium">{{ $company->zatca_last_sync_at?->format('Y-m-d H:i') ?? '—' }}</dd></div>
            <div class="flex justify-between"><dt class="text-slate-500">{{ __('Cleared / reported') }}</dt><dd class="font-medium text-emerald-600">{{ $zatca['cleared'] + $zatca['reported'] }}</dd></div>
            <div class="flex justify-between"><dt class="text-slate-500">{{ __('Failed') }}</dt><dd class="font-medium {{ $zatca['failed'] > 0 ? 'text-red-600' : '' }}">{{ $zatca['failed'] }}</dd></div>
        </dl>
    </div>

    <div class="bg-white rounded-xl border border-slate-100 p-6">
        <h3 class="font-semibold text-slate-900 mb-4">{{ __('Usage this period') }}</h3>
        @if ($usage)
            <dl class="space-y-2 text-sm">
                @foreach ([__('Invoices') => 'invoices', __('Customers') => 'customers', __('Suppliers') => 'suppliers', __('Users') => 'users', __('Invoice templates') => 'invoice_templates', __('Warehouses') => 'warehouses', __('Bank & cash accounts') => 'bank_accounts', __('Branches') => 'branches'] as $label => $key)
                    <div class="flex justify-between">
                        <dt class="text-slate-500">{{ $label }}</dt>
                        <dd class="font-medium">{{ $usage[$key]['used'] }} / {{ $usage[$key]['limit'] ?? '∞' }}</dd>
                    </div>
                @endforeach
            </dl>
        @else
            <p class="text-sm text-slate-400">{{ __('No active subscription.') }}</p>
        @endif
    </div>
</div>

<div class="mt-6 bg-white rounded-xl border border-slate-100 p-6">
    <div class="flex items-center justify-between">
        <h3 class="font-semibold text-slate-900">{{ __('Subscription') }}</h3>
    </div>
    <ul class="mt-3 space-y-2 text-sm">
        @forelse ($company->subscriptions as $sub)
            <li class="flex justify-between">
                <span>{{ $sub->plan->name }} — {{ ucfirst($sub->status) }}</span>
                <span class="text-slate-500">{{ ucfirst($sub->billing_cycle) }} · {{ __('renews') }} {{ optional($sub->current_period_end)->format('Y-m-d') ?? '—' }}</span>
            </li>
        @empty
            <li class="text-slate-400">{{ __('No subscriptions.') }}</li>
        @endforelse
    </ul>

    <details class="mt-4 group">
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

<div class="mt-6 bg-white rounded-xl border border-slate-100">
    <div class="px-6 py-4 border-b border-slate-100">
        <h3 class="font-semibold text-slate-900">{{ __('Recent payments') }}</h3>
    </div>
    @if ($company->payments->isEmpty())
        <p class="px-6 py-8 text-sm text-slate-500">{{ __('No payments yet.') }}</p>
    @else
        <table class="w-full text-sm">
            <thead>
                <tr class="text-left text-slate-500 border-b border-slate-100">
                    <th class="px-6 py-3 font-medium">{{ __('Date') }}</th>
                    <th class="px-6 py-3 font-medium">{{ __('Amount') }}</th>
                    <th class="px-6 py-3 font-medium">{{ __('Status') }}</th>
                    <th class="px-6 py-3 font-medium"></th>
                </tr>
            </thead>
            <tbody>
                @foreach ($company->payments as $payment)
                    <tr class="border-b border-slate-50 last:border-0">
                        <td class="px-6 py-3">{{ optional($payment->paid_at)->format('Y-m-d') ?? '—' }}</td>
                        <td class="px-6 py-3">SAR {{ number_format($payment->amount, 2) }}</td>
                        <td class="px-6 py-3">{{ ucfirst($payment->status) }}</td>
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

<div class="mt-6 bg-white rounded-xl border border-slate-100">
    <div class="px-6 py-4 border-b border-slate-100">
        <h3 class="font-semibold text-slate-900">{{ __('Admin activity on this company') }}</h3>
    </div>
    @if ($auditLogs->isEmpty())
        <p class="px-6 py-8 text-sm text-slate-500">{{ __('No admin activity recorded yet.') }}</p>
    @else
        <ul class="divide-y divide-slate-50">
            @foreach ($auditLogs as $log)
                <li class="px-6 py-3 text-sm flex items-center justify-between">
                    <span class="text-slate-700">{{ $log->description ?? $log->action }}</span>
                    <span class="text-slate-400 text-xs">{{ $log->admin?->name ?? __('System') }} · {{ $log->created_at->format('Y-m-d H:i') }}</span>
                </li>
            @endforeach
        </ul>
    @endif
</div>
@endsection
