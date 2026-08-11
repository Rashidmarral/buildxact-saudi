@php
    $bgClass = match ($section->background) {
        'dark' => 'bg-teal-950 text-teal-100',
        'tint' => 'bg-teal-50 text-teal-950',
        default => 'bg-white text-slate-700',
    };
@endphp
<section class="{{ $bgClass }} reveal">
    <div class="mx-auto max-w-3xl px-4 py-16 sm:px-6 lg:px-8">
        @if ($section->heading)
            <h2 class="text-2xl font-bold sm:text-3xl {{ $section->background === 'dark' ? 'text-white' : 'text-teal-900' }}">{{ $section->heading }}</h2>
        @endif
        @if ($section->subheading)
            <p class="mt-2 text-lg {{ $section->background === 'dark' ? 'text-teal-300' : 'text-coral-600' }}">{{ $section->subheading }}</p>
        @endif
        @if ($section->body)
            <div class="prose-p:mt-4 prose-p:leading-relaxed">
                @foreach (explode("\n\n", $section->body) as $paragraph)
                    <p class="mt-4 leading-relaxed">{{ $paragraph }}</p>
                @endforeach
            </div>
        @endif
    </div>
</section>
