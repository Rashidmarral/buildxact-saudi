@php
    $bgClass = match ($section->background) {
        'dark' => 'bg-teal-950 bg-teal-pattern text-white',
        'tint' => 'bg-teal-50 text-teal-950',
        default => 'bg-white text-slate-700',
    };
    $isDark = $section->background === 'dark';
@endphp
<section class="{{ $bgClass }} {{ $section->animationClass() }}">
    <div class="mx-auto max-w-6xl px-4 py-16 sm:px-6 lg:px-8">
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

        <div class="grid grid-cols-2 gap-8 reveal-stagger sm:grid-cols-4">
            @foreach ($section->items ?? [] as $item)
                <div class="text-center">
                    <p class="text-3xl font-bold sm:text-4xl {{ $isDark ? 'text-white' : 'text-teal-900' }}">{{ $item['heading'] ?? '' }}</p>
                    <p class="mt-1 text-sm {{ $isDark ? 'text-teal-300' : 'text-slate-500' }}">{{ $item['text'] ?? '' }}</p>
                </div>
            @endforeach
        </div>
    </div>
</section>
