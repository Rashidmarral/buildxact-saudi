<x-layout title="Our Services" description="From warehousing to last-mile delivery, discover the distribution services Sales Care Plus MZG provides to pharmacies, clinics and hospitals in Muzaffargarh.">

    <section class="bg-leaf-pattern bg-leaf-50 py-16 sm:py-20">
        <div class="mx-auto max-w-4xl px-4 text-center sm:px-6 lg:px-8">
            <span class="hero-fade-in inline-flex items-center gap-2 rounded-full bg-leaf-100 px-4 py-1.5 text-sm font-medium text-leaf-700">
                <x-icon name="truck" class="h-4 w-4" /> What We Do
            </span>
            <h1 class="hero-fade-in hero-fade-in-delay-1 mt-6 text-4xl font-bold tracking-tight text-leaf-950 sm:text-5xl">End-to-End Distribution Services</h1>
            <p class="hero-fade-in hero-fade-in-delay-2 mt-6 text-lg leading-relaxed text-stone-600">
                We handle every step between the manufacturer's gate and the pharmacy shelf, so our
                partners can focus on caring for patients.
            </p>
        </div>
    </section>

    <section class="mx-auto max-w-7xl px-4 py-20 sm:px-6 lg:px-8">
        <div class="reveal-stagger grid grid-cols-1 gap-6 md:grid-cols-2 lg:grid-cols-3">
            @foreach ([
                ['icon' => 'warehouse', 'title' => 'Bulk Warehousing', 'text' => 'Climate-controlled storage across our Muzaffargarh facility, organised by category and batch for fast, accurate order picking.'],
                ['icon' => 'thermometer', 'title' => 'Cold-Chain Handling', 'text' => 'Temperature-sensitive medicines are stored and transported under monitored conditions, preserving potency from warehouse to shelf.'],
                ['icon' => 'truck', 'title' => 'Last-Mile Delivery', 'text' => 'A dedicated local fleet covering Muzaffargarh, Alipur, Kot Addu and Jatoi with scheduled, reliable delivery routes.'],
                ['icon' => 'file-check', 'title' => 'Order & Inventory Management', 'text' => 'Simple ordering, transparent invoicing, and proactive stock alerts so partner pharmacies never run short.'],
                ['icon' => 'shield', 'title' => 'Regulatory Compliance', 'text' => 'Every product we distribute is sourced from DRAP-registered manufacturers and handled under GDP guidelines.'],
                ['icon' => 'users', 'title' => 'Dedicated Account Support', 'text' => 'A named representative for every partner pharmacy — for order queries, product information and account support.'],
            ] as $service)
                <div class="rounded-2xl border border-stone-100 p-7 shadow-sm transition duration-300 hover:-translate-y-1.5 hover:shadow-lg">
                    <span class="flex h-12 w-12 items-center justify-center rounded-xl bg-leaf-100 text-leaf-700 transition-transform duration-300 hover:scale-110">
                        <x-icon :name="$service['icon']" class="h-6 w-6" />
                    </span>
                    <h3 class="mt-5 text-lg font-bold text-leaf-950">{{ $service['title'] }}</h3>
                    <p class="mt-3 leading-relaxed text-stone-600">{{ $service['text'] }}</p>
                </div>
            @endforeach
        </div>
    </section>

    <section class="bg-leaf-50/60 py-20">
        <div class="mx-auto max-w-5xl px-4 sm:px-6 lg:px-8">
            <div class="reveal mx-auto max-w-2xl text-center">
                <span class="text-sm font-semibold uppercase tracking-wide text-leaf-600">How It Works</span>
                <h2 class="mt-3 text-3xl font-bold text-leaf-950 sm:text-4xl">Getting Supplied, Simplified</h2>
            </div>
            <ol class="reveal-stagger mt-14 grid grid-cols-1 gap-8 sm:grid-cols-2 lg:grid-cols-4">
                @foreach ([
                    ['step' => '01', 'title' => 'Set Up Your Account', 'text' => 'Share your pharmacy or hospital details and licence — our team verifies and opens your account.'],
                    ['step' => '02', 'title' => 'Browse & Order', 'text' => 'Get our current catalogue and pricing, then place orders by phone, WhatsApp, or in person.'],
                    ['step' => '03', 'title' => 'We Pick & Pack', 'text' => 'Your order is picked from our GDP-compliant warehouse and quality-checked before dispatch.'],
                    ['step' => '04', 'title' => 'On-Time Delivery', 'text' => 'Our fleet delivers to your door on schedule, with clear invoicing every time.'],
                ] as $item)
                    <li class="relative rounded-2xl bg-white p-6 shadow-sm transition duration-300 hover:-translate-y-1 hover:shadow-lg">
                        <span class="text-3xl font-bold text-leaf-200">{{ $item['step'] }}</span>
                        <h3 class="mt-3 font-semibold text-stone-800">{{ $item['title'] }}</h3>
                        <p class="mt-2 text-sm leading-relaxed text-stone-500">{{ $item['text'] }}</p>
                    </li>
                @endforeach
            </ol>
        </div>
    </section>

    <section class="bg-sand-500">
        <div class="reveal mx-auto max-w-7xl px-4 py-16 text-center sm:px-6 lg:px-8">
            <h2 class="text-3xl font-bold text-white sm:text-4xl">Let's Set Up Your Supply Account</h2>
            <p class="mx-auto mt-4 max-w-xl text-white/90">Reach out today and our team will guide you through onboarding in minutes.</p>
            <a href="{{ route('contact') }}" class="mt-8 inline-flex items-center gap-2 rounded-full bg-white px-6 py-3.5 font-semibold text-sand-700 shadow-sm transition duration-300 hover:-translate-y-0.5 hover:bg-sand-50 hover:shadow-lg">
                Contact Us <x-icon name="arrow-right" class="h-4 w-4" />
            </a>
        </div>
    </section>

</x-layout>
