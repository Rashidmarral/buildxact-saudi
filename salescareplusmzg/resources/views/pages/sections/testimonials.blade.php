@php
    $testimonials = \App\Models\Testimonial::orderBy('sort_order')->get();
    $isDark = $section->background === 'dark';
    $bgClass = match ($section->background) {
        'dark' => 'bg-teal-950 text-white',
        'tint' => 'bg-teal-50 text-teal-950',
        default => 'bg-slate-50 text-slate-700',
    };
@endphp
@if ($testimonials->isNotEmpty())
    <section class="{{ $bgClass }} {{ $section->animationClass() }}">
        <div class="mx-auto max-w-7xl px-4 py-16 sm:px-6 lg:px-8">
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

            <div class="grid grid-cols-1 gap-6 reveal-stagger lg:grid-cols-3">
                @foreach ($testimonials as $testimonial)
                    <figure class="flex flex-col rounded-2xl border {{ $isDark ? 'border-teal-800 bg-teal-900/60' : 'border-slate-100 bg-white shadow-sm' }} p-7 transition duration-300 hover:-translate-y-1">
                        <div class="flex gap-1 text-coral-500">
                            @for ($i = 0; $i < $testimonial->rating; $i++)
                                <x-icon name="star" class="h-4 w-4 fill-current" />
                            @endfor
                        </div>
                        <blockquote class="mt-4 flex-1 text-sm leading-relaxed">&ldquo;{{ $testimonial->quote }}&rdquo;</blockquote>
                        <figcaption class="mt-6 border-t {{ $isDark ? 'border-teal-800' : 'border-slate-100' }} pt-4">
                            <p class="font-semibold {{ $isDark ? 'text-white' : 'text-teal-900' }}">{{ $testimonial->name }}</p>
                            <p class="text-xs {{ $isDark ? 'text-teal-300' : 'text-slate-500' }}">{{ $testimonial->role }}, {{ $testimonial->organization }}</p>
                        </figcaption>
                    </figure>
                @endforeach
            </div>
        </div>
    </section>
@endif
