@if ($section->items->isNotEmpty())
<section class="border-y border-slate-100 bg-white py-10">
    <div class="mx-auto max-w-7xl px-6">
        @if ($section->title())
            <p class="text-center text-xs font-semibold uppercase tracking-wider text-slate-400">{{ $section->title() }}</p>
        @endif
        <div class="mt-6 grid grid-cols-2 gap-6 text-center sm:grid-cols-4">
            @foreach ($section->items as $i => $item)
                <div x-data x-reveal style="animation-delay: {{ $i * 80 }}ms">
                    <div class="text-xl font-bold text-gradient">{{ $item->title() }}</div>
                    <div class="text-xs text-slate-500">{{ $item->subtitle() }}</div>
                </div>
            @endforeach
        </div>
    </div>
</section>
@endif
