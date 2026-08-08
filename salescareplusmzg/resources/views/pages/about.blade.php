<x-layout title="About Us" description="Learn about Sales Care Plus MZG's story, mission and the team behind Muzaffargarh's trusted medicine distribution company.">

    <section class="bg-leaf-pattern bg-leaf-50 py-16 sm:py-20">
        <div class="mx-auto max-w-4xl px-4 text-center sm:px-6 lg:px-8">
            <span class="inline-flex items-center gap-2 rounded-full bg-leaf-100 px-4 py-1.5 text-sm font-medium text-leaf-700">
                <x-icon name="leaf" class="h-4 w-4" /> About Sales Care Plus MZG
            </span>
            <h1 class="mt-6 text-4xl font-bold tracking-tight text-leaf-950 sm:text-5xl">Our Story, Rooted in Muzaffargarh</h1>
            <p class="mt-6 text-lg leading-relaxed text-stone-600">
                Since {{ config('company.founded_year') }}, we've grown from a single warehouse into one of
                Muzaffargarh's most dependable pharmaceutical distribution companies — built on honesty,
                consistency, and genuine care for the communities we serve.
            </p>
        </div>
    </section>

    <section class="mx-auto max-w-7xl px-4 py-20 sm:px-6 lg:px-8">
        <div class="grid gap-12 lg:grid-cols-2 lg:items-center">
            <div>
                <span class="text-sm font-semibold uppercase tracking-wide text-leaf-600">Our Journey</span>
                <h2 class="mt-3 text-3xl font-bold text-leaf-950">From One City, To an Entire Region</h2>
                <p class="mt-5 leading-relaxed text-stone-600">
                    Sales Care Plus MZG began with a simple goal: make sure every pharmacy in
                    Muzaffargarh could depend on a supplier who delivers what it promises. Over the
                    years, that goal expanded into a full-scale distribution operation covering
                    Muzaffargarh, Alipur, Kot Addu, Jatoi and the surrounding tehsils — supplying
                    branded and generic medicines across eight therapeutic categories.
                </p>
                <p class="mt-4 leading-relaxed text-stone-600">
                    Today, our warehouse follows Good Distribution Practice (GDP) storage standards,
                    our fleet runs daily delivery routes, and our team works directly with pharmacists
                    to keep their shelves stocked without gaps.
                </p>
            </div>
            <div class="grid grid-cols-2 gap-4">
                <div class="rounded-2xl bg-leaf-600 p-6 text-white">
                    <p class="text-3xl font-bold">{{ date('Y') - config('company.founded_year') }}+</p>
                    <p class="mt-1 text-sm text-leaf-100">Years serving Muzaffargarh</p>
                </div>
                <div class="rounded-2xl bg-sand-500 p-6 text-white">
                    <p class="text-3xl font-bold">300+</p>
                    <p class="mt-1 text-sm text-sand-50">Partner pharmacies</p>
                </div>
                <div class="rounded-2xl bg-leaf-100 p-6 text-leaf-900">
                    <p class="text-3xl font-bold">8</p>
                    <p class="mt-1 text-sm text-leaf-700">Therapeutic categories</p>
                </div>
                <div class="rounded-2xl bg-stone-100 p-6 text-stone-900">
                    <p class="text-3xl font-bold">24hr</p>
                    <p class="mt-1 text-sm text-stone-600">Average order fulfilment</p>
                </div>
            </div>
        </div>
    </section>

    <section class="bg-leaf-50/60 py-20">
        <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
            <div class="grid gap-6 md:grid-cols-2">
                <div class="rounded-2xl border border-leaf-100 bg-white p-8">
                    <span class="flex h-12 w-12 items-center justify-center rounded-xl bg-leaf-100 text-leaf-700">
                        <x-icon name="sprout" class="h-6 w-6" />
                    </span>
                    <h3 class="mt-5 text-xl font-bold text-leaf-950">Our Mission</h3>
                    <p class="mt-3 leading-relaxed text-stone-600">
                        To ensure every pharmacy, clinic and hospital we serve has uninterrupted access to
                        quality medicines — delivered on time, stored correctly, and priced fairly — so
                        the people of Muzaffargarh never have to wait for the care they need.
                    </p>
                </div>
                <div class="rounded-2xl border border-leaf-100 bg-white p-8">
                    <span class="flex h-12 w-12 items-center justify-center rounded-xl bg-sand-100 text-sand-700">
                        <x-icon name="sun" class="h-6 w-6" />
                    </span>
                    <h3 class="mt-5 text-xl font-bold text-leaf-950">Our Vision</h3>
                    <p class="mt-3 leading-relaxed text-stone-600">
                        To be South Punjab's most trusted name in medicine distribution — recognised for
                        integrity, reliability, and a genuine, nature-rooted commitment to community health.
                    </p>
                </div>
            </div>
        </div>
    </section>

    <section class="mx-auto max-w-7xl px-4 py-20 sm:px-6 lg:px-8">
        <div class="mx-auto max-w-2xl text-center">
            <span class="text-sm font-semibold uppercase tracking-wide text-leaf-600">Our Values</span>
            <h2 class="mt-3 text-3xl font-bold text-leaf-950 sm:text-4xl">What Guides Every Delivery</h2>
        </div>
        <div class="mt-12 grid grid-cols-1 gap-6 sm:grid-cols-2 lg:grid-cols-4">
            @foreach ([
                ['icon' => 'shield', 'title' => 'Integrity', 'text' => 'Transparent pricing and honest stock information, always.'],
                ['icon' => 'thermometer', 'title' => 'Quality', 'text' => 'GDP-compliant storage and careful handling from warehouse to shelf.'],
                ['icon' => 'truck', 'title' => 'Reliability', 'text' => 'Consistent, on-time delivery pharmacies can plan their business around.'],
                ['icon' => 'heart', 'title' => 'Care', 'text' => 'Every order represents a patient waiting — we never lose sight of that.'],
            ] as $value)
                <div class="rounded-2xl border border-stone-100 p-6 text-center shadow-sm">
                    <span class="mx-auto flex h-12 w-12 items-center justify-center rounded-full bg-leaf-100 text-leaf-700">
                        <x-icon :name="$value['icon']" class="h-6 w-6" />
                    </span>
                    <h3 class="mt-4 font-semibold text-stone-800">{{ $value['title'] }}</h3>
                    <p class="mt-2 text-sm leading-relaxed text-stone-500">{{ $value['text'] }}</p>
                </div>
            @endforeach
        </div>
    </section>

    <section class="bg-leaf-950 py-20 text-white">
        <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
            <div class="mx-auto max-w-2xl text-center">
                <span class="text-sm font-semibold uppercase tracking-wide text-leaf-300">Leadership</span>
                <h2 class="mt-3 text-3xl font-bold sm:text-4xl">Meet Our Team</h2>
            </div>
            <div class="mt-12 grid grid-cols-1 gap-6 sm:grid-cols-2 lg:grid-cols-4">
                @foreach ($team as $member)
                    <div class="rounded-2xl border border-leaf-800 bg-leaf-900/60 p-6 text-center">
                        <span class="mx-auto flex h-16 w-16 items-center justify-center rounded-full bg-leaf-600 text-lg font-bold text-white">
                            {{ $member->initials }}
                        </span>
                        <h3 class="mt-4 font-semibold">{{ $member->name }}</h3>
                        <p class="text-sm text-leaf-300">{{ $member->designation }}</p>
                        <p class="mt-3 text-xs leading-relaxed text-leaf-200">{{ $member->bio }}</p>
                    </div>
                @endforeach
            </div>
        </div>
    </section>

</x-layout>
