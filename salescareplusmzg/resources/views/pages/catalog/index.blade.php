<x-layout title="Our Catalog" description="Browse Sales Care Plus MZG's catalog of therapeutic categories and featured pharmaceutical products distributed across Muzaffargarh and South Punjab.">

    <section class="bg-teal-pattern py-14 sm:py-16">
        <div class="mx-auto max-w-4xl px-4 text-center sm:px-6 lg:px-8">
            <span class="hero-fade-in inline-flex items-center gap-2 rounded-full bg-white/10 px-4 py-1.5 text-sm font-medium text-coral-300 ring-1 ring-white/10">
                <x-icon name="pill" class="h-4 w-4" /> Our Catalog
            </span>
            <h1 class="hero-fade-in hero-fade-in-delay-1 mt-6 text-4xl font-bold tracking-tight text-white sm:text-5xl">Comprehensive Range of Therapeutic Categories</h1>
            <p class="hero-fade-in hero-fade-in-delay-2 mt-6 text-lg leading-relaxed text-teal-200">
                A dependable range of branded and generic medicines, sourced from DRAP-registered
                manufacturers and stored under GDP-compliant conditions.
            </p>
        </div>
    </section>

    {{-- Therapeutic categories --}}
    <section class="mx-auto max-w-7xl px-4 py-20 sm:px-6 lg:px-8">
        <div class="reveal mx-auto max-w-2xl text-center">
            <h2 class="text-3xl font-bold text-teal-900 sm:text-4xl">Therapeutic Categories <span class="text-coral-500">We Cover</span></h2>
            <p class="mt-4 text-slate-600">We offer a wide range of pharmaceutical products across multiple therapeutic categories to meet diverse healthcare needs.</p>
        </div>
        <div class="reveal-stagger mt-12 grid grid-cols-1 gap-5 sm:grid-cols-2 lg:grid-cols-4">
            @foreach ($categories as $category)
                <a href="{{ route('catalog.index', ['category' => $category->slug]) }}"
                   class="hover-tilt-card group rounded-2xl border border-slate-100 bg-white p-6 text-center shadow-sm transition duration-300 hover:shadow-lg">
                    <span class="mx-auto flex h-14 w-14 items-center justify-center rounded-full bg-coral-50 text-coral-600 transition-transform duration-300 group-hover:scale-110">
                        <x-icon :name="$category->icon" class="h-7 w-7" />
                    </span>
                    <h3 class="mt-4 font-semibold text-teal-900">{{ $category->name }}</h3>
                    <p class="mt-2 text-sm leading-relaxed text-slate-500">{{ $category->description }}</p>
                    <span class="mt-4 inline-flex items-center gap-1 text-sm font-semibold text-coral-600">
                        View Products <x-icon name="arrow-right" class="h-3.5 w-3.5" />
                    </span>
                </a>
            @endforeach
        </div>
    </section>

    {{-- Featured products --}}
    @if ($featuredProducts->isNotEmpty())
        <section class="bg-slate-50 py-20">
            <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                <div class="reveal mx-auto max-w-2xl text-center">
                    <h2 class="text-3xl font-bold text-teal-900 sm:text-4xl">Featured <span class="text-coral-500">Products</span></h2>
                </div>
                <div class="reveal-stagger mt-12 grid grid-cols-1 gap-5 sm:grid-cols-2 lg:grid-cols-3">
                    @foreach ($featuredProducts as $product)
                        <a href="{{ route('catalog.show', $product) }}" class="group rounded-2xl border border-slate-100 bg-white p-6 text-center shadow-sm transition duration-300 hover:-translate-y-1.5 hover:shadow-lg">
                            @if ($product->image_path)
                                <img src="{{ asset('storage/'.$product->image_path) }}" alt="{{ $product->name }}" loading="lazy" class="mx-auto h-14 w-14 rounded-full object-cover transition-transform duration-300 group-hover:scale-110">
                            @else
                                <span class="mx-auto flex h-14 w-14 items-center justify-center rounded-full bg-teal-900 text-coral-400 transition-transform duration-300 group-hover:scale-110">
                                    <x-icon name="pill" class="h-7 w-7" />
                                </span>
                            @endif
                            <h3 class="mt-4 font-semibold text-teal-900">{{ $product->name }}</h3>
                            <p class="mt-1 text-sm text-slate-500">Category: {{ $product->category->name }}</p>
                            <span class="mt-3 inline-flex items-center rounded-full bg-emerald-50 px-3 py-1 text-xs font-semibold text-emerald-700">Available</span>
                        </a>
                    @endforeach
                </div>
            </div>
        </section>
    @endif

    {{-- Full catalog with filter/search --}}
    <section class="mx-auto max-w-7xl px-4 py-20 sm:px-6 lg:px-8">
        <div class="reveal mx-auto max-w-2xl text-center">
            <span class="text-sm font-semibold uppercase tracking-wide text-coral-600">Browse All</span>
            <h2 class="mt-3 text-3xl font-bold text-teal-900 sm:text-4xl">Full Product Catalog</h2>
        </div>

        <div class="mt-12 grid gap-10 lg:grid-cols-[280px_1fr]">
            <aside class="reveal space-y-6">
                <form method="GET" action="{{ route('catalog.index') }}" class="relative">
                    @if (request('category'))
                        <input type="hidden" name="category" value="{{ request('category') }}">
                    @endif
                    <input type="text" name="q" value="{{ request('q') }}" placeholder="Search products…"
                        class="w-full rounded-full border border-slate-200 py-2.5 pl-10 pr-4 text-sm transition focus:border-coral-500 focus:outline-none focus:ring-1 focus:ring-coral-500">
                    <x-icon name="search" class="pointer-events-none absolute left-3.5 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" />
                </form>

                <div>
                    <h3 class="text-sm font-semibold uppercase tracking-wide text-slate-500">Categories</h3>
                    <ul class="mt-4 space-y-1">
                        <li>
                            <a href="{{ route('catalog.index') }}"
                               class="flex items-center justify-between rounded-lg px-3 py-2 text-sm font-medium {{ ! request('category') ? 'bg-coral-50 text-coral-700' : 'text-slate-600 hover:bg-slate-50' }}">
                                All Products
                            </a>
                        </li>
                        @foreach ($categories as $category)
                            <li>
                                <a href="{{ route('catalog.index', ['category' => $category->slug]) }}"
                                   class="flex items-center justify-between rounded-lg px-3 py-2 text-sm font-medium {{ request('category') === $category->slug ? 'bg-coral-50 text-coral-700' : 'text-slate-600 hover:bg-slate-50' }}">
                                    <span>{{ $category->name }}</span>
                                    <span class="text-xs text-slate-400">{{ $category->products_count }}</span>
                                </a>
                            </li>
                        @endforeach
                    </ul>
                </div>

                <div class="rounded-2xl bg-teal-950 p-6 text-white">
                    <p class="text-sm font-semibold">Need a custom quote?</p>
                    <p class="mt-2 text-xs leading-relaxed text-teal-300">Pharmacies and hospitals can request a full price list and set up a supply account.</p>
                    <a href="{{ route('contact') }}" class="mt-4 inline-flex items-center gap-1.5 text-sm font-semibold text-coral-400 hover:text-coral-300">
                        Contact our team <x-icon name="arrow-right" class="h-3.5 w-3.5" />
                    </a>
                </div>
            </aside>

            <div>
                <div class="mb-6 flex items-center justify-between">
                    <p class="text-sm text-slate-500">Showing {{ $products->firstItem() ?? 0 }}–{{ $products->lastItem() ?? 0 }} of {{ $products->total() }} products</p>
                </div>

                @if ($products->isEmpty())
                    <div class="rounded-2xl border border-dashed border-slate-200 p-16 text-center text-slate-500">
                        No products matched your search. Try a different keyword or category.
                    </div>
                @else
                    <div class="reveal-stagger grid grid-cols-1 gap-5 sm:grid-cols-2 xl:grid-cols-3">
                        @foreach ($products as $product)
                            <a href="{{ route('catalog.show', $product) }}" class="group rounded-2xl border border-slate-100 bg-white p-5 shadow-sm transition duration-300 hover:-translate-y-1.5 hover:shadow-lg">
                                <div class="flex items-center justify-between">
                                    @if ($product->image_path)
                                        <img src="{{ asset('storage/'.$product->image_path) }}" alt="{{ $product->name }}" loading="lazy" class="h-14 w-14 rounded-xl object-cover transition-transform duration-300 group-hover:scale-110">
                                    @else
                                        <div class="flex h-14 w-14 items-center justify-center rounded-xl bg-coral-50 text-coral-600 transition-transform duration-300 group-hover:scale-110">
                                            <x-icon name="pill" class="h-7 w-7" />
                                        </div>
                                    @endif
                                    @if ($product->is_featured)
                                        <span class="rounded-full bg-amber-50 px-2.5 py-1 text-[11px] font-semibold text-amber-700">Popular</span>
                                    @endif
                                </div>
                                <p class="mt-4 text-xs font-medium uppercase tracking-wide text-coral-600">{{ $product->category->name }}</p>
                                <h3 class="mt-1 font-semibold text-teal-900 group-hover:text-coral-600">{{ $product->name }}</h3>
                                <p class="mt-1 text-sm text-slate-500">{{ $product->generic_name }}</p>
                                <p class="mt-3 text-xs text-slate-400">Pack: {{ $product->pack_size }}</p>
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
