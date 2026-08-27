@php
    $bgClass = match ($section->background) {
        'dark' => 'bg-teal-950 text-teal-100',
        'tint' => 'bg-teal-50 text-teal-950',
        default => 'bg-white text-slate-700',
    };
@endphp
<section class="{{ $bgClass }} {{ $section->animationClass() }}">
    <div class="mx-auto grid max-w-6xl items-center gap-10 px-4 py-16 sm:px-6 lg:grid-cols-2 lg:px-8 lg:py-20">
        @if ($section->image_path)
            <img src="{{ asset('storage/'.$section->image_path) }}" alt="{{ $section->heading }}" loading="lazy" class="w-full rounded-2xl shadow-md">
        @endif
        <div>
            @if ($section->subheading)
                <p class="mb-2 text-sm font-semibold uppercase tracking-wide text-coral-600">{{ $section->subheading }}</p>
            @endif
            @if ($section->heading)
                <h2 class="text-2xl font-bold sm:text-3xl {{ $section->background === 'dark' ? 'text-white' : 'text-teal-900' }}">{{ $section->heading }}</h2>
            @endif
            @if ($section->body)
                <p class="mt-4 leading-relaxed">{{ $section->body }}</p>
            @endif
            @if ($section->button_text && $section->button_url)
                <a href="{{ $section->button_url }}" class="mt-6 inline-flex items-center gap-2 rounded-full bg-teal-900 px-6 py-3 text-sm font-semibold text-white transition duration-300 hover:-translate-y-0.5 hover:bg-teal-800">
                    {{ $section->button_text }} <x-icon name="arrow-right" class="h-4 w-4" />
                </a>
            @endif
        </div>
    </div>
</section>
