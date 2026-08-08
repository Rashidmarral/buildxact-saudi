<x-layout :title="$product->name" :description="$product->name.' — '.$product->generic_name.'. Distributed by Sales Care Plus MZG across Muzaffargarh and South Punjab.'">

    <section class="border-b border-leaf-100 bg-leaf-50/60">
        <div class="mx-auto max-w-7xl px-4 py-4 text-sm text-stone-500 sm:px-6 lg:px-8">
            <a href="{{ route('products.index') }}" class="hover:text-leaf-700">Products</a>
            <span class="mx-1.5">/</span>
            <a href="{{ route('products.index', ['category' => $product->category->slug]) }}" class="hover:text-leaf-700">{{ $product->category->name }}</a>
            <span class="mx-1.5">/</span>
            <span class="text-stone-700">{{ $product->name }}</span>
        </div>
    </section>

    <section class="mx-auto max-w-7xl px-4 py-14 sm:px-6 lg:px-8">
        <div class="grid gap-12 lg:grid-cols-2">
            <div class="reveal-scale flex aspect-square items-center justify-center overflow-hidden rounded-3xl bg-gradient-to-br from-leaf-100 to-leaf-50 p-10">
                <x-illustration name="medicines" class="w-full max-w-xs animate-float" />
            </div>

            <div class="hero-fade-in">
                <p class="text-sm font-semibold uppercase tracking-wide text-leaf-600">{{ $product->category->name }}</p>
                <h1 class="mt-2 text-3xl font-bold text-leaf-950 sm:text-4xl">{{ $product->name }}</h1>
                <p class="mt-3 text-lg text-stone-500">{{ $product->generic_name }}</p>

                @if ($product->description)
                    <p class="mt-6 leading-relaxed text-stone-600">{{ $product->description }}</p>
                @endif

                <dl class="mt-8 grid grid-cols-1 gap-4 rounded-2xl border border-stone-100 p-6 sm:grid-cols-2">
                    <div>
                        <dt class="text-xs font-semibold uppercase tracking-wide text-stone-400">Pack Size</dt>
                        <dd class="mt-1 font-medium text-stone-800">{{ $product->pack_size ?? '—' }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs font-semibold uppercase tracking-wide text-stone-400">Sourcing</dt>
                        <dd class="mt-1 font-medium text-stone-800">{{ $product->manufacturer ?? '—' }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs font-semibold uppercase tracking-wide text-stone-400">Category</dt>
                        <dd class="mt-1 font-medium text-stone-800">{{ $product->category->name }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs font-semibold uppercase tracking-wide text-stone-400">Availability</dt>
                        <dd class="mt-1 inline-flex items-center gap-1.5 font-medium text-leaf-700">
                            <x-icon name="check-circle" class="h-4 w-4" /> In Stock for Partner Pharmacies
                        </dd>
                    </div>
                </dl>

                <div class="mt-8 flex flex-wrap gap-4">
                    <a href="{{ route('contact') }}" class="inline-flex items-center gap-2 rounded-full bg-leaf-600 px-6 py-3.5 font-semibold text-white shadow-sm transition duration-300 hover:-translate-y-0.5 hover:bg-leaf-700 hover:shadow-lg">
                        Request Pricing &amp; Availability <x-icon name="arrow-right" class="h-4 w-4" />
                    </a>
                    <a href="tel:{{ config('company.phone') }}" class="inline-flex items-center gap-2 rounded-full border border-leaf-300 px-6 py-3.5 font-semibold text-leaf-700 transition duration-300 hover:-translate-y-0.5 hover:bg-leaf-50">
                        <x-icon name="phone" class="h-4 w-4" /> {{ config('company.phone') }}
                    </a>
                </div>
            </div>
        </div>

        @if ($related->isNotEmpty())
            <div class="reveal mt-20">
                <h2 class="text-2xl font-bold text-leaf-950">More in {{ $product->category->name }}</h2>
                <div class="reveal-stagger mt-6 grid grid-cols-1 gap-5 sm:grid-cols-2 lg:grid-cols-4">
                    @foreach ($related as $item)
                        <a href="{{ route('products.show', $item) }}" class="group rounded-2xl border border-stone-100 bg-white p-5 shadow-sm transition duration-300 hover:-translate-y-1.5 hover:shadow-lg">
                            <div class="flex h-14 w-14 items-center justify-center rounded-xl bg-leaf-50 text-leaf-500 transition-transform duration-300 group-hover:scale-110">
                                <x-icon name="pill" class="h-7 w-7" />
                            </div>
                            <h3 class="mt-4 font-semibold text-stone-800 group-hover:text-leaf-700">{{ $item->name }}</h3>
                            <p class="mt-1 text-sm text-stone-500">{{ $item->generic_name }}</p>
                        </a>
                    @endforeach
                </div>
            </div>
        @endif
    </section>

</x-layout>
