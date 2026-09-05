@extends('layouts.site')

@section('title', ($page === 'terms' ? __('Terms of Service') : __('Privacy Policy')) . ' · Daftari')

@section('content')
<section class="mx-auto max-w-3xl px-6 py-16 prose prose-slate">
    @if ($page === 'terms')
        <h1 class="text-3xl font-extrabold text-slate-900">{{ __('Terms of Service') }}</h1>
        <p class="mt-4 text-slate-500 text-sm">{{ __('Last updated') }}: {{ now()->format('Y-m-d') }}</p>
        <div class="mt-8 space-y-6 text-slate-600">
            <p>{{ __('These placeholder Terms of Service govern your use of Daftari. Replace this text with terms reviewed by your legal counsel before going live.') }}</p>
            <h2 class="text-xl font-bold text-slate-900">{{ __('1. Subscriptions') }}</h2>
            <p>{{ __('Daftari is offered on a subscription basis. Plans, pricing, and billing cycles are described on our Pricing page and may change with notice.') }}</p>
            <h2 class="text-xl font-bold text-slate-900">{{ __('2. Your data') }}</h2>
            <p>{{ __('You retain ownership of the invoices, clients, and financial data you enter into Daftari. We do not sell your business data to third parties.') }}</p>
            <h2 class="text-xl font-bold text-slate-900">{{ __('3. Acceptable use') }}</h2>
            <p>{{ __('You agree not to use Daftari for unlawful purposes or to attempt to disrupt the service.') }}</p>
        </div>
    @else
        <h1 class="text-3xl font-extrabold text-slate-900">{{ __('Privacy Policy') }}</h1>
        <p class="mt-4 text-slate-500 text-sm">{{ __('Last updated') }}: {{ now()->format('Y-m-d') }}</p>
        <div class="mt-8 space-y-6 text-slate-600">
            <p>{{ __('This placeholder Privacy Policy explains what information Daftari collects and how it is used. Replace this text with a policy reviewed by your legal counsel before going live.') }}</p>
            <h2 class="text-xl font-bold text-slate-900">{{ __('Information we collect') }}</h2>
            <p>{{ __('Account details (name, email), company information (VAT/CR numbers), and the invoicing and expense data you enter.') }}</p>
            <h2 class="text-xl font-bold text-slate-900">{{ __('How we use it') }}</h2>
            <p>{{ __('To provide the service, process subscription billing, and communicate with you about your account.') }}</p>
        </div>
    @endif
</section>
@endsection
