@if ($section->items->isNotEmpty())
<section class="mx-auto max-w-6xl px-6 py-16">
    @if ($section->title())
        <h2 x-data x-reveal class="text-2xl font-bold text-slate-900 text-center mb-3 md:text-3xl">{{ $section->title() }}</h2>
    @endif
    @if ($section->subtitle())
        <p x-data x-reveal class="mx-auto max-w-2xl text-center text-slate-600 mb-10">{{ $section->subtitle() }}</p>
    @elseif ($section->title())
        <div class="mb-10"></div>
    @endif
    <div class="grid gap-6 md:grid-cols-3">
        @foreach ($section->items as $i => $item)
            <div x-data x-reveal style="animation-delay: {{ ($i % 6) * 70 }}ms" class="card-hover relative rounded-2xl border border-slate-100 bg-white p-6">
                @if ($item->icon && preg_match('/^[a-z][a-z-]*$/', $item->icon))
                    <div class="inline-flex h-11 w-11 items-center justify-center rounded-xl bg-gradient-to-br from-brand-500 to-brand-700 text-white shadow-soft">
                        @include('partials.icon', ['name' => $item->icon, 'class' => 'h-5 w-5'])
                    </div>
                @elseif ($item->icon)
                    <div class="inline-flex h-11 w-11 items-center justify-center rounded-xl bg-brand-50 text-2xl">{{ $item->icon }}</div>
                @else
                    <span class="inline-flex h-9 w-9 items-center justify-center rounded-full bg-brand-50 text-brand-700 text-sm font-bold">{{ $i + 1 }}</span>
                @endif
                <h3 class="mt-4 font-semibold text-slate-900">{{ $item->title() }}</h3>
                <p class="mt-2 text-sm text-slate-500">{{ $item->body() }}</p>
            </div>
        @endforeach
    </div>
</section>
@endif
