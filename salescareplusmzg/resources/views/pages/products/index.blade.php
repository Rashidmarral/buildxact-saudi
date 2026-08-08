<x-layout title="Our Products" description="Browse Sales Care Plus MZG's full catalogue of pharmaceutical products across analgesics, antibiotics, cardiovascular, diabetic, respiratory and more categories.">

    <section class="bg-leaf-pattern bg-leaf-50 py-14 sm:py-16">
        <div class="mx-auto max-w-4xl px-4 text-center sm:px-6 lg:px-8">
            <span class="hero-fade-in inline-flex items-center gap-2 rounded-full bg-leaf-100 px-4 py-1.5 text-sm font-medium text-leaf-700">
                <x-icon name="pill" class="h-4 w-4" /> Our Catalogue
            </span>
            <h1 class="hero-fade-in hero-fade-in-delay-1 mt-6 text-4xl font-bold tracking-tight text-leaf-950 sm:text-5xl">Products We Distribute</h1>
            <p class="hero-fade-in hero-fade-in-delay-2 mt-6 text-lg leading-relaxed text-stone-600">
                A dependable range of branded and generic medicines, sourced from DRAP-registered
                manufacturers and stored under GDP-compliant conditions.
            </p>
        </div>
    </section>

    <section class="mx-auto max-w-7xl px-4 py-14 sm:px-6 lg:px-8">
        <div class="grid gap-10 lg:grid-cols-[280px_1fr]">

            <aside class="reveal space-y-6">
                <form method="GET" action="{{ route('products.index') }}" class="relative">
                    @if (request('category'))
                        <input type="hidden" name="category" value="{{ request('category') }}">
                    @endif
                    <input type="text" name="q" value="{{ request('q') }}" placeholder="Search products…"
                        class="w-full rounded-full border border-stone-200 py-2.5 pl-10 pr-4 text-sm focus:border-leaf-500 focus:outline-none focus:ring-1 focus:ring-leaf-500">
                    <x-icon name="search" class="pointer-events-none absolute left-3.5 top-1/2 h-4 w-4 -translate-y-1/2 text-stone-400" />
                </form>

                <div>
                    <h2 class="text-sm font-semibold uppercase tracking-wide text-stone-500">Categories</h2>
                    <ul class="mt-4 space-y-1">
                        <li>
                            <a href="{{ route('products.index') }}"
                               class="flex items-center justify-between rounded-lg px-3 py-2 text-sm font-medium {{ ! request('category') ? 'bg-leaf-100 text-leaf-700' : 'text-stone-600 hover:bg-stone-50' }}">
                                All Products
                            </a>
                        </li>
                        @foreach ($categories as $category)
                            <li>
                                <a href="{{ route('products.index', ['category' => $category->slug]) }}"
                                   class="flex items-center justify-between rounded-lg px-3 py-2 text-sm font-medium {{ request('category') === $category->slug ? 'bg-leaf-100 text-leaf-700' : 'text-stone-600 hover:bg-stone-50' }}">
                                    <span>{{ $category->name }}</span>
                                    <span class="text-xs text-stone-400">{{ $category->products_count }}</span>
                                </a>
                            </li>
                        @endforeach
                    </ul>
                </div>

                <div class="rounded-2xl bg-leaf-950 p-6 text-white">
                    <p class="text-sm font-semibold">Need a custom quote?</p>
                    <p class="mt-2 text-xs leading-relaxed text-leaf-200">Pharmacies and hospitals can request a full price list and set up a supply account.</p>
                    <a href="{{ route('contact') }}" class="mt-4 inline-flex items-center gap-1.5 text-sm font-semibold text-leaf-300 hover:text-white">
                        Contact our team <x-icon name="arrow-right" class="h-3.5 w-3.5" />
                    </a>
                </div>
            </aside>

            <div>
                <div class="mb-6 flex items-center justify-between">
                    <p class="text-sm text-stone-500">Showing {{ $products->firstItem() ?? 0 }}–{{ $products->lastItem() ?? 0 }} of {{ $products->total() }} products</p>
                </div>

                @if ($products->isEmpty())
                    <div class="rounded-2xl border border-dashed border-stone-200 p-16 text-center text-stone-500">
                        No products matched your search. Try a different keyword or category.
                    </div>
                @else
                    <div class="reveal-stagger grid grid-cols-1 gap-5 sm:grid-cols-2 xl:grid-cols-3">
                        @foreach ($products as $product)
                            <a href="{{ route('products.show', $product) }}" class="group rounded-2xl border border-stone-100 bg-white p-5 shadow-sm transition duration-300 hover:-translate-y-1.5 hover:shadow-lg">
                                <div class="flex items-center justify-between">
                                    <div class="flex h-14 w-14 items-center justify-center rounded-xl bg-leaf-50 text-leaf-500 transition-transform duration-300 group-hover:scale-110">
                                        <x-icon name="pill" class="h-7 w-7" />
                                    </div>
                                    @if ($product->is_featured)
                                        <span class="rounded-full bg-sand-100 px-2.5 py-1 text-[11px] font-semibold text-sand-700">Popular</span>
                                    @endif
                                </div>
                                <p class="mt-4 text-xs font-medium uppercase tracking-wide text-leaf-600">{{ $product->category->name }}</p>
                                <h3 class="mt-1 font-semibold text-stone-800 group-hover:text-leaf-700">{{ $product->name }}</h3>
                                <p class="mt-1 text-sm text-stone-500">{{ $product->generic_name }}</p>
                                <p class="mt-3 text-xs text-stone-400">Pack: {{ $product->pack_size }}</p>
                            </a>
                        @endforeach
                    </div>

                    <div class="mt-10">
                        {{ $products->links() }}
                    </div>
                @endif
            </div>
        </div>
    </section>

</x-layout>
