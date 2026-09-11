<section class="mx-auto max-w-4xl px-6 py-16">
    <div class="rounded-2xl bg-brand-700 px-8 py-12 text-center text-white">
        <h2 class="text-xl md:text-2xl font-bold">{{ __('From tools to a full accounting system') }}</h2>
        <p class="mt-3 text-brand-50">{{ __('Daftari is subscription VAT invoicing and accounting software: invoices, expenses, and VAT reports in one place.') }}</p>
        <a href="{{ route('register') }}" class="mt-6 inline-block rounded-lg bg-white px-6 py-3 font-semibold text-brand-700 hover:bg-brand-50">{{ __('Start your free trial') }}</a>
        <p class="mt-3 text-xs text-brand-100">{{ __(':days days free · no credit card required', ['days' => config('daftari.trial_days')]) }}</p>
    </div>
</section>
