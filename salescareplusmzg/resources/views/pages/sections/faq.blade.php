@php
    $faqs = \App\Models\Faq::orderBy('category')->orderBy('sort_order')->get()->groupBy('category');
    $isDark = $section->background === 'dark';
    $bgClass = match ($section->background) {
        'dark' => 'bg-teal-950 text-white',
        'tint' => 'bg-teal-50 text-teal-950',
        default => 'bg-white text-slate-700',
    };
@endphp
@if ($faqs->isNotEmpty())
    <section class="{{ $bgClass }} {{ $section->animationClass() }}">
        <div class="mx-auto max-w-3xl px-4 py-16 sm:px-6 lg:px-8">
            @if ($section->heading || $section->subheading)
                <div class="mx-auto mb-10 max-w-2xl text-center">
                    @if ($section->subheading)
                        <p class="mb-2 text-sm font-semibold uppercase tracking-wide {{ $isDark ? 'text-coral-300' : 'text-coral-600' }}">{{ $section->subheading }}</p>
                    @endif
                    @if ($section->heading)
                        <h2 class="text-2xl font-bold sm:text-3xl {{ $isDark ? 'text-white' : 'text-teal-900' }}">{{ $section->heading }}</h2>
                    @endif
                </div>
            @endif

            @foreach ($faqs as $category => $categoryFaqs)
                <div class="mb-8 last:mb-0">
                    <h3 class="text-sm font-semibold uppercase tracking-wide {{ $isDark ? 'text-teal-300' : 'text-slate-400' }}">{{ $category }}</h3>
                    <div class="mt-3 space-y-3 reveal-stagger">
                        @foreach ($categoryFaqs as $faq)
                            <details class="group rounded-2xl border {{ $isDark ? 'border-teal-800 bg-teal-900/60' : 'border-slate-100 bg-white shadow-sm' }} p-5 open:shadow-md open:border-coral-300">
                                <summary class="flex cursor-pointer list-none items-center justify-between gap-4 font-semibold {{ $isDark ? 'text-white' : 'text-teal-900' }} marker:content-none">
                                    {{ $faq->question }}
                                    <span class="flex h-7 w-7 shrink-0 items-center justify-center rounded-full bg-teal-50 text-teal-700 transition-transform duration-300 group-open:rotate-45 group-open:bg-coral-50 group-open:text-coral-600">
                                        <svg viewBox="0 0 24 24" class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M12 5v14M5 12h14" /></svg>
                                    </span>
                                </summary>
                                <p class="mt-3 leading-relaxed {{ $isDark ? 'text-teal-200' : 'text-slate-600' }}">{{ $faq->answer }}</p>
                            </details>
                        @endforeach
                    </div>
                </div>
            @endforeach
        </div>
    </section>
@endif
