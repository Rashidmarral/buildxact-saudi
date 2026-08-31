@if ($section->items->isNotEmpty())
<section class="bg-slate-50 py-16">
    <div class="mx-auto max-w-6xl px-6">
        @if ($section->title())
            <h2 x-data x-reveal class="text-2xl font-bold text-slate-900 text-center mb-10 md:text-3xl">{{ $section->title() }}</h2>
        @endif
        <div class="grid gap-6 md:grid-cols-3">
            @foreach ($section->items as $i => $item)
                <div x-data x-reveal style="animation-delay: {{ $i * 80 }}ms" class="card-hover relative rounded-2xl border border-slate-100 bg-white p-6">
                    <svg class="h-7 w-7 text-brand-200" viewBox="0 0 24 24" fill="currentColor"><path d="M7.17 6C4.87 6 3 7.87 3 10.17c0 2.3 1.87 4.17 4.17 4.17.34 0 .67-.04.98-.12-.4 1.3-1.4 2.4-3.15 2.85v2.4c3.6-.5 5.83-3.05 5.83-6.86V10.17C10.83 7.87 8.96 6 6.66 6h.51zm10.66 0c-2.3 0-4.17 1.87-4.17 4.17 0 2.3 1.87 4.17 4.17 4.17.34 0 .67-.04.98-.12-.4 1.3-1.4 2.4-3.15 2.85v2.4c3.6-.5 5.83-3.05 5.83-6.86V10.17C21.49 7.87 19.62 6 17.32 6h.51z"/></svg>
                    <p class="mt-3 text-sm text-slate-600">{{ $item->body() }}</p>
                    <div class="mt-5 flex items-center gap-3">
                        @if ($item->image_path)
                            <img src="{{ \Illuminate\Support\Facades\Storage::url($item->image_path) }}" alt="" class="h-10 w-10 rounded-full object-cover ring-2 ring-brand-100">
                        @else
                            <span class="flex h-10 w-10 items-center justify-center rounded-full bg-gradient-to-br from-brand-500 to-brand-700 text-sm font-bold text-white">{{ mb_substr($item->title() ?? '', 0, 1) }}</span>
                        @endif
                        <div>
                            <p class="text-sm font-semibold text-slate-900">{{ $item->title() }}</p>
                            <p class="text-xs text-slate-500">{{ $item->subtitle() }}</p>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
    </div>
</section>
@endif
