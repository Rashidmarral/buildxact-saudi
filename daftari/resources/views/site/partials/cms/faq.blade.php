@if ($section->items->isNotEmpty())
<section class="bg-slate-50 py-16">
    <div class="mx-auto max-w-3xl px-6">
        @if ($section->title())
            <h2 x-data x-reveal class="text-2xl font-bold text-slate-900 text-center mb-10 md:text-3xl">{{ $section->title() }}</h2>
        @endif
        <div class="space-y-3">
            @foreach ($section->items as $i => $item)
                <div x-data="{ open: {{ $i === 0 ? 'true' : 'false' }} }" x-reveal style="animation-delay: {{ $i * 60 }}ms" class="overflow-hidden rounded-xl border border-slate-100 bg-white">
                    <button type="button" @click="open = !open" class="flex w-full items-center justify-between gap-4 px-5 py-4 text-start">
                        <span class="font-semibold text-slate-900">{{ $item->title() }}</span>
                        <svg class="h-4 w-4 shrink-0 text-slate-400 transition-transform" :class="open ? 'rotate-180' : ''" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M5.23 7.21a.75.75 0 011.06.02L10 11.168l3.71-3.938a.75.75 0 111.08 1.04l-4.25 4.5a.75.75 0 01-1.08 0l-4.25-4.5a.75.75 0 01.02-1.06z" clip-rule="evenodd"/></svg>
                    </button>
                    <div x-show="open" x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0 -translate-y-1" x-transition:enter-end="opacity-100 translate-y-0" class="px-5 pb-4 text-sm text-slate-600" style="display: none">{{ $item->body() }}</div>
                </div>
            @endforeach
        </div>
    </div>
</section>
@endif
