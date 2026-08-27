@php
    $bgClass = match ($section->background) {
        'dark' => 'bg-teal-950 bg-teal-pattern text-teal-50',
        'tint' => 'bg-teal-50 text-teal-950',
        default => 'bg-white text-teal-950',
    };
    $isDark = $section->background === 'dark';
@endphp
<section class="{{ $bgClass }} {{ $section->animationClass() }}">
    <div class="mx-auto max-w-4xl px-4 py-20 text-center sm:px-6 lg:px-8 lg:py-28">
        @if ($section->subheading)
            <p class="mb-3 text-sm font-semibold uppercase tracking-wide {{ $isDark ? 'text-coral-300' : 'text-coral-600' }}">{{ $section->subheading }}</p>
        @endif
        @if ($section->heading)
            <h1 class="text-3xl font-bold sm:text-4xl lg:text-5xl">{{ $section->heading }}</h1>
        @endif
        @if ($section->body)
            <p class="mt-5 text-lg {{ $isDark ? 'text-teal-200' : 'text-slate-600' }}">{{ $section->body }}</p>
        @endif
        @if ($section->image_path)
            <img src="{{ asset('storage/'.$section->image_path) }}" alt="" class="mx-auto mt-10 w-full max-w-2xl rounded-2xl shadow-lg">
        @endif
        @if ($section->button_text && $section->button_url)
            <a href="{{ $section->button_url }}" class="mt-8 inline-flex items-center gap-2 rounded-full bg-coral-500 px-7 py-3.5 text-sm font-semibold text-white shadow-sm transition duration-300 hover:-translate-y-0.5 hover:bg-coral-400 hover:shadow-lg">
                {{ $section->button_text }} <x-icon name="arrow-right" class="h-4 w-4" />
            </a>
        @endif
    </div>
</section>
