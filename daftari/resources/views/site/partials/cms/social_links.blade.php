@if ($section->items->isNotEmpty())
<section class="mx-auto max-w-2xl px-6 pb-8 text-center">
    @if ($section->title())
        <p class="text-sm font-semibold text-slate-700">{{ $section->title() }}</p>
    @endif
    <div class="mt-3 flex flex-wrap items-center justify-center gap-3">
        @foreach ($section->items as $item)
            <a href="{{ $item->meta['url'] ?? '#' }}" target="_blank" rel="noopener" class="inline-flex items-center gap-2 rounded-full border border-slate-200 bg-white px-4 py-2 text-sm font-medium text-slate-600 hover:border-brand-300 hover:text-brand-700">
                @if ($item->icon)
                    <span>{{ $item->icon }}</span>
                @endif
                {{ $item->title() }}
            </a>
        @endforeach
    </div>
</section>
@endif
