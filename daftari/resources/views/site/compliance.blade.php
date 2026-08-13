@extends('layouts.site')

@section('title', __('Compliance') . ' · Daftari')

@section('content')
<section class="mx-auto max-w-4xl px-6 py-16">
    <h1 class="text-4xl font-extrabold text-slate-900">{{ __('Saudi e-invoicing & tax compliance, explained simply') }}</h1>
    <p class="mt-4 text-lg text-slate-600">{{ __('A practical guide to what ZATCA e-invoicing, VAT, and record-keeping actually require from your business — in plain language, so you know what applies to you before you need it.') }}</p>
</section>

<section class="mx-auto max-w-6xl px-6 pb-16">
    <h2 class="text-2xl font-bold text-slate-900 text-center mb-8">{{ __('Choose a topic') }}</h2>
    <div class="grid md:grid-cols-2 gap-6">
        @foreach ([
            ['n' => 1, 't' => __('Phase 1 — Generation'), 'd' => __('What a compliant electronic invoice must include, and how it must be generated and stored from day one.')],
            ['n' => 2, 't' => __('Phase 2 — Integration'), 'd' => __('When ZATCA integration becomes mandatory for a business, and what changes in practice once it applies to you.')],
            ['n' => 3, 't' => __('Rollout waves'), 'd' => __('How ZATCA phases businesses in by revenue threshold, and how to find which wave and deadline applies to you.')],
            ['n' => 4, 't' => __('Record keeping'), 'd' => __('What invoicing and accounting records to retain, for how long, and how to stay ready for an audit.')],
            ['n' => 5, 't' => __('VAT basics'), 'd' => __('The fundamentals of VAT registration, standard rates, and preparing your figures ahead of filing.')],
            ['n' => 6, 't' => __('Withholding tax'), 'd' => __('A plain-language overview of when withholding tax can apply and how it is typically handled.')],
            ['n' => 7, 't' => __('WPS'), 'd' => __('A simplified overview of the Wage Protection System and what employers are generally expected to do.')],
            ['n' => 8, 't' => __('GOSI'), 'd' => __('The essentials of employer registration and contributions through official channels.')],
        ] as $topic)
            <div class="rounded-xl border border-slate-100 bg-white p-6">
                <span class="inline-flex h-8 w-8 items-center justify-center rounded-full bg-brand-50 text-brand-700 text-sm font-bold">{{ $topic['n'] }}</span>
                <h3 class="mt-3 font-semibold text-slate-900">{{ $topic['t'] }}</h3>
                <p class="mt-2 text-sm text-slate-500">{{ $topic['d'] }}</p>
            </div>
        @endforeach
    </div>

    <p class="mt-8 rounded-lg bg-slate-50 border border-slate-100 px-4 py-3 text-sm text-slate-500">
        <strong class="text-slate-700">{{ __('Note:') }}</strong>
        {{ __('This page is informational, to help you understand what generally applies. It is not legal or tax advice — confirm your specific obligations with ZATCA or a licensed advisor.') }}
    </p>
</section>

<section class="bg-slate-50 py-16">
    <div class="mx-auto max-w-3xl px-6">
        <h2 class="text-2xl font-bold text-slate-900 text-center mb-8">{{ __('Frequently asked questions') }}</h2>
        <div class="space-y-6">
            @foreach ([
                [__('Is Daftari compliant with ZATCA e-invoicing requirements?'), __('Every tax invoice you issue in Daftari includes standards-based VAT calculation and a scannable Phase-1-style QR code out of the box. Full Phase 2 integration (real-time clearance with ZATCA\'s API) is on our roadmap — see our Features page for current status.')],
                [__('Is Phase 2 mandatory for my business right now?'), __('ZATCA rolls Phase 2 out in waves by revenue threshold, so it depends on your business. Check the official ZATCA announcements for your specific wave and deadline.')],
                [__('Do I need accounting experience to use Daftari?'), __('No. Daftari is built so a business owner can issue a compliant invoice, log an expense, and read their VAT position without prior bookkeeping experience.')],
                [__('Is my data secure?'), __('Every company\'s data is isolated from every other company at the database level, passwords are hashed, and all traffic should run over HTTPS in production.')],
            ] as [$q, $a])
                <div class="border-b border-slate-200 pb-6">
                    <h3 class="font-semibold text-slate-900">{{ $q }}</h3>
                    <p class="mt-2 text-sm text-slate-600">{{ $a }}</p>
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
