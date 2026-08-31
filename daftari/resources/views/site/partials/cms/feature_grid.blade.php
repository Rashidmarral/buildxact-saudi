@if ($section->items->isNotEmpty())
<section class="mx-auto max-w-6xl px-6 py-16">
    @if ($section->title())
        <h2 class="text-2xl font-bold text-slate-900 text-center mb-8">{{ $section->title() }}</h2>
    @endif
    @if ($section->subtitle())
        <p class="text-center text-slate-600 mb-8">{{ $section->subtitle() }}</p>
    @endif
    <div class="grid gap-6 md:grid-cols-3">
        @foreach ($section->items as $i => $item)
            <div class="relative rounded-xl border border-slate-100 bg-white p-6">
                @if ($item->icon && preg_match('/^[a-z][a-z-]*$/', $item->icon))
                    <div class="inline-flex h-11 w-11 items-center justify-center rounded-xl bg-gradient-to-br from-brand-500 to-brand-700 text-white shadow-soft">
                        @include('partials.icon', ['name' => $item->icon, 'class' => 'h-5 w-5'])
                    </div>
                @elseif ($item->icon)
                    <div class="text-2xl">{{ $item->icon }}</div>
                @else
                    <span class="inline-flex h-8 w-8 items-center justify-center rounded-full bg-brand-50 text-brand-700 text-sm font-bold">{{ $i + 1 }}</span>
                @endif
                <h3 class="mt-3 font-semibold text-slate-900">{{ $item->title() }}</h3>
                <p class="mt-2 text-sm text-slate-500">{{ $item->body() }}</p>
            </div>
        @endforeach
    </div>
</section>
@endif
