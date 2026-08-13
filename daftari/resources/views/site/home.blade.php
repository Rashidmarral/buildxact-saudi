@extends('layouts.site')

@section('title', __('Daftari — Saudi VAT Invoicing & Accounting'))

@section('content')
<section class="mx-auto max-w-7xl px-6 pt-16 pb-20 grid lg:grid-cols-2 gap-12 items-center">
    <div>
        <span class="inline-block rounded-full bg-brand-50 text-brand-700 text-xs font-semibold px-3 py-1">{{ __('ZATCA-ready e-invoicing') }}</span>
        <h1 class="mt-5 text-4xl md:text-5xl font-extrabold tracking-tight text-slate-900">
            {{ __('VAT invoicing & accounting, built for Saudi businesses') }}
        </h1>
        <p class="mt-5 text-lg text-slate-600">
            {{ __('Daftari is a subscription-based accounting platform for Saudi companies — create compliant VAT invoices with QR codes, track expenses, manage clients, and see your VAT position at a glance. Bilingual Arabic/English, priced in SAR.') }}
        </p>
        <div class="mt-8 flex flex-wrap gap-4">
            <a href="{{ route('register') }}" class="rounded-lg bg-brand-600 px-6 py-3 font-semibold text-white hover:bg-brand-700">{{ __('Start your free trial') }}</a>
            <a href="{{ route('pricing') }}" class="rounded-lg border border-slate-200 px-6 py-3 font-semibold text-slate-700 hover:border-brand-300">{{ __('See pricing') }}</a>
        </div>
        <p class="mt-4 text-sm text-slate-400">{{ __('No credit card required · :days-day free trial', ['days' => config('daftari.trial_days')]) }}</p>
    </div>
    <div class="bg-white rounded-2xl border border-slate-100 shadow-xl p-6">
        <div class="rounded-xl bg-slate-900 text-white p-5">
            <div class="flex items-center justify-between text-sm text-slate-300">
                <span>{{ __('Tax Invoice') }} · INV-00042</span>
                <span class="rounded-full bg-brand-600 px-2 py-0.5 text-xs">{{ __('Paid') }}</span>
            </div>
            <div class="mt-4 text-2xl font-bold">SAR 11,500.00</div>
            <div class="mt-1 text-xs text-slate-400">{{ __('VAT (15%) included') }}: SAR 1,500.00</div>
            <div class="mt-4 h-24 w-24 bg-white rounded grid grid-cols-4 grid-rows-4 gap-0.5 p-2">
                @for ($i = 0; $i < 16; $i++)
                    <div class="{{ rand(0, 1) ? 'bg-slate-900' : 'bg-white' }}"></div>
                @endfor
            </div>
        </div>
        <div class="mt-4 grid grid-cols-3 gap-3 text-center text-xs text-slate-500">
            <div class="rounded-lg bg-slate-50 py-3"><div class="text-lg font-bold text-slate-800">128</div>{{ __('Invoices') }}</div>
            <div class="rounded-lg bg-slate-50 py-3"><div class="text-lg font-bold text-slate-800">SAR 342K</div>{{ __('Revenue') }}</div>
            <div class="rounded-lg bg-slate-50 py-3"><div class="text-lg font-bold text-slate-800">SAR 51K</div>{{ __('VAT collected') }}</div>
        </div>
    </div>
</section>

<section class="bg-slate-50 py-20">
    <div class="mx-auto max-w-7xl px-6">
        <h2 class="text-2xl md:text-3xl font-bold text-slate-900 text-center">{{ __('Everything you need to run compliant, organized books') }}</h2>
        <div class="mt-12 grid md:grid-cols-3 gap-8">
            @foreach ([
                ['icon' => '🧾', 'title' => __('ZATCA-style QR invoices'), 'desc' => __('Every tax invoice carries a scannable QR code encoding seller, VAT number, timestamp, and totals.')],
                ['icon' => '📈', 'title' => __('One-click VAT reports'), 'desc' => __('See output VAT, input VAT, and net VAT due for any period — ready for your return.')],
                ['icon' => '👥', 'title' => __('Client & item catalog'), 'desc' => __('Save clients and price lists once, reuse them on every invoice.')],
                ['icon' => '💳', 'title' => __('Expense tracking'), 'desc' => __('Log purchases and reclaimable VAT by category, vendor, and date.')],
                ['icon' => '🌐', 'title' => __('Bilingual, RTL-ready'), 'desc' => __('Full Arabic and English interface with proper right-to-left layout.')],
                ['icon' => '🧑‍🤝‍🧑', 'title' => __('Team access'), 'desc' => __('Invite your bookkeeper or staff with role-based access to your books.')],
            ] as $feature)
                <div class="bg-white rounded-xl border border-slate-100 p-6">
                    <div class="text-3xl">{{ $feature['icon'] }}</div>
                    <h3 class="mt-4 font-semibold text-slate-900">{{ $feature['title'] }}</h3>
                    <p class="mt-2 text-sm text-slate-500">{{ $feature['desc'] }}</p>
                </div>
            @endforeach
        </div>
    </div>
</section>

<section class="mx-auto max-w-7xl px-6 py-20">
    <div class="rounded-2xl bg-brand-600 px-8 py-14 text-center text-white">
        <h2 class="text-2xl md:text-3xl font-bold">{{ __('Ready to simplify your VAT invoicing?') }}</h2>
        <p class="mt-3 text-brand-50">{{ __('Join Saudi businesses managing their invoicing and VAT with Daftari.') }}</p>
        <a href="{{ route('register') }}" class="mt-6 inline-block rounded-lg bg-white px-6 py-3 font-semibold text-brand-700 hover:bg-brand-50">{{ __('Start your free trial') }}</a>
    </div>
</section>
@endsection
