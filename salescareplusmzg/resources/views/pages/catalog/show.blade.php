<x-layout :title="$product->name" :description="$product->name.' — '.$product->generic_name.'. Distributed by Sales Care Plus MZG across Muzaffargarh and South Punjab.'">

    <section class="border-b border-slate-100 bg-slate-50">
        <div class="mx-auto max-w-7xl px-4 py-4 text-sm text-slate-500 sm:px-6 lg:px-8">
            <a href="{{ route('catalog.index') }}" class="hover:text-coral-600">Catalog</a>
            <span class="mx-1.5">/</span>
            <a href="{{ route('catalog.index', ['category' => $product->category->slug]) }}" class="hover:text-coral-600">{{ $product->category->name }}</a>
            <span class="mx-1.5">/</span>
            <span class="text-teal-900">{{ $product->name }}</span>
        </div>
    </section>

    <section class="mx-auto max-w-7xl px-4 py-14 sm:px-6 lg:px-8">
        <div class="grid gap-12 lg:grid-cols-2">
            <div class="reveal-scale flex aspect-square items-center justify-center overflow-hidden rounded-3xl bg-gradient-to-br from-teal-50 to-coral-50 p-10">
                @if ($product->image_path)
                    <img src="{{ asset('storage/'.$product->image_path) }}" alt="{{ $product->name }}" class="h-full w-full rounded-2xl object-cover">
                @else
                    <x-illustration name="medicines" class="w-full max-w-xs animate-float" />
                @endif
            </div>

            <div class="hero-fade-in">
                <p class="text-sm font-semibold uppercase tracking-wide text-coral-600">{{ $product->category->name }}</p>
                <h1 class="mt-2 text-3xl font-bold text-teal-900 sm:text-4xl">{{ $product->name }}</h1>
                <p class="mt-3 text-lg text-slate-500">{{ $product->generic_name }}</p>

                @if ($product->description)
                    <p class="mt-6 leading-relaxed text-slate-600">{{ $product->description }}</p>
                @endif

                <div class="mt-8 overflow-hidden rounded-2xl border border-slate-100">
                    <table class="w-full text-left text-sm">
                        <tbody class="divide-y divide-slate-100">
                            <tr>
                                <th scope="row" class="w-2/5 bg-slate-50 px-5 py-3 font-medium text-slate-500">Generic Name</th>
                                <td class="px-5 py-3 font-medium text-teal-900">{{ $product->generic_name ?? '—' }}</td>
                            </tr>
                            <tr>
                                <th scope="row" class="bg-slate-50 px-5 py-3 font-medium text-slate-500">Pack Size</th>
                                <td class="px-5 py-3 font-medium text-teal-900">{{ $product->pack_size ?? '—' }}</td>
                            </tr>
                            <tr>
                                <th scope="row" class="bg-slate-50 px-5 py-3 font-medium text-slate-500">Manufacturer</th>
                                <td class="px-5 py-3 font-medium text-teal-900">{{ $product->manufacturer ?? '—' }}</td>
                            </tr>
                            <tr>
                                <th scope="row" class="bg-slate-50 px-5 py-3 font-medium text-slate-500">Category</th>
                                <td class="px-5 py-3 font-medium text-teal-900">{{ $product->category->name }}</td>
                            </tr>
                            <tr>
                                <th scope="row" class="bg-slate-50 px-5 py-3 font-medium text-slate-500">Availability</th>
                                <td class="px-5 py-3">
                                    <span class="inline-flex items-center gap-1.5 font-medium text-emerald-600">
                                        <x-icon name="check-circle" class="h-4 w-4" /> In Stock for Partner Pharmacies
                                    </span>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div class="mt-8 flex flex-wrap gap-4">
                    <a href="{{ route('contact') }}" class="inline-flex items-center gap-2 rounded-full bg-coral-500 px-6 py-3.5 font-semibold text-white shadow-sm transition duration-300 hover:-translate-y-0.5 hover:bg-coral-400 hover:shadow-lg">
                        Request Pricing &amp; Availability <x-icon name="arrow-right" class="h-4 w-4" />
                    </a>
                    <a href="tel:{{ config('company.phone') }}" class="inline-flex items-center gap-2 rounded-full border border-slate-200 px-6 py-3.5 font-semibold text-teal-900 transition duration-300 hover:-translate-y-0.5 hover:bg-slate-50">
                        <x-icon name="phone" class="h-4 w-4" /> {{ config('company.phone') }}
                    </a>
                </div>
            </div>
        </div>

        @if ($related->isNotEmpty())
            <div class="reveal mt-20">
                <h2 class="text-2xl font-bold text-teal-900">More in {{ $product->category->name }}</h2>
                <div class="reveal-stagger mt-6 grid grid-cols-1 gap-5 sm:grid-cols-2 lg:grid-cols-4">
                    @foreach ($related as $item)
                        <a href="{{ route('catalog.show', $item) }}" class="group rounded-2xl border border-slate-100 bg-white p-5 shadow-sm transition duration-300 hover:-translate-y-1.5 hover:shadow-lg">
                            <div class="flex h-14 w-14 items-center justify-center rounded-xl bg-coral-50 text-coral-600 transition-transform duration-300 group-hover:scale-110">
                                <x-icon name="pill" class="h-7 w-7" />
                            </div>
                            <h3 class="mt-4 font-semibold text-teal-900 group-hover:text-coral-600">{{ $item->name }}</h3>
                            <p class="mt-1 text-sm text-slate-500">{{ $item->generic_name }}</p>
                        </a>
                    @endforeach
                </div>
            </div>
        @endif
    </section>

</x-layout>
