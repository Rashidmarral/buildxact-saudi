<x-layout title="Gallery" description="A look inside Sales Care Plus MZG's warehouse, delivery operations and team in Muzaffargarh, Pakistan.">

    <section class="bg-leaf-pattern bg-leaf-50 py-16 sm:py-20">
        <div class="mx-auto max-w-4xl px-4 text-center sm:px-6 lg:px-8">
            <span class="hero-fade-in inline-flex items-center gap-2 rounded-full bg-leaf-100 px-4 py-1.5 text-sm font-medium text-leaf-700">
                <x-icon name="leaf" class="h-4 w-4 animate-leaf-sway" /> Gallery
            </span>
            <h1 class="hero-fade-in hero-fade-in-delay-1 mt-6 text-4xl font-bold tracking-tight text-leaf-950 sm:text-5xl">A Look Inside Our Operations</h1>
            <p class="hero-fade-in hero-fade-in-delay-2 mt-6 text-lg leading-relaxed text-stone-600">
                From our warehouse floor to the roads of Muzaffargarh — here's how we keep medicines
                moving.
            </p>
        </div>
    </section>

    <section class="mx-auto max-w-7xl px-4 py-20 sm:px-6 lg:px-8">
        <div class="reveal-stagger grid grid-cols-2 gap-4 sm:grid-cols-3 lg:grid-cols-4">
            @foreach ([
                ['icon' => 'warehouse', 'label' => 'Our Warehouse', 'bg' => 'bg-leaf-600'],
                ['icon' => 'thermometer', 'label' => 'Cold-Chain Storage', 'bg' => 'bg-sand-500'],
                ['icon' => 'truck', 'label' => 'Delivery Fleet', 'bg' => 'bg-leaf-800'],
                ['icon' => 'pill', 'label' => 'Product Handling', 'bg' => 'bg-leaf-500'],
                ['icon' => 'users', 'label' => 'Our Team', 'bg' => 'bg-sand-600'],
                ['icon' => 'file-check', 'label' => 'Quality Checks', 'bg' => 'bg-leaf-700'],
                ['icon' => 'leaf', 'label' => 'Community Outreach', 'bg' => 'bg-leaf-900'],
                ['icon' => 'shield', 'label' => 'Compliance Audits', 'bg' => 'bg-sand-700'],
            ] as $item)
                <div class="group relative aspect-square overflow-hidden rounded-2xl {{ $item['bg'] }} text-white shadow-sm transition duration-300 hover:-translate-y-1 hover:shadow-xl">
                    <div class="flex h-full w-full flex-col items-center justify-center gap-3 p-4 text-center transition-transform duration-300 group-hover:scale-110">
                        <x-icon :name="$item['icon']" class="h-10 w-10" />
                        <span class="text-sm font-semibold">{{ $item['label'] }}</span>
                    </div>
                </div>
            @endforeach
        </div>
        <p class="reveal mt-10 text-center text-sm text-stone-500">
            Photos from our facility are being updated regularly — contact us to arrange a warehouse visit.
        </p>
    </section>

</x-layout>
