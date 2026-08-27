<x-layout title="Search" description="Search products, principals, pages and FAQs.">

    <section class="bg-teal-pattern py-16 sm:py-20">
        <div class="mx-auto max-w-2xl px-4 text-center sm:px-6 lg:px-8">
            <h1 class="hero-fade-in text-3xl font-bold tracking-tight text-white sm:text-4xl">Search</h1>
            <form method="GET" action="{{ route('search') }}" class="hero-fade-in hero-fade-in-delay-1 mt-6 flex gap-2">
                <label for="q" class="sr-only">Search</label>
                <input type="search" name="q" id="q" value="{{ $query }}" autofocus placeholder="Search products, principals, pages, FAQs..."
                    class="w-full rounded-full border border-white/25 bg-white/10 px-5 py-3 text-white placeholder:text-teal-300 transition focus:border-coral-400 focus:outline-none focus:ring-1 focus:ring-coral-400">
                <button type="submit" class="shrink-0 rounded-full bg-coral-500 px-6 py-3 font-semibold text-white transition duration-300 hover:-translate-y-0.5 hover:bg-coral-400">
                    <x-icon name="search" class="h-5 w-5" />
                </button>
            </form>
        </div>
    </section>

    <section class="mx-auto max-w-4xl px-4 py-16 sm:px-6 lg:px-8">
        @if ($query === '')
            <p class="text-center text-slate-400">Type something above to search the whole site.</p>
        @elseif ($total === 0)
            <p class="text-center text-slate-400">No results for "<span class="font-medium text-slate-600">{{ $query }}</span>". Try a different keyword.</p>
        @else
            <p class="mb-8 text-sm text-slate-500">{{ $total }} result{{ $total === 1 ? '' : 's' }} for "<span class="font-medium text-teal-900">{{ $query }}</span>"</p>

            @if ($results['products']->isNotEmpty())
                <div class="mb-10">
                    <h2 class="text-sm font-semibold uppercase tracking-wide text-coral-600">Products</h2>
                    <div class="mt-4 space-y-3">
                        @foreach ($results['products'] as $product)
                            <a href="{{ route('catalog.show', $product) }}" class="block rounded-xl border border-slate-100 p-4 transition hover:border-teal-200 hover:shadow-sm">
                                <p class="font-semibold text-teal-900">{{ $product->name }}</p>
                                <p class="text-sm text-slate-500">{{ $product->generic_name }} &middot; {{ $product->category?->name }}</p>
                            </a>
                        @endforeach
                    </div>
                </div>
            @endif

            @if ($results['principals']->isNotEmpty())
                <div class="mb-10">
                    <h2 class="text-sm font-semibold uppercase tracking-wide text-coral-600">Principals</h2>
                    <div class="mt-4 space-y-3">
                        @foreach ($results['principals'] as $principal)
                            <a href="{{ route('principals') }}" class="block rounded-xl border border-slate-100 p-4 transition hover:border-teal-200 hover:shadow-sm">
                                <p class="font-semibold text-teal-900">{{ $principal->name }}</p>
                                <p class="text-sm text-slate-500">{{ $principal->tagline }}</p>
                            </a>
                        @endforeach
                    </div>
                </div>
            @endif

            @if ($results['pages']->isNotEmpty())
                <div class="mb-10">
                    <h2 class="text-sm font-semibold uppercase tracking-wide text-coral-600">Pages</h2>
                    <div class="mt-4 space-y-3">
                        @foreach ($results['pages'] as $page)
                            <a href="{{ route('page.show', $page->slug) }}" class="block rounded-xl border border-slate-100 p-4 transition hover:border-teal-200 hover:shadow-sm">
                                <p class="font-semibold text-teal-900">{{ $page->title }}</p>
                                @if ($page->meta_description)<p class="text-sm text-slate-500">{{ $page->meta_description }}</p>@endif
                            </a>
                        @endforeach
                    </div>
                </div>
            @endif

            @if ($results['faqs']->isNotEmpty())
                <div class="mb-10">
                    <h2 class="text-sm font-semibold uppercase tracking-wide text-coral-600">FAQs</h2>
                    <div class="mt-4 space-y-3">
                        @foreach ($results['faqs'] as $faq)
                            <a href="{{ route('faq') }}" class="block rounded-xl border border-slate-100 p-4 transition hover:border-teal-200 hover:shadow-sm">
                                <p class="font-semibold text-teal-900">{{ $faq->question }}</p>
                                <p class="text-sm text-slate-500">{{ \Illuminate\Support\Str::limit($faq->answer, 120) }}</p>
                            </a>
                        @endforeach
                    </div>
                </div>
            @endif
        @endif
    </section>

</x-layout>
