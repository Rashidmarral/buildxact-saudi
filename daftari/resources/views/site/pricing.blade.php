@extends('layouts.site')

@section('title', __('Pricing') . ' · Daftari')

@section('content')
<section class="mx-auto max-w-4xl px-6 py-16 text-center">
    <h1 class="text-4xl font-extrabold text-slate-900">{{ __('Simple, transparent pricing') }}</h1>
    <p class="mt-4 text-lg text-slate-600">{{ __('All prices in SAR. Cancel anytime. Every plan starts with a :days-day free trial.', ['days' => config('daftari.trial_days')]) }}</p>
</section>

<section class="mx-auto max-w-6xl px-6 pb-24 grid md:grid-cols-3 gap-8">
    @foreach ($plans as $plan)
        <div class="rounded-2xl border {{ $loop->index === 1 ? 'border-brand-500 shadow-lg ring-1 ring-brand-500' : 'border-slate-200' }} bg-white p-8 flex flex-col">
            @if ($loop->index === 1)
                <span class="self-start mb-3 rounded-full bg-brand-600 text-white text-xs font-semibold px-3 py-1">{{ __('Most popular') }}</span>
            @endif
            <h3 class="text-xl font-bold text-slate-900">{{ app()->getLocale() === 'ar' && $plan->name_ar ? $plan->name_ar : $plan->name }}</h3>
            <div class="mt-4">
                <span class="text-3xl font-extrabold text-slate-900">SAR {{ number_format($plan->price_monthly, 0) }}</span>
                <span class="text-slate-500 text-sm">/{{ __('month') }}</span>
            </div>
            <p class="text-xs text-slate-400 mt-1">{{ __('or SAR :price/year, billed annually', ['price' => number_format($plan->price_yearly, 0)]) }}</p>
            <ul class="mt-6 space-y-3 text-sm text-slate-600 flex-1">
                <li class="flex items-center gap-2"><span class="text-brand-600">✓</span>{{ $plan->max_users ? __(':count team members', ['count' => $plan->max_users]) : __('Unlimited team members') }}</li>
                <li class="flex items-center gap-2"><span class="text-brand-600">✓</span>{{ $plan->max_invoices_per_month ? __(':count invoices/month', ['count' => $plan->max_invoices_per_month]) : __('Unlimited invoices') }}</li>
                @foreach (($plan->features ?? []) as $feature)
                    <li class="flex items-center gap-2"><span class="text-brand-600">✓</span>{{ $feature }}</li>
                @endforeach
            </ul>
            <a href="{{ route('register') }}" class="mt-8 block text-center rounded-lg {{ $loop->index === 1 ? 'bg-brand-600 text-white hover:bg-brand-700' : 'border border-slate-200 text-slate-700 hover:border-brand-300' }} px-6 py-3 font-semibold">
                {{ __('Start free trial') }}
            </a>
        </div>
    @endforeach
</section>

<section class="mx-auto max-w-4xl px-6 pb-24">
    <h2 class="text-2xl font-bold text-slate-900 text-center">{{ __('Frequently asked questions') }}</h2>
    <div class="mt-8 space-y-6">
        @foreach ([
            [__('Do I need a credit card to start the trial?'), __('No — start your :days-day trial with just an email address. Add billing details only when you choose a plan.', ['days' => config('daftari.trial_days')])],
            [__('Can I change plans later?'), __('Yes, upgrade or downgrade anytime from your Billing page. Changes apply to your next billing cycle.')],
            [__('Is VAT handled automatically?'), __('Daftari calculates 15% VAT per line item by default and lets you override the rate per item where needed, and totals it into a VAT report.')],
            [__('What payment methods are supported?'), __('We support major Saudi payment gateways for subscription billing; contact us if you need a specific method.')],
        ] as [$q, $a])
            <div class="border-b border-slate-100 pb-6">
                <h3 class="font-semibold text-slate-900">{{ $q }}</h3>
                <p class="mt-2 text-sm text-slate-600">{{ $a }}</p>
            </div>
        @endforeach
    </div>
</section>
@endsection
