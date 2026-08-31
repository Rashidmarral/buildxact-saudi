@if ($section->items->isNotEmpty())
<section class="bg-slate-50 py-16">
    <div class="mx-auto max-w-3xl px-6">
        @if ($section->title())
            <h2 class="text-2xl font-bold text-slate-900 text-center mb-8">{{ $section->title() }}</h2>
        @endif
        <div class="space-y-6">
            @foreach ($section->items as $item)
                <div class="border-b border-slate-200 pb-6">
                    <h3 class="font-semibold text-slate-900">{{ $item->title() }}</h3>
                    <p class="mt-2 text-sm text-slate-600">{{ $item->body() }}</p>
                </div>
            @endforeach
        </div>
    </div>
</section>
@endif
