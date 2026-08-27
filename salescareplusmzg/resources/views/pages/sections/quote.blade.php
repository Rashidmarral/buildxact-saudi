@php
    $bgClass = match ($section->background) {
        'dark' => 'bg-teal-950 bg-teal-pattern text-white',
        'tint' => 'bg-teal-50 text-teal-950',
        default => 'bg-white text-slate-700',
    };
    $isDark = $section->background === 'dark';
@endphp
<section class="{{ $bgClass }} {{ $section->animationClass() }}">
    <div class="mx-auto max-w-3xl px-4 py-20 text-center sm:px-6 lg:px-8">
        <x-icon name="quote" class="mx-auto h-10 w-10 {{ $isDark ? 'text-coral-300' : 'text-coral-500' }}" />
        @if ($section->body)
            <blockquote class="mt-6 text-2xl font-medium leading-relaxed sm:text-3xl">
                &ldquo;{{ $section->body }}&rdquo;
            </blockquote>
        @endif
        @if ($section->subheading)
            <p class="mt-6 text-sm font-semibold uppercase tracking-wide {{ $isDark ? 'text-teal-300' : 'text-slate-500' }}">{{ $section->subheading }}</p>
        @endif
    </div>
</section>
