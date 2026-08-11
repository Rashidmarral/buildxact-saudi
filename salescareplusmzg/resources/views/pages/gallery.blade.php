<x-layout title="Gallery" description="A look inside Sales Care Plus MZG's warehouse, delivery operations and team in Muzaffargarh, Pakistan.">

    <section class="bg-teal-pattern py-16 sm:py-20">
        <div class="mx-auto max-w-4xl px-4 text-center sm:px-6 lg:px-8">
            <span class="hero-fade-in inline-flex items-center gap-2 rounded-full bg-white/10 px-4 py-1.5 text-sm font-medium text-coral-300 ring-1 ring-white/10">
                <x-icon name="building" class="h-4 w-4" /> Gallery
            </span>
            <h1 class="hero-fade-in hero-fade-in-delay-1 mt-6 text-4xl font-bold tracking-tight text-white sm:text-5xl">A Look Inside Our Operations</h1>
            <p class="hero-fade-in hero-fade-in-delay-2 mt-6 text-lg leading-relaxed text-teal-200">
                From our warehouse floor to the roads of Muzaffargarh — here's how we keep medicines
                moving.
            </p>
        </div>
    </section>

    <section class="mx-auto max-w-7xl px-4 py-20 sm:px-6 lg:px-8">
        <div class="reveal-stagger grid grid-cols-1 gap-6 sm:grid-cols-2 lg:grid-cols-3">
            @foreach ([
                ['illustration' => 'warehouse', 'label' => 'Our Warehouse', 'text' => 'GDP-compliant storage in Muzaffargarh, organised by category and batch.'],
                ['illustration' => 'cold-chain', 'label' => 'Cold-Chain Storage', 'text' => 'Monitored temperature-controlled units for sensitive medicines.'],
                ['illustration' => 'delivery', 'label' => 'Delivery Fleet', 'text' => 'Daily routes covering Muzaffargarh, Alipur, Kot Addu and Jatoi.'],
                ['illustration' => 'medicines', 'label' => 'Medicine Handling', 'text' => 'Careful picking and packing across our full product catalogue.'],
                ['illustration' => 'pharmacist', 'label' => 'Quality Checks', 'text' => 'Batch verification and quality assurance before every dispatch.'],
                ['illustration' => 'team', 'label' => 'Our Team', 'text' => 'The people behind every order, from warehouse to doorstep.'],
            ] as $item)
                <div class="group overflow-hidden rounded-2xl border border-teal-100 bg-white shadow-sm transition duration-300 hover:-translate-y-1 hover:shadow-xl">
                    <div class="flex aspect-[4/3] items-center justify-center bg-teal-50 p-6 transition-transform duration-300 group-hover:scale-105">
                        <x-illustration :name="$item['illustration']" class="h-full w-full" />
                    </div>
                    <div class="p-5">
                        <h3 class="font-semibold text-teal-900">{{ $item['label'] }}</h3>
                        <p class="mt-1.5 text-sm leading-relaxed text-slate-500">{{ $item['text'] }}</p>
                    </div>
                </div>
            @endforeach
        </div>
        <p class="reveal mt-10 text-center text-sm text-slate-500">
            Real photos from our facility are coming soon — contact us to arrange a warehouse visit in the meantime.
        </p>
    </section>

</x-layout>
