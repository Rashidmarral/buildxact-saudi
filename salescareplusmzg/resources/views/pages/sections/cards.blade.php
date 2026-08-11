@php
    $bgClass = match ($section->background) {
        'dark' => 'bg-teal-950 text-teal-100',
        'tint' => 'bg-teal-50 text-teal-950',
        default => 'bg-white text-slate-700',
    };
@endphp
<section class="{{ $bgClass }} reveal">
    <div class="mx-auto max-w-6xl px-4 py-16 sm:px-6 lg:px-8">
        @if ($section->heading || $section->subheading)
            <div class="mx-auto mb-10 max-w-2xl text-center">
                @if ($section->subheading)
                    <p class="mb-2 text-sm font-semibold uppercase tracking-wide text-coral-600">{{ $section->subheading }}</p>
                @endif
                @if ($section->heading)
                    <h2 class="text-2xl font-bold sm:text-3xl {{ $section->background === 'dark' ? 'text-white' : 'text-teal-900' }}">{{ $section->heading }}</h2>
                @endif
            </div>
        @endif

        <div class="grid gap-6 reveal-stagger sm:grid-cols-2 lg:grid-cols-3">
            @foreach ($section->items ?? [] as $item)
                <div class="rounded-2xl border {{ $section->background === 'dark' ? 'border-teal-800 bg-teal-900/50' : 'border-slate-200 bg-white' }} p-6">
                    @if (!empty($item['heading']))
                        <h3 class="font-semibold {{ $section->background === 'dark' ? 'text-white' : 'text-teal-900' }}">{{ $item['heading'] }}</h3>
                    @endif
                    @if (!empty($item['text']))
                        <p class="mt-2 text-sm {{ $section->background === 'dark' ? 'text-teal-300' : 'text-slate-500' }}">{{ $item['text'] }}</p>
                    @endif
                </div>
            @endforeach
        </div>
    </div>
</section>
