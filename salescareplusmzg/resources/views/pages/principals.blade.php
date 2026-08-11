<x-layout title="Our Principals" description="Sales Care Plus MZG is the authorized distributor for leading pharmaceutical manufacturers, proudly representing trusted brands across Muzaffargarh and South Punjab.">

    <section class="bg-teal-pattern py-16 sm:py-20">
        <div class="mx-auto max-w-4xl px-4 text-center sm:px-6 lg:px-8">
            <span class="hero-fade-in inline-flex items-center gap-2 rounded-full bg-white/10 px-4 py-1.5 text-sm font-medium text-coral-300 ring-1 ring-white/10">
                <x-icon name="building" class="h-4 w-4" /> Our Principals
            </span>
            <h1 class="hero-fade-in hero-fade-in-delay-1 mt-6 text-4xl font-bold tracking-tight text-white sm:text-5xl">Trusted Manufacturer Partners</h1>
            <p class="hero-fade-in hero-fade-in-delay-2 mt-6 text-lg leading-relaxed text-teal-200">
                Proudly representing South Punjab's most respected pharmaceutical manufacturers.
            </p>
        </div>
    </section>

    <section class="mx-auto max-w-7xl px-4 py-20 sm:px-6 lg:px-8">
        <div class="reveal mx-auto max-w-2xl text-center">
            <h2 class="text-3xl font-bold text-teal-900 sm:text-4xl">
                Authorized Distributor for <span class="text-coral-500">{{ $principals->count() }}</span> Leading Companies
            </h2>
            <p class="mt-4 text-slate-600">
                We are proud to partner with these trusted pharmaceutical manufacturers to serve
                healthcare providers across Muzaffargarh and South Punjab.
            </p>
        </div>

        <div class="reveal-stagger mt-14 grid grid-cols-1 gap-6 md:grid-cols-2">
            @foreach ($principals as $principal)
                <div class="flex gap-5 rounded-2xl border border-slate-100 bg-white p-6 shadow-sm transition duration-300 hover:-translate-y-1 hover:shadow-lg">
                    <span class="flex h-16 w-16 shrink-0 items-center justify-center rounded-xl bg-teal-900 text-lg font-bold text-coral-400">
                        {{ $principal->initials }}
                    </span>
                    <div>
                        <h3 class="text-lg font-bold text-teal-900">{{ $principal->name }}</h3>
                        <p class="mt-0.5 text-sm text-slate-500">{{ $principal->tagline }}</p>
                        <div class="mt-3 flex flex-wrap gap-2">
                            <span class="inline-flex items-center gap-1 rounded-full bg-coral-50 px-2.5 py-1 text-[11px] font-semibold text-coral-700">
                                <x-icon name="check-circle" class="h-3 w-3" /> Authorized Distributor
                            </span>
                            <span class="inline-flex items-center gap-1 rounded-full bg-amber-50 px-2.5 py-1 text-[11px] font-semibold text-amber-700">
                                <x-icon name="star" class="h-3 w-3" /> Trusted Partner
                            </span>
                        </div>
                        <dl class="mt-4 grid grid-cols-3 gap-2 border-t border-slate-100 pt-4">
                            <div>
                                <dt class="text-lg font-bold text-teal-900">{{ $principal->years_partnership }}+</dt>
                                <dd class="text-[11px] text-slate-500">Years Partnership</dd>
                            </div>
                            <div>
                                <dt class="text-lg font-bold text-teal-900">{{ $principal->products_count }}+</dt>
                                <dd class="text-[11px] text-slate-500">Products</dd>
                            </div>
                            <div>
                                <dt class="text-lg font-bold text-teal-900">100%</dt>
                                <dd class="text-[11px] text-slate-500">Quality Assured</dd>
                            </div>
                        </dl>
                    </div>
                </div>
            @endforeach
        </div>
    </section>

    <section class="bg-slate-50 py-20">
        <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
            <div class="reveal mx-auto max-w-2xl text-center">
                <span class="text-sm font-semibold uppercase tracking-wide text-coral-600">Partnership</span>
                <h2 class="mt-3 text-3xl font-bold text-teal-900 sm:text-4xl">Why Partner With Us?</h2>
                <p class="mt-4 text-slate-600">We provide comprehensive distribution services to our principal partners.</p>
            </div>
            <div class="reveal-stagger mt-12 grid grid-cols-1 gap-6 sm:grid-cols-2 lg:grid-cols-4">
                @foreach ([
                    ['icon' => 'truck', 'title' => 'Own Fleet', 'text' => 'Dedicated logistics fleet for timely deliveries across South Punjab.'],
                    ['icon' => 'warehouse', 'title' => 'Modern Warehousing', 'text' => 'Temperature-controlled storage facilities for product integrity.'],
                    ['icon' => 'headset', 'title' => '24/7 Support', 'text' => 'Dedicated customer service team for all your needs.'],
                    ['icon' => 'globe', 'title' => 'Market Coverage', 'text' => 'Extensive reach across South Punjab with 45+ professionals.'],
                ] as $reason)
                    <div class="rounded-2xl border border-slate-100 bg-white p-6 text-center shadow-sm transition duration-300 hover:-translate-y-1 hover:shadow-lg">
                        <span class="mx-auto flex h-12 w-12 items-center justify-center rounded-full bg-coral-100 text-coral-600 transition-transform duration-300 hover:scale-110">
                            <x-icon :name="$reason['icon']" class="h-6 w-6" />
                        </span>
                        <h3 class="mt-4 font-semibold text-teal-900">{{ $reason['title'] }}</h3>
                        <p class="mt-2 text-sm leading-relaxed text-slate-500">{{ $reason['text'] }}</p>
                    </div>
                @endforeach
            </div>
        </div>
    </section>

    <section class="bg-gradient-to-br from-teal-900 to-teal-950">
        <div class="reveal mx-auto max-w-7xl px-4 py-16 text-center sm:px-6 lg:px-8">
            <h2 class="text-3xl font-bold text-white sm:text-4xl">Interested in a Distribution Partnership?</h2>
            <p class="mx-auto mt-4 max-w-xl text-teal-300">
                We're always open to representing new pharmaceutical manufacturers across South Punjab.
            </p>
            <a href="{{ route('contact') }}" class="mt-8 inline-flex items-center gap-2 rounded-full bg-coral-500 px-6 py-3.5 font-semibold text-white shadow-sm transition duration-300 hover:-translate-y-0.5 hover:bg-coral-400 hover:shadow-lg">
                Discuss a Partnership <x-icon name="arrow-right" class="h-4 w-4" />
            </a>
        </div>
    </section>

</x-layout>
