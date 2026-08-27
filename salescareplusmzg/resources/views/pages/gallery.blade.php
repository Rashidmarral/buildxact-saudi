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
        @if ($images->isEmpty())
            <p class="rounded-2xl border border-dashed border-slate-200 px-4 py-16 text-center text-slate-400">
                No gallery photos yet — add some from the admin panel.
            </p>
        @else
            <div class="reveal-stagger grid grid-cols-1 gap-6 sm:grid-cols-2 lg:grid-cols-3">
                @foreach ($images as $item)
                    <div class="group overflow-hidden rounded-2xl border border-teal-100 bg-white shadow-sm transition duration-300 hover:-translate-y-1 hover:shadow-xl">
                        <div class="flex aspect-[4/3] items-center justify-center overflow-hidden bg-teal-50 {{ $item->image_path ? '' : 'p-6' }}">
                            @if ($item->image_path)
                                <img src="{{ asset('storage/'.$item->image_path) }}" alt="{{ $item->title }}" loading="lazy" class="h-full w-full object-cover transition-transform duration-300 group-hover:scale-105">
                            @elseif ($item->illustration)
                                <x-illustration :name="$item->illustration" class="h-full w-full transition-transform duration-300 group-hover:scale-105" />
                            @else
                                <x-icon name="building" class="h-12 w-12 text-teal-300" />
                            @endif
                        </div>
                        <div class="p-5">
                            <h3 class="font-semibold text-teal-900">{{ $item->title }}</h3>
                            @if ($item->caption)
                                <p class="mt-1.5 text-sm leading-relaxed text-slate-500">{{ $item->caption }}</p>
                            @endif
                        </div>
                    </div>
                @endforeach
            </div>
        @endif
    </section>

</x-layout>
