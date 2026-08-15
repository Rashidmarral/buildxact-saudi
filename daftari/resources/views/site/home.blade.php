@extends('layouts.site')

@section('title', __('Daftari — Saudi VAT Invoicing & Accounting'))

@section('content')
<section class="relative overflow-hidden">
    <div class="bg-grid pointer-events-none absolute inset-0 [mask-image:radial-gradient(ellipse_60%_60%_at_50%_0%,black,transparent)]"></div>
    <div class="pointer-events-none absolute -top-24 start-1/2 h-96 w-96 -translate-x-1/2 rounded-full bg-brand-200/40 blur-3xl"></div>

    <div class="relative mx-auto grid max-w-7xl items-center gap-12 px-6 pb-20 pt-16 lg:grid-cols-2 lg:pt-24">
        <div class="animate-fade-up">
            <span class="inline-flex items-center gap-1.5 rounded-full bg-brand-50 px-3 py-1 text-xs font-semibold text-brand-700 ring-1 ring-inset ring-brand-100">
                @include('partials.icon', ['name' => 'sparkle', 'class' => 'h-3.5 w-3.5'])
                {{ __('ZATCA-ready e-invoicing') }}
            </span>
            <h1 class="mt-5 text-4xl font-extrabold tracking-tight text-slate-900 md:text-5xl">
                {{ __('VAT invoicing & accounting,') }} <span class="text-gradient">{{ __('built for Saudi businesses') }}</span>
            </h1>
            <p class="mt-5 text-lg text-slate-600">
                {{ __('Daftari is a subscription-based accounting platform for Saudi companies — create compliant VAT invoices with QR codes, track expenses, manage clients, and see your VAT position at a glance. Bilingual Arabic/English, priced in SAR.') }}
            </p>
            <div class="mt-8 flex flex-wrap gap-4">
                <a href="{{ route('register') }}" class="btn-shine rounded-lg bg-brand-600 px-6 py-3 font-semibold text-white shadow-card transition-all hover:-translate-y-0.5 hover:bg-brand-700 hover:shadow-card-hover">{{ __('Start your free trial') }}</a>
                <a href="{{ route('pricing') }}" class="rounded-lg border border-slate-200 bg-white px-6 py-3 font-semibold text-slate-700 transition-all hover:-translate-y-0.5 hover:border-brand-300 hover:shadow-soft">{{ __('See pricing') }}</a>
            </div>
            <p class="mt-4 text-sm text-slate-400">{{ __('No credit card required · :days-day free trial', ['days' => config('daftari.trial_days')]) }}</p>
        </div>

        <div class="relative animate-fade-up [animation-delay:150ms]">
            <div class="animate-float rounded-2xl border border-slate-100 bg-white p-6 shadow-card-hover">
                <div class="rounded-xl bg-gradient-to-br from-slate-900 to-slate-800 p-5 text-white">
                    <div class="flex items-center justify-between text-sm text-slate-300">
                        <span>{{ __('Tax Invoice') }} · INV-00042</span>
                        <span class="rounded-full bg-brand-500/90 px-2 py-0.5 text-xs">{{ __('Paid') }}</span>
                    </div>
                    <div class="mt-4 text-2xl font-bold">SAR 11,500.00</div>
                    <div class="mt-1 text-xs text-slate-400">{{ __('VAT (15%) included') }}: SAR 1,500.00</div>
                    <div class="mt-4 grid h-24 w-24 grid-cols-4 grid-rows-4 gap-0.5 rounded bg-white p-2">
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
            <div class="absolute -bottom-4 -start-4 -z-10 h-full w-full rounded-2xl bg-brand-100/60 sm:-bottom-6 sm:-start-6"></div>
        </div>
    </div>
</section>

