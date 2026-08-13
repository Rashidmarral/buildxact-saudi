@extends('layouts.site')

@section('title', __('About') . ' · Daftari')

@section('content')
<section class="mx-auto max-w-4xl px-6 py-16">
    <h1 class="text-4xl font-extrabold text-slate-900">{{ __('About Daftari') }}</h1>
    <p class="mt-6 text-lg text-slate-600">
        {{ __('Daftari was built for one reason: Saudi businesses deserve accounting software that speaks their language — literally and in terms of VAT compliance, SAR pricing, and local business practices.') }}
    </p>
    <p class="mt-4 text-slate-600">
        {{ __('We focus on the everyday workflow of a growing business: quote a client, invoice them with the correct VAT and a compliant QR code, track what is owed, log your expenses, and know your VAT position before the return is due — without hiring an accountant just to keep the books straight.') }}
    </p>
    <div class="mt-12 grid md:grid-cols-3 gap-8">
        @foreach ([
            [__('Compliance first'), __('Every invoice includes standards-based VAT calculation and a scannable QR code.')],
            [__('Built for Arabic & English'), __('A genuinely bilingual product with right-to-left layout, not a translated afterthought.')],
            [__('Fair, transparent pricing'), __('Simple SAR pricing with no hidden fees — upgrade, downgrade, or cancel anytime.')],
        ] as [$title, $desc])
            <div class="rounded-xl border border-slate-100 p-6">
                <h3 class="font-semibold text-slate-900">{{ $title }}</h3>
                <p class="mt-2 text-sm text-slate-500">{{ $desc }}</p>
            </div>
        @endforeach
    </div>
</section>
@endsection
