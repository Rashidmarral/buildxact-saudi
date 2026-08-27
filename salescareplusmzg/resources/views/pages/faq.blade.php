<x-layout title="Frequently Asked Questions" description="Answers to common questions about ordering, delivery, products and partnerships with Sales Care Plus MZG.">

    <section class="bg-teal-pattern py-16 sm:py-20">
        <div class="mx-auto max-w-4xl px-4 text-center sm:px-6 lg:px-8">
            <span class="hero-fade-in inline-flex items-center gap-2 rounded-full bg-white/10 px-4 py-1.5 text-sm font-medium text-coral-300 ring-1 ring-white/10">
                <x-icon name="quote" class="h-4 w-4" /> FAQs
            </span>
            <h1 class="hero-fade-in hero-fade-in-delay-1 mt-6 text-4xl font-bold tracking-tight text-white sm:text-5xl">Frequently Asked Questions</h1>
            <p class="hero-fade-in hero-fade-in-delay-2 mt-6 text-lg leading-relaxed text-teal-200">
                Answers to the questions we hear most from pharmacies, hospitals and manufacturer partners.
            </p>
        </div>
    </section>

    <section class="mx-auto max-w-4xl px-4 py-20 sm:px-6 lg:px-8">
        @foreach ($faqsByCategory as $category => $faqs)
            <div class="reveal mb-12 last:mb-0">
                <h2 class="text-xl font-bold text-teal-900">{{ $category }}</h2>
                <div class="reveal-stagger mt-5 space-y-3">
                    @foreach ($faqs as $faq)
                        <details class="group rounded-2xl border border-slate-100 bg-white p-5 shadow-sm open:shadow-md open:border-coral-200">
                            <summary class="flex cursor-pointer list-none items-center justify-between gap-4 font-semibold text-teal-900 marker:content-none">
                                {{ $faq->question }}
                                <span class="flex h-7 w-7 shrink-0 items-center justify-center rounded-full bg-teal-50 text-teal-700 transition-transform duration-300 group-open:rotate-45 group-open:bg-coral-50 group-open:text-coral-600">
                                    <svg viewBox="0 0 24 24" class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M12 5v14M5 12h14" /></svg>
                                </span>
                            </summary>
                            <p class="mt-3 leading-relaxed text-slate-600">{{ $faq->answer }}</p>
                        </details>
                    @endforeach
                </div>
            </div>
        @endforeach

        <div class="reveal mt-16 rounded-2xl bg-teal-50/60 p-8 text-center">
            <h2 class="text-xl font-bold text-teal-900">Still have a question?</h2>
            <p class="mt-2 text-slate-600">Our team is happy to help with anything not covered here.</p>
            <a href="{{ route('contact') }}" class="mt-5 inline-flex items-center gap-2 rounded-full bg-teal-900 px-6 py-3 font-semibold text-white transition duration-300 hover:-translate-y-0.5 hover:bg-teal-800 hover:shadow-lg">
                Contact Us
            </a>
        </div>
    </section>

</x-layout>
