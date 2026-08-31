@if ($section->items->isNotEmpty())
<section class="bg-slate-50 py-16">
    <div class="mx-auto max-w-6xl px-6">
        @if ($section->title())
            <h2 class="text-2xl font-bold text-slate-900 text-center mb-8">{{ $section->title() }}</h2>
        @endif
        <div class="grid gap-6 md:grid-cols-3">
            @foreach ($section->items as $item)
                <div class="rounded-xl border border-slate-100 bg-white p-6">
                    <p class="text-sm text-slate-600">&ldquo;{{ $item->body() }}&rdquo;</p>
                    <div class="mt-4 flex items-center gap-3">
                        @if ($item->image_path)
                            <img src="{{ \Illuminate\Support\Facades\Storage::url($item->image_path) }}" alt="" class="h-10 w-10 rounded-full object-cover">
                        @else
                            <span class="flex h-10 w-10 items-center justify-center rounded-full bg-brand-50 text-brand-700 text-sm font-bold">{{ mb_substr($item->title() ?? '', 0, 1) }}</span>
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