<section class="border-y border-slate-100 bg-white py-10">
    <div class="mx-auto max-w-7xl px-6">
        <p class="text-center text-xs font-semibold uppercase tracking-wider text-slate-400">{{ __('Built for how Saudi businesses actually work') }}</p>
        <div class="mt-6 grid grid-cols-2 gap-6 text-center sm:grid-cols-4">
            @foreach ([
                [__('VAT rate'), '15%'],
                [__('QR code'), __('On every invoice')],
                [__('Languages'), __('Arabic & English')],
                [__('Currency'), 'SAR'],
            ] as [$label, $value])
                <div>
                    <div class="text-xl font-bold text-slate-900">{{ $value }}</div>
                    <div class="text-xs text-slate-500">{{ $label }}</div>
                </div>
            @endforeach
        </div>
    </div>
</section>

<section class="bg-slate-50 py-20">
    <div class="mx-auto max-w-7xl px-6">
        <div x-data x-reveal class="text-center">
            <h2 class="text-2xl font-bold text-slate-900 md:text-3xl">{{ __('Everything you need to run compliant, organized books') }}</h2>
        </div>
        <div class="mt-12 grid gap-6 md:grid-cols-3">
            @foreach ([
                ['icon' => 'zatca', 'title' => __('ZATCA-style QR invoices'), 'desc' => __('Every tax invoice carries a scannable QR code encoding seller, VAT number, timestamp, and totals.')],
                ['icon' => 'trend-up', 'title' => __('One-click VAT reports'), 'desc' => __('See output VAT, input VAT, and net VAT due for any period — ready for your return.')],
                ['icon' => 'team', 'title' => __('Client & item catalog'), 'desc' => __('Save clients and price lists once, reuse them on every invoice.')],
                ['icon' => 'billing', 'title' => __('Expense tracking'), 'desc' => __('Log purchases and reclaimable VAT by category, vendor, and date.')],
                ['icon' => 'globe', 'title' => __('Bilingual, RTL-ready'), 'desc' => __('Full Arabic and English interface with proper right-to-left layout.')],
                ['icon' => 'shield', 'title' => __('Team access'), 'desc' => __('Invite your bookkeeper or staff with role-based access to your books.')],
            ] as $i => $feature)
                <div x-data x-reveal style="animation-delay: {{ $i * 90 }}ms" class="card-hover rounded-2xl border border-slate-100 bg-white p-6">
                    <div class="inline-flex h-11 w-11 items-center justify-center rounded-xl bg-gradient-to-br from-brand-500 to-brand-700 text-white shadow-soft">
                        @include('partials.icon', ['name' => $feature['icon'], 'class' => 'h-5 w-5'])
                    </div>
                    <h3 class="mt-4 font-semibold text-slate-900">{{ $feature['title'] }}</h3>
                    <p class="mt-2 text-sm text-slate-500">{{ $feature['desc'] }}</p>
                </div>
            @endforeach
        </div>
    </div>
</section>

<section class="mx-auto max-w-7xl px-6 py-20">
    <div x-data x-reveal class="relative overflow-hidden rounded-2xl bg-gradient-to-br from-brand-600 to-brand-800 px-8 py-14 text-center text-white shadow-card-hover">
        <div class="pointer-events-none absolute -end-16 -top-16 h-64 w-64 rounded-full bg-white/10 blur-2xl"></div>
        <div class="pointer-events-none absolute -bottom-16 -start-16 h-64 w-64 rounded-full bg-white/10 blur-2xl"></div>
        <h2 class="relative text-2xl font-bold md:text-3xl">{{ __('Ready to simplify your VAT invoicing?') }}</h2>
        <p class="relative mt-3 text-brand-50">{{ __('Join Saudi businesses managing their invoicing and VAT with Daftari.') }}</p>
        <a href="{{ route('register') }}" class="btn-shine relative mt-6 inline-block rounded-lg bg-white px-6 py-3 font-semibold text-brand-700 shadow-lg transition-transform hover:-translate-y-0.5 hover:bg-brand-50">{{ __('Start your free trial') }}</a>
    </div>
</section>
@endsection
