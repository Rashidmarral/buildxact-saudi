@if ($section->items->isNotEmpty())
<section class="mx-auto max-w-5xl px-6 pb-8 grid sm:grid-cols-3 gap-6">
    @foreach ($section->items as $i => $item)
        <div x-data x-reveal style="animation-delay: {{ $i * 80 }}ms" class="card-hover rounded-2xl border border-slate-100 bg-white p-6 text-center">
            <div class="mx-auto flex h-12 w-12 items-center justify-center rounded-full bg-gradient-to-br from-brand-50 to-brand-100 text-2xl">{{ $item->icon ?: '•' }}</div>
            <h3 class="mt-4 font-semibold text-slate-900">{{ $item->title() }}</h3>
            <p class="mt-1 text-sm text-slate-500">{{ $item->subtitle() }}</p>
            <a href="{{ $item->meta['url'] ?? '#' }}" class="mt-2 inline-block text-sm font-semibold text-brand-700 hover:underline">{{ $item->body() }}</a>
        </div>
    @endforeach
</section>
@endif
