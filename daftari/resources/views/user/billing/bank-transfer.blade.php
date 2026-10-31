@extends('layouts.app')

@section('title', __('Bank transfer instructions'))

@section('content')
<div class="max-w-2xl bg-white rounded-xl border border-slate-100 p-6">
    <h1 class="text-lg font-semibold text-slate-900">{{ __('Complete your payment by bank transfer') }}</h1>
    <p class="text-sm text-slate-500 mt-1">{{ __('Your :plan subscription will be activated once we confirm the transfer has arrived — this usually takes 1-2 business days.', ['plan' => $payment->plan->name]) }}</p>

    <div class="mt-5 rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800">
        {{ __('Awaiting payment confirmation') }}
    </div>

    <div class="mt-5 rounded-lg border border-slate-100 bg-slate-50 p-5 text-sm">
        <dl class="grid gap-2 sm:grid-cols-2">
            <div><dt class="inline text-slate-500">{{ __('Amount') }}: </dt><dd class="inline font-semibold text-slate-900">{{ number_format($payment->amount, 2) }} {{ $payment->currency }}</dd></div>
            <div><dt class="inline text-slate-500">{{ __('Reference') }}: </dt><dd class="inline font-mono font-semibold text-slate-900">PMT-{{ $payment->id }}</dd></div>
            <div><dt class="inline text-slate-500">{{ __('Bank') }}: </dt><dd class="inline font-medium text-slate-800">{{ $bank['bank_name'] ?? '' }}</dd></div>
            <div><dt class="inline text-slate-500">{{ __('Account name') }}: </dt><dd class="inline font-medium text-slate-800">{{ $bank['account_holder_name'] ?? '' }}</dd></div>
            <div class="sm:col-span-2"><dt class="inline text-slate-500">{{ __('IBAN') }}: </dt><dd class="inline font-mono font-medium text-slate-800">{{ $bank['iban'] ?? '' }}</dd></div>
            @if (! empty($bank['account_number']))
                <div><dt class="inline text-slate-500">{{ __('Account number') }}: </dt><dd class="inline font-mono font-medium text-slate-800">{{ $bank['account_number'] }}</dd></div>
            @endif
            @if (! empty($bank['swift_code']))
                <div><dt class="inline text-slate-500">{{ __('SWIFT/BIC') }}: </dt><dd class="inline font-mono font-medium text-slate-800">{{ $bank['swift_code'] }}</dd></div>
            @endif
        </dl>
        <p class="mt-3 text-xs text-slate-500">{{ __('Please include the reference :reference as your payment description so we can match it to your subscription.', ['reference' => 'PMT-'.$payment->id]) }}</p>
    </div>

    <a href="{{ route('app.billing.index') }}" class="mt-6 inline-block rounded-lg border border-slate-200 px-5 py-2.5 text-sm font-semibold text-slate-600 hover:border-slate-300">{{ __('Back to billing') }}</a>
</div>
@endsection
