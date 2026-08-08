<x-layout title="Home" description="Sales Care Plus MZG — a trusted pharmaceutical distribution company based in Muzaffargarh, Pakistan, supplying quality medicines to pharmacies, hospitals and clinics across South Punjab.">

    {{-- Hero --}}
    <section class="relative overflow-hidden bg-leaf-pattern bg-leaf-50">
        <div class="mx-auto grid max-w-7xl items-center gap-12 px-4 py-16 sm:px-6 lg:grid-cols-2 lg:px-8 lg:py-24">
            <div>
                <span class="hero-fade-in inline-flex items-center gap-2 rounded-full bg-leaf-100 px-4 py-1.5 text-sm font-medium text-leaf-700">
                    <x-icon name="leaf" class="h-4 w-4 animate-leaf-sway" /> Rooted in Muzaffargarh since {{ config('company.founded_year') }}
                </span>
                <h1 class="hero-fade-in hero-fade-in-delay-1 mt-6 text-balance text-4xl font-bold tracking-tight text-leaf-950 sm:text-5xl lg:text-6xl">
                    Quality Medicines, <span class="text-leaf-600">Delivered with Care</span>
                </h1>
                <p class="hero-fade-in hero-fade-in-delay-2 mt-6 max-w-xl text-lg leading-relaxed text-stone-600">
                    Sales Care Plus MZG is a trusted pharmaceutical distribution company supplying
                    branded and generic medicines to pharmacies, hospitals and clinics across
                    Muzaffargarh and South Punjab — reliably, ethically, and on time.
                </p>
                <div class="hero-fade-in hero-fade-in-delay-3 mt-8 flex flex-wrap gap-4">
                    <a href="{{ route('products.index') }}" class="inline-flex items-center gap-2 rounded-full bg-leaf-600 px-6 py-3.5 font-semibold text-white shadow-sm transition duration-300 hover:-translate-y-0.5 hover:bg-leaf-700 hover:shadow-lg">
                        Explore Our Products <x-icon name="arrow-right" class="h-4 w-4" />
                    </a>
                    <a href="{{ route('contact') }}" class="inline-flex items-center gap-2 rounded-full border border-leaf-300 bg-white px-6 py-3.5 font-semibold text-leaf-700 transition duration-300 hover:-translate-y-0.5 hover:bg-leaf-50 hover:shadow-md">
                        Get in Touch
                    </a>
                </div>
                <dl class="hero-fade-in hero-fade-in-delay-3 mt-12 grid grid-cols-3 gap-6 border-t border-leaf-200 pt-8">
                    <div>
                        <dt class="text-2xl font-bold text-leaf-800 sm:text-3xl"><span data-counter="10" data-counter-suffix="+">10+</span></dt>
                        <dd class="mt-1 text-sm text-stone-500">Years of Service</dd>
                    </div>
                    <div>
                        <dt class="text-2xl font-bold text-leaf-800 sm:text-3xl"><span data-counter="300" data-counter-suffix="+">300+</span></dt>
                        <dd class="mt-1 text-sm text-stone-500">Partner Pharmacies</dd>
                    </div>
                    <div>
                        <dt class="text-2xl font-bold text-leaf-800 sm:text-3xl"><span data-counter="8">8</span></dt>
                        <dd class="mt-1 text-sm text-stone-500">Product Categories</dd>
                    </div>
                </dl>
            </div>

            <div class="reveal-scale relative">
                <div class="aspect-[4/5] w-full overflow-hidden rounded-[2.5rem] bg-gradient-to-br from-leaf-600 via-leaf-700 to-leaf-900 shadow-2xl">
                    <div class="flex h-full w-full flex-col items-center justify-center gap-6 p-8 text-center text-leaf-50">
                        <div class="animate-float w-full max-w-xs">
                            <x-illustration name="medicines" class="w-full drop-shadow-xl" />
                        </div>
                        <p class="text-sm uppercase tracking-[0.3em] text-leaf-300">Nature-Trusted &middot; Quality Assured</p>
                        <p class="max-w-sm text-lg font-semibold leading-snug">
                            "Health delivered with the same care nature gives — consistent, gentle, dependable."
                        </p>
                    </div>
                </div>
                <div class="absolute -bottom-6 -left-6 hidden animate-float rounded-2xl bg-white p-5 shadow-xl sm:block">
                    <div class="flex items-center gap-3">
                        <span class="flex h-11 w-11 items-center justify-center rounded-full bg-sand-100 text-sand-700">
                            <x-icon name="truck" class="h-5 w-5" />
                        </span>
                        <div>
                            <p class="text-sm font-semibold text-stone-800">Next-Day Delivery</p>
                            <p class="text-xs text-stone-500">Across Muzaffargarh &amp; nearby tehsils</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    {{-- Trust bar --}}
    <section class="border-y border-leaf-100 bg-white">
        <div class="reveal-stagger mx-auto grid max-w-7xl grid-cols-2 gap-6 px-4 py-8 sm:px-6 md:grid-cols-4 lg:px-8">
            @foreach ([
                ['icon' => 'badge-check', 'label' => 'DRAP Registered'],
                ['icon' => 'thermometer', 'label' => 'Cold-Chain Handling'],
                ['icon' => 'truck', 'label' => 'Reliable Logistics'],
                ['icon' => 'users', 'label' => '300+ Partner Pharmacies'],
            ] as $item)
                <div class="flex items-center gap-3">
                    <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-leaf-50 text-leaf-700 transition-transform duration-300 hover:scale-110">
                        <x-icon :name="$item['icon']" class="h-5 w-5" />
                    </span>
                    <span class="text-sm font-medium text-stone-700">{{ $item['label'] }}</span>
                </div>
            @endforeach
        </div>
    </section>

    {{-- About preview --}}
    <section class="mx-auto max-w-7xl px-4 py-20 sm:px-6 lg:px-8">
        <div class="grid gap-12 lg:grid-cols-2 lg:items-center">
            <div class="reveal-scale order-2 grid grid-cols-2 gap-4 lg:order-1">
                <div class="col-span-2 flex h-48 items-center justify-center overflow-hidden rounded-2xl bg-leaf-50 transition-transform duration-500 hover:scale-[1.02] sm:h-64">
                    <x-illustration name="warehouse" class="h-full w-full" />
                </div>
                <div class="flex h-32 items-center justify-center rounded-2xl bg-sand-100 text-sand-700 transition-transform duration-500 hover:scale-105 sm:h-40">
                    <x-icon name="thermometer" class="h-10 w-10" />
                </div>
                <div class="flex h-32 items-center justify-center rounded-2xl bg-leaf-600 text-white transition-transform duration-500 hover:scale-105 sm:h-40">
                    <x-icon name="shield" class="h-10 w-10" />
                </div>
            </div>
            <div class="reveal order-1 lg:order-2">
                <span class="text-sm font-semibold uppercase tracking-wide text-leaf-600">Who We Are</span>
                <h2 class="mt-3 text-3xl font-bold text-leaf-950 sm:text-4xl">A Distribution Partner You Can Rely On</h2>
                <p class="mt-5 leading-relaxed text-stone-600">
                    Based in the heart of Muzaffargarh, Sales Care Plus MZG bridges the gap between
                    pharmaceutical manufacturers and the pharmacies, clinics and hospitals that depend
                    on us every day. From temperature-controlled storage to a dedicated local delivery
                    fleet, we've built our business on one principle — medicines should reach the people
                    who need them, in perfect condition, every single time.
                </p>
                <ul class="mt-6 space-y-3">
                    @foreach ([
                        'DRAP-registered warehouse with GDP-compliant storage',
                        'Wide catalogue across 8 therapeutic categories',
                        'Dedicated account support for every partner pharmacy',
                    ] as $point)
                        <li class="flex items-start gap-3 text-sm text-stone-700">
                            <x-icon name="check-circle" class="mt-0.5 h-5 w-5 shrink-0 text-leaf-600" />
                            {{ $point }}
                        </li>
                    @endforeach
                </ul>
                <a href="{{ route('about') }}" class="mt-8 inline-flex items-center gap-2 font-semibold text-leaf-700 hover:text-leaf-800">
                    Learn more about us <x-icon name="arrow-right" class="h-4 w-4" />
                </a>
            </div>
        </div>
    </section>

    {{-- Categories --}}
    <section class="bg-leaf-50/60 py-20">
        <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
            <div class="reveal mx-auto max-w-2xl text-center">
                <span class="text-sm font-semibold uppercase tracking-wide text-leaf-600">Our Range</span>
                <h2 class="mt-3 text-3xl font-bold text-leaf-950 sm:text-4xl">Product Categories</h2>
                <p class="mt-4 text-stone-600">A broad, dependable catalogue covering everyday and specialist therapeutic needs.</p>
            </div>
            <div class="reveal-stagger mt-12 grid grid-cols-1 gap-5 sm:grid-cols-2 lg:grid-cols-4">
                @foreach ($categories as $category)
                    <a href="{{ route('products.index', ['category' => $category->slug]) }}"
                       class="group rounded-2xl border border-leaf-100 bg-white p-6 shadow-sm transition duration-300 hover:-translate-y-1.5 hover:shadow-lg">
                        <span class="flex h-12 w-12 items-center justify-center rounded-xl bg-leaf-100 text-leaf-700 transition duration-300 group-hover:scale-110 group-hover:bg-leaf-600 group-hover:text-white">
                            <x-icon :name="$category->icon" class="h-6 w-6" />
                        </span>
                        <h3 class="mt-4 font-semibold text-stone-800">{{ $category->name }}</h3>
                        <p class="mt-2 text-sm leading-relaxed text-stone-500">{{ $category->description }}</p>
                    </a>
                @endforeach
            </div>
        </div>
    </section>

    {{-- Featured products --}}
    @if ($featuredProducts->isNotEmpty())
        <section class="mx-auto max-w-7xl px-4 py-20 sm:px-6 lg:px-8">
            <div class="reveal flex flex-wrap items-end justify-between gap-4">
                <div>
                    <span class="text-sm font-semibold uppercase tracking-wide text-leaf-600">Featured</span>
                    <h2 class="mt-3 text-3xl font-bold text-leaf-950 sm:text-4xl">Popular Products</h2>
                </div>
                <a href="{{ route('products.index') }}" class="inline-flex items-center gap-2 font-semibold text-leaf-700 hover:text-leaf-800">
                    View full catalogue <x-icon name="arrow-right" class="h-4 w-4" />
                </a>
            </div>
            <div class="reveal-stagger mt-10 grid grid-cols-1 gap-5 sm:grid-cols-2 lg:grid-cols-4">
                @foreach ($featuredProducts as $product)
                    <a href="{{ route('products.show', $product) }}" class="group rounded-2xl border border-stone-100 bg-white p-5 shadow-sm transition duration-300 hover:-translate-y-1.5 hover:shadow-lg">
                        <div class="flex h-28 items-center justify-center rounded-xl bg-leaf-50 text-leaf-500 transition-transform duration-300 group-hover:scale-105">
                            <x-icon name="pill" class="h-10 w-10" />
                        </div>
                        <p class="mt-4 text-xs font-medium uppercase tracking-wide text-leaf-600">{{ $product->category->name }}</p>
                        <h3 class="mt-1 font-semibold text-stone-800 group-hover:text-leaf-700">{{ $product->name }}</h3>
                        <p class="mt-1 text-sm text-stone-500">{{ $product->generic_name }}</p>
                    </a>
                @endforeach
            </div>
        </section>
    @endif

    {{-- Certifications --}}
    <section class="bg-leaf-950 py-20 text-white">
        <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
            <div class="reveal mx-auto max-w-2xl text-center">
                <span class="text-sm font-semibold uppercase tracking-wide text-leaf-300">Trust &amp; Compliance</span>
                <h2 class="mt-3 text-3xl font-bold sm:text-4xl">Quality You Can Verify</h2>
            </div>
            <div class="reveal-stagger mt-12 grid grid-cols-1 gap-6 sm:grid-cols-2 lg:grid-cols-4">
                @foreach ($certifications as $certification)
                    <div class="rounded-2xl border border-leaf-800 bg-leaf-900/60 p-6 transition duration-300 hover:-translate-y-1 hover:border-leaf-600">
                        <span class="flex h-11 w-11 items-center justify-center rounded-full bg-leaf-700 text-white">
                            <x-icon :name="$certification->icon" class="h-5 w-5" />
                        </span>
                        <h3 class="mt-4 font-semibold">{{ $certification->title }}</h3>
                        <p class="mt-2 text-sm leading-relaxed text-leaf-200">{{ $certification->description }}</p>
                    </div>
                @endforeach
            </div>
        </div>
    </section>

    {{-- Testimonials --}}
    <section class="mx-auto max-w-7xl px-4 py-20 sm:px-6 lg:px-8">
        <div class="reveal mx-auto max-w-2xl text-center">
            <span class="text-sm font-semibold uppercase tracking-wide text-leaf-600">Testimonials</span>
            <h2 class="mt-3 text-3xl font-bold text-leaf-950 sm:text-4xl">What Our Partners Say</h2>
        </div>
        <div class="reveal-stagger mt-12 grid grid-cols-1 gap-6 lg:grid-cols-3">
            @foreach ($testimonials as $testimonial)
                <figure class="flex flex-col rounded-2xl border border-leaf-100 bg-leaf-50/50 p-7 transition duration-300 hover:-translate-y-1 hover:shadow-lg">
                    <x-icon name="quote" class="h-7 w-7 text-leaf-400" />
                    <blockquote class="mt-4 flex-1 text-sm leading-relaxed text-stone-700">
                        &ldquo;{{ $testimonial->quote }}&rdquo;
                    </blockquote>
                    <figcaption class="mt-6 border-t border-leaf-100 pt-4">
                        <p class="font-semibold text-stone-800">{{ $testimonial->name }}</p>
                        <p class="text-xs text-stone-500">{{ $testimonial->role }}, {{ $testimonial->organization }}</p>
                    </figcaption>
                </figure>
            @endforeach
        </div>
    </section>

    {{-- CTA --}}
    <section class="bg-sand-500">
        <div class="reveal mx-auto max-w-7xl px-4 py-16 text-center sm:px-6 lg:px-8">
            <h2 class="text-3xl font-bold text-white sm:text-4xl">Ready to Partner with Sales Care Plus MZG?</h2>
            <p class="mx-auto mt-4 max-w-xl text-white/90">
                Whether you run a pharmacy, clinic or hospital in Muzaffargarh and beyond, our team is
                ready to set up a reliable supply account for you.
            </p>
            <div class="mt-8 flex flex-wrap justify-center gap-4">
                <a href="{{ route('contact') }}" class="rounded-full bg-white px-6 py-3.5 font-semibold text-sand-700 shadow-sm transition duration-300 hover:-translate-y-0.5 hover:bg-sand-50 hover:shadow-lg">
                    Request a Callback
                </a>
                <a href="tel:{{ config('company.phone') }}" class="rounded-full border border-white/70 px-6 py-3.5 font-semibold text-white transition duration-300 hover:-translate-y-0.5 hover:bg-white/10">
                    Call {{ config('company.phone') }}
                </a>
            </div>
        </div>
    </section>

</x-layout>
