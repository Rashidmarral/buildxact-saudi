<x-layout title="Home" description="Sales Care Plus MZG — a trusted pharmaceutical distribution company headquartered in Muzaffargarh, Pakistan, delivering healthcare with trust and excellence across South Punjab.">

    {{-- Hero --}}
    <section class="relative overflow-hidden bg-navy-pattern">
        <div class="mx-auto grid max-w-7xl items-center gap-12 px-4 py-20 sm:px-6 lg:grid-cols-2 lg:px-8 lg:py-28">
            <div>
                <span class="hero-fade-in inline-flex items-center gap-2 rounded-full bg-white/10 px-4 py-1.5 text-sm font-medium text-sky-300 ring-1 ring-white/10">
                    <x-icon name="badge-check" class="h-4 w-4" /> Serving South Punjab since {{ config('company.founded_year') }}
                </span>
                <h1 class="hero-fade-in hero-fade-in-delay-1 mt-6 text-balance text-4xl font-bold tracking-tight text-white sm:text-5xl lg:text-6xl">
                    Delivering Healthcare with <span class="text-sky-400">Trust &amp; Excellence</span>
                </h1>
                <p class="hero-fade-in hero-fade-in-delay-2 mt-6 max-w-xl text-lg leading-relaxed text-navy-200">
                    Sales Care Plus MZG is a leading pharmaceutical distribution organisation serving
                    healthcare providers, pharmacies, hospitals and institutions across Muzaffargarh
                    and South Punjab — proudly representing trusted manufacturers.
                </p>
                <div class="hero-fade-in hero-fade-in-delay-3 mt-8 flex flex-wrap gap-4">
                    <a href="{{ route('catalog.index') }}" class="inline-flex items-center gap-2 rounded-full bg-sky-500 px-6 py-3.5 font-semibold text-white shadow-sm transition duration-300 hover:-translate-y-0.5 hover:bg-sky-400 hover:shadow-lg">
                        Explore Catalog <x-icon name="arrow-right" class="h-4 w-4" />
                    </a>
                    <a href="{{ route('contact') }}" class="inline-flex items-center gap-2 rounded-full border border-white/25 px-6 py-3.5 font-semibold text-white transition duration-300 hover:-translate-y-0.5 hover:bg-white/10">
                        Contact Us
                    </a>
                </div>
                <dl class="hero-fade-in hero-fade-in-delay-3 mt-12 grid grid-cols-3 gap-6 border-t border-white/10 pt-8">
                    <div>
                        <dt class="text-2xl font-bold text-white sm:text-3xl"><span data-counter="{{ config('company.stats.years') }}" data-counter-suffix="+">{{ config('company.stats.years') }}+</span></dt>
                        <dd class="mt-1 text-sm text-navy-300">Years of Excellence</dd>
                    </div>
                    <div>
                        <dt class="text-2xl font-bold text-white sm:text-3xl"><span data-counter="{{ config('company.stats.professionals') }}" data-counter-suffix="+">{{ config('company.stats.professionals') }}+</span></dt>
                        <dd class="mt-1 text-sm text-navy-300">Professionals</dd>
                    </div>
                    <div>
                        <dt class="text-2xl font-bold text-white sm:text-3xl"><span data-counter="{{ $principals->count() }}">{{ $principals->count() }}</span></dt>
                        <dd class="mt-1 text-sm text-navy-300">Principal Companies</dd>
                    </div>
                </dl>
            </div>

            <div class="reveal-scale relative hidden lg:block">
                <div class="aspect-[4/5] w-full overflow-hidden rounded-[2.5rem] bg-white/5 shadow-2xl ring-1 ring-white/10">
                    <div class="flex h-full w-full flex-col items-center justify-center gap-6 p-8 text-center">
                        <div class="animate-float w-full max-w-xs">
                            <x-illustration name="medicines" class="w-full drop-shadow-2xl" />
                        </div>
                        <p class="text-sm uppercase tracking-[0.3em] text-sky-300">Quality Assured &middot; On Time</p>
                    </div>
                </div>
                <div class="absolute -bottom-6 -left-6 animate-float rounded-2xl bg-white p-5 shadow-xl">
                    <div class="flex items-center gap-3">
                        <span class="flex h-11 w-11 items-center justify-center rounded-full bg-sky-100 text-sky-600">
                            <x-icon name="truck" class="h-5 w-5" />
                        </span>
                        <div>
                            <p class="text-sm font-semibold text-navy-900">Same-Day Dispatch</p>
                            <p class="text-xs text-slate-500">Across Muzaffargarh &amp; nearby tehsils</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    {{-- Stats bar --}}
    <section class="border-b border-slate-100 bg-white">
        <div class="reveal-stagger mx-auto grid max-w-7xl grid-cols-2 gap-6 px-4 py-10 sm:px-6 md:grid-cols-4 lg:px-8">
            @foreach ([
                ['icon' => 'star', 'value' => config('company.stats.years').'+', 'label' => 'Years of Excellence'],
                ['icon' => 'users', 'value' => config('company.stats.professionals').'+', 'label' => 'Professionals'],
                ['icon' => 'building', 'value' => $principals->count(), 'label' => 'Principal Companies'],
                ['icon' => 'globe', 'value' => config('company.stats.monthly_reach'), 'label' => 'Pharmacies Reached Monthly'],
            ] as $stat)
                <div class="flex flex-col items-center gap-2 text-center">
                    <span class="flex h-12 w-12 items-center justify-center rounded-full bg-sky-50 text-sky-600">
                        <x-icon :name="$stat['icon']" class="h-6 w-6" />
                    </span>
                    <p class="text-2xl font-bold text-navy-900">{{ $stat['value'] }}</p>
                    <p class="text-xs text-slate-500">{{ $stat['label'] }}</p>
                </div>
            @endforeach
        </div>
    </section>

    {{-- Services --}}
    <section class="bg-slate-50 py-20">
        <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
            <div class="reveal mx-auto max-w-2xl text-center">
                <span class="text-sm font-semibold uppercase tracking-wide text-sky-600">Our Services</span>
                <h2 class="mt-3 text-3xl font-bold text-navy-900 sm:text-4xl">Comprehensive Pharmaceutical Services</h2>
                <p class="mt-4 text-slate-600">We provide end-to-end pharmaceutical distribution services with unmatched professionalism and care.</p>
            </div>
            <div class="reveal-stagger mt-12 grid grid-cols-1 gap-5 sm:grid-cols-2 lg:grid-cols-4">
                @foreach ([
                    ['icon' => 'truck', 'title' => 'Distribution & Logistics', 'text' => 'Own fleet of temperature-controlled vehicles ensuring timely delivery across South Punjab.'],
                    ['icon' => 'warehouse', 'title' => 'Warehousing & Storage', 'text' => 'State-of-the-art storage facilities with proper conditions for pharmaceutical products.'],
                    ['icon' => 'headset', 'title' => 'Customer Support', 'text' => 'Dedicated customer service team available to handle enquiries, orders and support.'],
                    ['icon' => 'shield', 'title' => 'Quality Assurance', 'text' => 'Rigorous quality control preserving product integrity that meets the highest standards.'],
                ] as $service)
                    <div class="group rounded-2xl border border-slate-100 bg-white p-6 shadow-sm transition duration-300 hover:-translate-y-1.5 hover:shadow-lg">
                        <span class="flex h-12 w-12 items-center justify-center rounded-xl bg-navy-900 text-sky-400 transition-transform duration-300 group-hover:scale-110">
                            <x-icon :name="$service['icon']" class="h-6 w-6" />
                        </span>
                        <h3 class="mt-4 font-semibold text-navy-900">{{ $service['title'] }}</h3>
                        <p class="mt-2 text-sm leading-relaxed text-slate-500">{{ $service['text'] }}</p>
                    </div>
                @endforeach
            </div>
        </div>
    </section>

    {{-- How we work --}}
    <section class="mx-auto max-w-7xl px-4 py-20 sm:px-6 lg:px-8">
        <div class="grid gap-12 lg:grid-cols-2 lg:items-center">
            <div class="reveal">
                <span class="text-sm font-semibold uppercase tracking-wide text-sky-600">How We Work</span>
                <h2 class="mt-3 text-3xl font-bold text-navy-900 sm:text-4xl">Our Distribution Process</h2>
                <p class="mt-4 text-slate-600">We've streamlined our distribution process to ensure your orders reach you quickly and reliably.</p>
                <ol class="mt-8 space-y-6">
                    @foreach ([
                        ['step' => '01', 'title' => 'Order Placement', 'text' => 'Pharmacies and hospitals place orders through our sales representatives or office.'],
                        ['step' => '02', 'title' => 'Picking & Packing', 'text' => 'Orders are carefully picked and packed with strict quality checks at every step.'],
                        ['step' => '03', 'title' => 'Same-Day Dispatch', 'text' => 'Orders are dispatched same-day wherever our logistics coverage allows.'],
                        ['step' => '04', 'title' => 'On-Time Delivery', 'text' => 'Delivery to your pharmacy, hospital or clinic by a responsive support team.'],
                    ] as $item)
                        <li class="flex items-start gap-4">
                            <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-navy-900 text-sm font-bold text-sky-400">{{ $item['step'] }}</span>
                            <div>
                                <h3 class="font-semibold text-navy-900">{{ $item['title'] }}</h3>
                                <p class="mt-1 text-sm leading-relaxed text-slate-500">{{ $item['text'] }}</p>
                            </div>
                        </li>
                    @endforeach
                </ol>
            </div>
            <div class="reveal-scale flex aspect-square items-center justify-center overflow-hidden rounded-3xl bg-navy-50 p-10">
                <x-illustration name="delivery" class="w-full" />
            </div>
        </div>
    </section>

    {{-- Trusted manufacturers --}}
    <section class="bg-navy-950 py-20 text-white">
        <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
            <div class="reveal mx-auto max-w-2xl text-center">
                <span class="text-sm font-semibold uppercase tracking-wide text-sky-400">Our Partners</span>
                <h2 class="mt-3 text-3xl font-bold sm:text-4xl">Trusted Manufacturers</h2>
                <p class="mt-4 text-navy-300">Proudly representing {{ $principals->count() }} of the region's respected pharmaceutical companies.</p>
            </div>
            <div class="reveal-stagger mt-12 grid grid-cols-2 gap-5 sm:grid-cols-4">
                @foreach ($principals as $principal)
                    <a href="{{ route('principals') }}" class="group flex flex-col items-center gap-3 rounded-2xl border border-navy-800 bg-navy-900/60 p-6 text-center transition duration-300 hover:-translate-y-1 hover:border-sky-500">
                        <span class="flex h-14 w-14 items-center justify-center rounded-full bg-navy-800 text-lg font-bold text-sky-400 transition-transform duration-300 group-hover:scale-110">
                            {{ $principal->initials }}
                        </span>
                        <span class="text-sm font-medium">{{ $principal->name }}</span>
                    </a>
                @endforeach
            </div>
            <div class="reveal mt-10 text-center">
                <a href="{{ route('principals') }}" class="inline-flex items-center gap-2 rounded-full border border-white/25 px-6 py-3 font-semibold text-white transition duration-300 hover:bg-white/10">
                    View All Principals <x-icon name="arrow-right" class="h-4 w-4" />
                </a>
            </div>
        </div>
    </section>

    {{-- Coverage --}}
    <section class="mx-auto max-w-7xl px-4 py-20 sm:px-6 lg:px-8">
        <div class="grid gap-12 lg:grid-cols-2 lg:items-center">
            <div class="reveal-scale flex aspect-[4/3] items-center justify-center overflow-hidden rounded-3xl bg-sky-50 p-10">
                <x-illustration name="warehouse" class="w-full" />
            </div>
            <div class="reveal">
                <span class="text-sm font-semibold uppercase tracking-wide text-sky-600">Coverage Area</span>
                <h2 class="mt-3 text-3xl font-bold text-navy-900 sm:text-4xl">Serving South Punjab</h2>
                <p class="mt-4 text-slate-600">Our distribution network reaches pharmacies, hospitals and clinics across the region.</p>
                <ul class="mt-6 grid grid-cols-2 gap-3">
                    @foreach (config('company.coverage_areas') as $area)
                        <li class="flex items-center gap-2 text-sm text-slate-700">
                            <x-icon name="map-pin" class="h-4 w-4 shrink-0 text-sky-500" /> {{ $area }}
                        </li>
                    @endforeach
                </ul>
            </div>
        </div>
    </section>

    {{-- Testimonials --}}
    @if ($testimonials->isNotEmpty())
        <section class="bg-slate-50 py-20">
            <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                <div class="reveal mx-auto max-w-2xl text-center">
                    <span class="text-sm font-semibold uppercase tracking-wide text-sky-600">Testimonials</span>
                    <h2 class="mt-3 text-3xl font-bold text-navy-900 sm:text-4xl">What Our Clients Say</h2>
                    <p class="mt-4 text-slate-600">Hear from healthcare professionals who trust us for their pharmaceutical needs.</p>
                </div>
                <div class="reveal-stagger mt-12 grid grid-cols-1 gap-6 lg:grid-cols-3">
                    @foreach ($testimonials as $testimonial)
                        <figure class="flex flex-col rounded-2xl border border-slate-100 bg-white p-7 shadow-sm transition duration-300 hover:-translate-y-1 hover:shadow-lg">
                            <div class="flex gap-1 text-sky-500">
                                @for ($i = 0; $i < $testimonial->rating; $i++)
                                    <x-icon name="star" class="h-4 w-4 fill-current" />
                                @endfor
                            </div>
                            <blockquote class="mt-4 flex-1 text-sm leading-relaxed text-slate-600">
                                &ldquo;{{ $testimonial->quote }}&rdquo;
                            </blockquote>
                            <figcaption class="mt-6 border-t border-slate-100 pt-4">
                                <p class="font-semibold text-navy-900">{{ $testimonial->name }}</p>
                                <p class="text-xs text-slate-500">{{ $testimonial->role }}, {{ $testimonial->organization }}</p>
                            </figcaption>
                        </figure>
                    @endforeach
                </div>
            </div>
        </section>
    @endif

    {{-- CTA --}}
    <section class="bg-gradient-to-br from-navy-900 to-navy-950">
        <div class="reveal mx-auto max-w-7xl px-4 py-16 text-center sm:px-6 lg:px-8">
            <h2 class="text-3xl font-bold text-white sm:text-4xl">Ready to Partner with a Trusted Distributor?</h2>
            <p class="mx-auto mt-4 max-w-xl text-navy-300">
                Join hands with us for reliable pharmaceutical distribution across South Punjab.
            </p>
            <div class="mt-8 flex flex-wrap justify-center gap-4">
                <a href="{{ route('contact') }}" class="inline-flex items-center gap-2 rounded-full bg-sky-500 px-6 py-3.5 font-semibold text-white shadow-sm transition duration-300 hover:-translate-y-0.5 hover:bg-sky-400 hover:shadow-lg">
                    Get in Touch Now
                </a>
                <a href="tel:{{ config('company.phone') }}" class="inline-flex items-center gap-2 rounded-full border border-white/25 px-6 py-3.5 font-semibold text-white transition duration-300 hover:bg-white/10">
                    <x-icon name="phone" class="h-4 w-4" /> Call {{ config('company.phone') }}
                </a>
            </div>
        </div>
    </section>

</x-layout>
