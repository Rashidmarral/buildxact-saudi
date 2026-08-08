<x-layout title="Careers" description="Join the Sales Care Plus MZG team in Muzaffargarh — opportunities in sales, logistics, warehousing and pharmacy support.">

    <section class="bg-leaf-pattern bg-leaf-50 py-16 sm:py-20">
        <div class="mx-auto max-w-4xl px-4 text-center sm:px-6 lg:px-8">
            <span class="inline-flex items-center gap-2 rounded-full bg-leaf-100 px-4 py-1.5 text-sm font-medium text-leaf-700">
                <x-icon name="users" class="h-4 w-4" /> Careers
            </span>
            <h1 class="mt-6 text-4xl font-bold tracking-tight text-leaf-950 sm:text-5xl">Build Your Career With Us</h1>
            <p class="mt-6 text-lg leading-relaxed text-stone-600">
                We're always looking for reliable, caring people to join our warehouse, sales and
                logistics teams in Muzaffargarh.
            </p>
        </div>
    </section>

    <section class="mx-auto max-w-7xl px-4 py-20 sm:px-6 lg:px-8">
        <div class="grid grid-cols-1 gap-6 md:grid-cols-2">
            @foreach ([
                ['title' => 'Medical Sales Representative', 'type' => 'Full-time · Muzaffargarh', 'text' => 'Build relationships with pharmacies and clinics, manage orders and grow our partner network across your territory.'],
                ['title' => 'Warehouse & Inventory Officer', 'type' => 'Full-time · Muzaffargarh', 'text' => 'Manage stock receiving, storage conditions, batch tracking and order picking in our distribution warehouse.'],
                ['title' => 'Delivery Rider / Driver', 'type' => 'Full-time · Muzaffargarh & nearby tehsils', 'text' => 'Deliver medicines safely and on schedule to pharmacies and hospitals across our coverage area.'],
                ['title' => 'Customer Support Officer', 'type' => 'Full-time · Muzaffargarh', 'text' => 'Handle pharmacy order queries, account support and coordination between sales and warehouse teams.'],
            ] as $job)
                <div class="rounded-2xl border border-stone-100 p-7 shadow-sm">
                    <h3 class="text-lg font-bold text-leaf-950">{{ $job['title'] }}</h3>
                    <p class="mt-1 text-xs font-medium uppercase tracking-wide text-leaf-600">{{ $job['type'] }}</p>
                    <p class="mt-3 leading-relaxed text-stone-600">{{ $job['text'] }}</p>
                    <a href="{{ route('contact') }}" class="mt-5 inline-flex items-center gap-1.5 text-sm font-semibold text-leaf-700 hover:text-leaf-800">
                        Apply now <x-icon name="arrow-right" class="h-3.5 w-3.5" />
                    </a>
                </div>
            @endforeach
        </div>

        <div class="mt-14 rounded-2xl bg-leaf-50/60 p-8 text-center">
            <h2 class="text-xl font-bold text-leaf-950">Don't see a role that fits?</h2>
            <p class="mt-2 text-stone-600">Send us your CV through the contact form — we're always happy to hear from motivated people.</p>
            <a href="{{ route('contact') }}" class="mt-5 inline-flex items-center gap-2 rounded-full bg-leaf-600 px-6 py-3 font-semibold text-white transition hover:bg-leaf-700">
                Get in Touch
            </a>
        </div>
    </section>

</x-layout>
