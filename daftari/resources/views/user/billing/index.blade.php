@extends('layouts.app')

@section('title', __('Billing'))

@section('content')
<div class="bg-white rounded-xl border border-slate-100 p-6 mb-8">
    <h2 class="font-semibold text-slate-900 mb-3">{{ __('Current subscription') }}</h2>
    @if ($subscription)
        <div class="flex items-center justify-between">
            <div>
                <p class="text-lg font-bold text-slate-900">{{ $subscription->plan->name }}</p>
                <p class="text-sm text-slate-500">
                    {{ $subscription->isTrial() ? __('Trial') : ucfirst($subscription->status) }}
                    · {{ ucfirst($subscription->billing_cycle) }}
                    @if ($subscription->current_period_end)
                        · {{ __('Renews') }} {{ $subscription->current_period_end->format('Y-m-d') }}
                    @endif
                </p>
            </div>
        </div>
    @else
        <p class="text-sm text-slate-500">{{ __('No active subscription.') }}</p>
    @endif
</div>

<div class="grid md:grid-cols-3 gap-6 mb-8">
    @foreach ($plans as $plan)
        <div class="bg-white rounded-xl border {{ $subscription && $subscription->plan_id === $plan->id ? 'border-brand-500 ring-1 ring-brand-500' : 'border-slate-100' }} p-6">
            <h3 class="font-bold text-slate-900">{{ $plan->name }}</h3>
            <p class="mt-2 text-2xl font-extrabold text-slate-900">SAR {{ number_format($plan->price_monthly, 0) }}<span class="text-sm font-normal text-slate-500">/{{ __('month') }}</span></p>
            <p class="text-xs text-slate-400">SAR {{ number_format($plan->price_yearly, 0) }}/{{ __('year') }}</p>
            <form method="POST" action="{{ route('app.billing.upgrade') }}" class="mt-4 space-y-2">
                @csrf
                <input type="hidden" name="plan_id" value="{{ $plan->id }}">
                <select name="billing_cycle" class="w-full rounded-lg border border-slate-200 text-sm focus:border-brand-500 focus:ring-brand-500">
                    <option value="monthly">{{ __('Monthly') }}</option>
                    <option value="yearly">{{ __('Yearly') }}</option>
                </select>
                <button type="submit" class="w-full rounded-lg {{ $subscription && $subscription->plan_id === $plan->id ? 'bg-slate-100 text-slate-500' : 'bg-brand-600 text-white hover:bg-brand-700' }} px-4 py-2 text-sm font-semibold">
                    {{ $subscription && $subscription->plan_id === $plan->id ? __('Current plan') : __('Choose plan') }}
                </button>
            </form>
        </div>
    @endforeach
</div>

<div class="bg-white rounded-xl border border-slate-100">
    <div class="px-6 py-4 border-b border-slate-100">
        <h2 class="font-semibold text-slate-900">{{ __('Payment history') }}</h2>
    </div>
    @if ($payments->isEmpty())
        <p class="px-6 py-8 text-sm text-slate-500">{{ __('No payments yet.') }}</p>
    @else
        <table class="w-full text-sm">
            <thead>
                <tr class="text-left text-slate-500 border-b border-slate-100">
                    <th class="px-6 py-3 font-medium">{{ __('Date') }}</th>
                    <th class="px-6 py-3 font-medium">{{ __('Plan') }}</th>
                    <th class="px-6 py-3 font-medium">{{ __('Amount') }}</th>
                    <th class="px-6 py-3 font-medium">{{ __('Status') }}</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($payments as $payment)
                    <tr class="border-b border-slate-50 last:border-0">
                        <td class="px-6 py-3">{{ optional($payment->paid_at)->format('Y-m-d') ?? '—' }}</td>
                        <td class="px-6 py-3">{{ $payment->plan->name ?? '—' }}</td>
                        <td class="px-6 py-3">SAR {{ number_format($payment->amount, 2) }}</td>
                        <td class="px-6 py-3">{{ ucfirst($payment->status) }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif
</div>
<div class="mt-4">{{ $payments->links() }}</div>
@endsection
