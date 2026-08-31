@extends('layouts.admin')

@section('title', __('Payment #:id', ['id' => $payment->id]))

@section('content')
<div x-data="{ showResponse: false, showRefund: false }">
    <div class="flex flex-wrap items-center justify-between gap-3 mb-6">
        <div>
            <div class="flex items-center gap-2">
                <h2 class="text-lg font-semibold text-slate-900">{{ __('Payment #:id', ['id' => $payment->id]) }}</h2>
                <span class="inline-flex items-center gap-1.5 rounded-full px-2 py-0.5 text-xs font-semibold {{ $payment->statusBadgeClasses() }}">{{ $payment->statusLabel() }}</span>
            </div>
            <p class="text-sm text-slate-500">{{ $payment->company?->name }} · {{ $payment->currency }} {{ number_format($payment->amount, 2) }}</p>
        </div>
        <div class="flex flex-wrap items-center gap-3">
            @if ($payment->status === 'paid')
                <a href="{{ route('admin.payments.receipt', $payment) }}" class="rounded-lg border border-slate-200 px-4 py-2 text-sm font-semibold text-slate-600 hover:border-slate-300">{{ __('Download receipt') }}</a>
            @endif
            @if ($payment->status === 'pending' && $payment->method === 'bank_transfer')
                <form method="POST" action="{{ route('admin.payments.confirm-bank-transfer', $payment) }}" onsubmit="return confirm('{{ __('Confirm this bank transfer arrived and activate the subscription?') }}')">
                    @csrf
                    <button type="submit" class="rounded-lg bg-emerald-600 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-700">{{ __('Confirm transfer') }}</button>
                </form>
            @endif
            @if ($payment->status === 'failed' && $payment->subscription_id && in_array($payment->method, \App\Models\PaymentGateway::PROVIDERS, true))
                <form method="POST" action="{{ route('admin.payments.retry', $payment) }}" onsubmit="return confirm('{{ __('Start a new payment attempt for this subscription?') }}')">
                    @csrf
                    <button type="submit" class="rounded-lg border border-slate-200 px-4 py-2 text-sm font-semibold text-slate-600 hover:border-slate-300">{{ __('Retry failed payment') }}</button>
                </form>
            @endif
            @if (in_array($payment->status, ['paid', 'partially_refunded'], true) && $payment->remainingRefundable() > 0)
                <button type="button" @click="showRefund = ! showRefund" class="rounded-lg border border-red-200 text-red-600 px-4 py-2 text-sm font-semibold hover:bg-red-50">{{ __('Refund') }}</button>
            @endif
        </div>
    </div>

    <div x-show="showRefund" x-cloak class="mb-6 bg-white rounded-xl border border-red-100 p-6">
        <h3 class="font-semibold text-slate-900 mb-2">{{ __('Refund payment') }}</h3>
        <p class="text-sm text-slate-500 mb-4">{{ __('Remaining refundable') }}: {{ $payment->currency }} {{ number_format($payment->remainingRefundable(), 2) }}. {{ __('This only updates Daftari\'s own records — no gateway is charged.') }}</p>
        <form method="POST" action="{{ route('admin.payments.refund', $payment) }}" class="grid sm:grid-cols-3 gap-3" onsubmit="return confirm('{{ __('Record this refund?') }}')">
            @csrf
            <div>
                <label class="block text-xs font-medium text-slate-500 mb-1">{{ __('Amount (leave blank for full refund)') }}</label>
                <input type="number" step="0.01" min="0.01" max="{{ $payment->remainingRefundable() }}" name="amount" class="w-full rounded-lg border border-slate-200 text-sm">
            </div>
            <div class="sm:col-span-2">
                <label class="block text-xs font-medium text-slate-500 mb-1">{{ __('Reason (optional)') }}</label>
                <input type="text" name="reason" class="w-full rounded-lg border border-slate-200 text-sm">
            </div>
            <div class="sm:col-span-3">
                <button type="submit" class="rounded-lg bg-red-600 px-4 py-2 text-sm font-semibold text-white hover:bg-red-700">{{ __('Confirm refund') }}</button>
            </div>
        </form>
    </div>

    <div class="grid md:grid-cols-2 gap-6">
        <div class="bg-white rounded-xl border border-slate-100 p-6">
            <h3 class="font-semibold text-slate-900 mb-4">{{ __('Payment information') }}</h3>
            <dl class="space-y-2 text-sm">
                <div class="flex justify-between"><dt class="text-slate-500">{{ __('Company') }}</dt><dd class="font-medium"><a href="{{ route('admin.companies.show', $payment->company_id) }}" class="text-brand-700 hover:underline">{{ $payment->company?->name }}</a></dd></div>
                <div class="flex justify-between"><dt class="text-slate-500">{{ __('Customer') }}</dt><dd class="font-medium">{{ $owner?->name ?? '—' }} @if($owner)<span class="text-slate-400">({{ $owner->email }})</span>@endif</dd></div>
                <div class="flex justify-between"><dt class="text-slate-500">{{ __('Plan') }}</dt><dd class="font-medium">{{ $payment->plan->name ?? '—' }}</dd></div>
                <div class="flex justify-between"><dt class="text-slate-500">{{ __('Amount') }}</dt><dd class="font-medium">{{ $payment->currency }} {{ number_format($payment->amount, 2) }}</dd></div>
                <div class="flex justify-between"><dt class="text-slate-500">{{ __('Gateway') }}</dt><dd class="font-medium">{{ $payment->method ? ucfirst($payment->method) : '—' }}</dd></div>
                <div class="flex justify-between"><dt class="text-slate-500">{{ __('Transaction ID') }}</dt><dd class="font-medium font-mono text-xs">{{ $payment->reference ?: '—' }}</dd></div>
                <div class="flex justify-between"><dt class="text-slate-500">{{ __('Payment date') }}</dt><dd class="font-medium">{{ optional($payment->paid_at)->format('Y-m-d H:i') ?? '—' }}</dd></div>
                @if ($payment->refunded_amount)
                    <div class="flex justify-between"><dt class="text-slate-500">{{ __('Refunded') }}</dt><dd class="font-medium text-orange-600">{{ $payment->currency }} {{ number_format($payment->refunded_amount, 2) }}</dd></div>
                    @if ($payment->refund_reason)
                        <div class="flex justify-between"><dt class="text-slate-500">{{ __('Refund reason') }}</dt><dd class="font-medium">{{ $payment->refund_reason }}</dd></div>
                    @endif
                @endif
            </dl>
        </div>

        <div class="bg-white rounded-xl border border-slate-100 p-6">
            <h3 class="font-semibold text-slate-900 mb-4">{{ __('Related subscription') }}</h3>
            @if ($payment->subscription)
                <dl class="space-y-2 text-sm">
                    <div class="flex justify-between"><dt class="text-slate-500">{{ __('Plan') }}</dt><dd class="font-medium">{{ $payment->subscription->plan->name ?? '—' }}</dd></div>
                    <div class="flex justify-between"><dt class="text-slate-500">{{ __('Status') }}</dt><dd class="font-medium">{{ $payment->subscription->statusLabel() }}</dd></div>
                    <div class="flex justify-between"><dt class="text-slate-500">{{ __('Billing cycle') }}</dt><dd class="font-medium">{{ ucfirst($payment->subscription->billing_cycle) }}</dd></div>
                    <div class="flex justify-between"><dt class="text-slate-500">{{ __('Current period end') }}</dt><dd class="font-medium">{{ optional($payment->subscription->current_period_end)->format('Y-m-d') ?? '—' }}</dd></div>
                </dl>
            @else
                <p class="text-sm text-slate-400">{{ __('No related subscription.') }}</p>
            @endif

            <h3 class="font-semibold text-slate-900 mt-6 mb-2">{{ __('Related invoice') }}</h3>
            @if ($payment->status === 'paid')
                <a href="{{ route('admin.payments.receipt', $payment) }}" class="text-sm font-semibold text-brand-700 hover:underline">{{ __('Download receipt PDF') }}</a>
            @else
                <p class="text-sm text-slate-400">{{ __('No receipt — this payment was never completed.') }}</p>
            @endif
        </div>
    </div>

    <div class="mt-6 bg-white rounded-xl border border-slate-100 p-6">
        <div class="flex items-center justify-between mb-4">
            <h3 class="font-semibold text-slate-900">{{ __('Gateway response') }}</h3>
            @if ($payment->transaction)
                <button type="button" @click="showResponse = ! showResponse" class="text-sm font-semibold text-brand-700 hover:underline">{{ __('View gateway response') }}</button>
            @endif
        </div>
        @if (! $payment->transaction)
            <p class="text-sm text-slate-400">{{ __('No gateway transaction is linked to this payment (bank transfer or manual entry).') }}</p>
        @else
            <div x-show="showResponse" x-cloak class="space-y-4">
                <div>
                    <p class="text-xs font-semibold uppercase text-slate-500 mb-1">{{ __('Provider response') }}</p>
                    <pre class="rounded-lg bg-slate-900 text-slate-100 text-xs p-4 overflow-x-auto">{{ json_encode($payment->transaction->raw_response, JSON_PRETTY_PRINT) }}</pre>
                </div>
                @if ($payment->transaction->raw_webhook)
                    <div>
                        <p class="text-xs font-semibold uppercase text-slate-500 mb-1">{{ __('Last webhook payload') }}</p>
                        <pre class="rounded-lg bg-slate-900 text-slate-100 text-xs p-4 overflow-x-auto">{{ json_encode($payment->transaction->raw_webhook, JSON_PRETTY_PRINT) }}</pre>
                    </div>
                @endif
            </div>
        @endif
    </div>

    <div class="grid md:grid-cols-2 gap-6 mt-6">
        <div class="bg-white rounded-xl border border-slate-100">
            <div class="px-6 py-4 border-b border-slate-100"><h3 class="font-semibold text-slate-900">{{ __('Timeline') }}</h3></div>
            @if (empty($timeline))
                <p class="px-6 py-8 text-sm text-slate-500">{{ __('No events yet.') }}</p>
            @else
                <ul class="divide-y divide-slate-50">
                    @foreach ($timeline as $event)
                        <li class="px-6 py-3 text-sm flex items-center justify-between gap-4">
                            <span class="text-slate-700">{{ $event['label'] }}</span>
                            <span class="text-slate-400 text-xs whitespace-nowrap">{{ $event['at']->format('Y-m-d H:i') }}</span>
                        </li>
                    @endforeach
                </ul>
            @endif
        </div>

        <div class="bg-white rounded-xl border border-slate-100">
            <div class="px-6 py-4 border-b border-slate-100"><h3 class="font-semibold text-slate-900">{{ __('Audit history') }}</h3></div>
            @if ($auditLogs->isEmpty())
                <p class="px-6 py-8 text-sm text-slate-500">{{ __('No admin actions recorded on this payment.') }}</p>
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
