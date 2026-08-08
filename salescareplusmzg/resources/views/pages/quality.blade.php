<x-layout title="Quality & Certifications" description="Discover the regulatory registrations, licences and quality practices behind Sales Care Plus MZG's medicine distribution operations.">

    <section class="bg-leaf-pattern bg-leaf-50 py-16 sm:py-20">
        <div class="mx-auto max-w-4xl px-4 text-center sm:px-6 lg:px-8">
            <span class="inline-flex items-center gap-2 rounded-full bg-leaf-100 px-4 py-1.5 text-sm font-medium text-leaf-700">
                <x-icon name="badge-check" class="h-4 w-4" /> Quality &amp; Compliance
            </span>
            <h1 class="mt-6 text-4xl font-bold tracking-tight text-leaf-950 sm:text-5xl">Quality You Can Verify</h1>
            <p class="mt-6 text-lg leading-relaxed text-stone-600">
                Every medicine that leaves our warehouse is handled under strict quality and regulatory
                standards — because the people receiving it deserve nothing less.
            </p>
        </div>
    </section>

    <section class="mx-auto max-w-7xl px-4 py-20 sm:px-6 lg:px-8">
        <div class="grid grid-cols-1 gap-6 sm:grid-cols-2">
            @foreach ($certifications as $certification)
                <div class="flex gap-5 rounded-2xl border border-stone-100 p-7 shadow-sm">
                    <span class="flex h-12 w-12 shrink-0 items-center justify-center rounded-xl bg-leaf-100 text-leaf-700">
                        <x-icon :name="$certification->icon" class="h-6 w-6" />
                    </span>
                    <div>
                        <h3 class="font-bold text-leaf-950">{{ $certification->title }}</h3>
                        <p class="text-xs font-medium uppercase tracking-wide text-stone-400">{{ $certification->issuing_body }}</p>
                        <p class="mt-2 text-sm leading-relaxed text-stone-600">{{ $certification->description }}</p>
                    </div>
                </div>
            @endforeach
        </div>
    </section>

    <section class="bg-leaf-950 py-20 text-white">
        <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
            <div class="mx-auto max-w-2xl text-center">
                <span class="text-sm font-semibold uppercase tracking-wide text-leaf-300">Our Standards</span>
                <h2 class="mt-3 text-3xl font-bold sm:text-4xl">How We Protect Product Quality</h2>
            </div>
            <div class="mt-14 grid grid-cols-1 gap-6 sm:grid-cols-2 lg:grid-cols-4">
                @foreach ([
                    ['icon' => 'thermometer', 'title' => 'Temperature Monitoring', 'text' => 'Continuous monitoring of storage areas to keep sensitive medicines within safe ranges.'],
                    ['icon' => 'file-check', 'title' => 'Batch Traceability', 'text' => 'Every product is tracked by batch and expiry date from receipt to dispatch.'],
                    ['icon' => 'shield', 'title' => 'Verified Sourcing', 'text' => 'We only stock products from licensed, DRAP-registered manufacturers and importers.'],
                    ['icon' => 'clock', 'title' => 'Stock Rotation', 'text' => 'First-expiry-first-out handling ensures pharmacies always receive fresh stock.'],
                ] as $standard)
                    <div class="rounded-2xl border border-leaf-800 bg-leaf-900/60 p-6">
                        <span class="flex h-11 w-11 items-center justify-center rounded-full bg-leaf-700 text-white">
                            <x-icon :name="$standard['icon']" class="h-5 w-5" />
                        </span>
                        <h3 class="mt-4 font-semibold">{{ $standard['title'] }}</h3>
                        <p class="mt-2 text-sm leading-relaxed text-leaf-200">{{ $standard['text'] }}</p>
                    </div>
                @endforeach
            </div>
        </div>
    </section>

</x-layout>
