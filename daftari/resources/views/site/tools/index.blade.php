@extends('layouts.site')

@section('title', __('Free Tools & Templates') . ' · Daftari')

@section('content')
<section class="mx-auto max-w-4xl px-6 pt-16 pb-8 text-center">
    <span class="inline-block rounded-full bg-brand-50 text-brand-700 text-xs font-semibold px-3 py-1">{{ __('Free · no sign-up · runs in your browser') }}</span>
    <h1 class="mt-5 text-4xl font-extrabold text-slate-900">{{ __('Free accounting tools') }}</h1>
    <p class="mt-4 text-lg text-slate-600">{{ __('Financial calculators and ready-to-use document templates for everyday Saudi business tasks — free, with no registration required.') }}</p>
</section>

<section class="mx-auto max-w-6xl px-6 pb-16">
    <h2 class="text-2xl font-bold text-slate-900 mb-2">{{ __('Financial calculators') }}</h2>
    <p class="text-sm text-slate-500 mb-6">{{ __('VAT, Zakat, GOSI, end-of-service, and other calculations Saudi businesses handle every day.') }}</p>
    <div class="grid sm:grid-cols-2 lg:grid-cols-4 gap-5">
        @foreach ([
            ['icon' => '➗', 't' => __('Percentage calculator'), 'd' => __('Work out a percentage of an amount, an increase, decrease, or profit margin.'), 'r' => 'tools.percentage'],
            ['icon' => '🏷️', 't' => __('Discount calculator'), 'd' => __('The price after a discount, and the discount rate, with a VAT option.'), 'r' => 'tools.discount'],
            ['icon' => '🧾', 't' => __('VAT calculator'), 'd' => __('Add or extract 15% VAT from any amount.'), 'r' => 'tools.vat'],
            ['icon' => '🕌', 't' => __('Zakat calculator'), 'd' => __('Estimate Zakat on wealth or business assets, by Hijri or Gregorian year.'), 'r' => 'tools.zakat'],
            ['icon' => '🛡️', 't' => __('GOSI calculator'), 'd' => __('Estimate employee and employer contributions.'), 'r' => 'tools.gosi'],
            ['icon' => '📆', 't' => __('End-of-service calculator'), 'd' => __('Estimate the award by length of service and reason for leaving.'), 'r' => 'tools.end-of-service'],
            ['icon' => '⚠️', 't' => __('ZATCA penalty estimator'), 'd' => __('A rough estimate of e-invoicing non-compliance penalty ranges.'), 'r' => 'tools.zatca-penalty'],
        ] as $tool)
            <a href="{{ route($tool['r']) }}" class="rounded-xl border border-slate-100 bg-white p-5 hover:border-brand-300 hover:shadow-sm transition">
                <div class="text-2xl">{{ $tool['icon'] }}</div>
                <h3 class="mt-3 font-semibold text-slate-900 text-sm">{{ $tool['t'] }}</h3>
                <p class="mt-1 text-xs text-slate-500">{{ $tool['d'] }}</p>
            </a>
        @endforeach
    </div>
</section>

<section class="bg-slate-50 py-16">
    <div class="mx-auto max-w-6xl px-6">
        <h2 class="text-2xl font-bold text-slate-900 mb-2">{{ __('Free invoice & document templates') }}</h2>
        <p class="text-sm text-slate-500 mb-6">{{ __('Create invoices, quotations, and payment vouchers, ready to print or download.') }}</p>
        <div class="grid sm:grid-cols-2 lg:grid-cols-4 gap-5">
            @foreach ([
                ['icon' => '🧾', 't' => __('Free invoice generator'), 'd' => __('A ready invoice with VAT calculated, to print or save.'), 'r' => 'tools.invoice-generator'],
                ['icon' => '📋', 't' => __('Free quotation generator'), 'd' => __('A professional quotation, ready to send.'), 'r' => 'tools.quotation-generator'],
                ['icon' => '🧾', 't' => __('Receipt voucher template'), 'd' => __('A receipt voucher ready to print in a minute.'), 'r' => 'tools.receipt-voucher'],
                ['icon' => '💵', 't' => __('Payment voucher template'), 'd' => __('A cash payment voucher with the amount in words.'), 'r' => 'tools.payment-voucher'],
            ] as $tool)
                <a href="{{ route($tool['r']) }}" class="rounded-xl border border-slate-100 bg-white p-5 hover:border-brand-300 hover:shadow-sm transition">
                    <div class="text-2xl">{{ $tool['icon'] }}</div>
                    <h3 class="mt-3 font-semibold text-slate-900 text-sm">{{ $tool['t'] }}</h3>
                    <p class="mt-1 text-xs text-slate-500">{{ $tool['d'] }}</p>
                </a>
            @endforeach
        </div>
    </div>
</section>

@include('site.tools.partials.cta')
@endsection
