<x-layout title="Quality & Certifications" description="Discover the regulatory registrations, licences and quality practices behind Sales Care Plus MZG's medicine distribution operations.">

    <section class="bg-teal-pattern py-16 sm:py-20">
        <div class="mx-auto max-w-4xl px-4 text-center sm:px-6 lg:px-8">
            <span class="hero-fade-in inline-flex items-center gap-2 rounded-full bg-white/10 px-4 py-1.5 text-sm font-medium text-coral-300 ring-1 ring-white/10">
                <x-icon name="badge-check" class="h-4 w-4" /> Quality &amp; Compliance
            </span>
            <h1 class="hero-fade-in hero-fade-in-delay-1 mt-6 text-4xl font-bold tracking-tight text-white sm:text-5xl">{{ \App\Models\Setting::get('quality_hero_heading', 'Quality You Can Verify') }}</h1>
            <p class="hero-fade-in hero-fade-in-delay-2 mt-6 text-lg leading-relaxed text-teal-200">
                {{ \App\Models\Setting::get('quality_hero_subheading') }}
            </p>
        </div>
    </section>

    <section class="mx-auto max-w-7xl px-4 pt-16 sm:px-6 lg:px-8">
        <div class="grid gap-10 lg:grid-cols-2 lg:items-center">
            <div class="reveal">
                <span class="text-sm font-semibold uppercase tracking-wide text-coral-600">{{ \App\Models\Setting::get('quality_intro_tagline') }}</span>
                <h2 class="mt-3 text-3xl font-bold text-teal-900">{{ \App\Models\Setting::get('quality_intro_heading') }}</h2>
                <p class="mt-5 leading-relaxed text-slate-600">
                    {{ \App\Models\Setting::get('quality_intro_body') }}
                </p>
            </div>
            <div class="reveal-scale flex aspect-[4/3] items-center justify-center overflow-hidden rounded-3xl bg-teal-50 p-6">
                <x-illustration name="cold-chain" class="h-full max-w-sm" />
            </div>
        </div>
    </section>

    @if ($certifications->isNotEmpty())
        <section class="mx-auto max-w-7xl px-4 py-20 sm:px-6 lg:px-8">
            <div class="reveal-stagger grid grid-cols-1 gap-6 sm:grid-cols-2">
                @foreach ($certifications as $certification)
                    <div class="hover-tilt-card flex gap-5 rounded-2xl border border-slate-100 p-7 shadow-sm transition duration-300 hover:shadow-lg">
                        <span class="flex h-12 w-12 shrink-0 items-center justify-center rounded-xl bg-teal-100 text-teal-700">
                            <x-icon :name="$certification->icon" class="h-6 w-6" />
                        </span>
                        <div>
                            <h3 class="font-bold text-teal-900">{{ $certification->title }}</h3>
                            <p class="text-xs font-medium uppercase tracking-wide text-slate-400">{{ $certification->issuing_body }}</p>
                            <p class="mt-2 text-sm leading-relaxed text-slate-600">{{ $certification->description }}</p>
                        </div>
                    </div>
                @endforeach
            </div>
        </section>
    @endif

    @if ($standards->isNotEmpty())
        <section class="bg-teal-950 py-20 text-white">
            <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                <div class="reveal mx-auto max-w-2xl text-center">
                    <span class="text-sm font-semibold uppercase tracking-wide text-coral-400">Our Standards</span>
                    <h2 class="mt-3 text-3xl font-bold sm:text-4xl">How We Protect Product Quality</h2>
                </div>
                <div class="reveal-stagger mt-14 grid grid-cols-1 gap-6 sm:grid-cols-2 lg:grid-cols-4">
                    @foreach ($standards as $standard)
                        <div class="reveal-flip rounded-2xl border border-teal-800 bg-teal-900/60 p-6 transition duration-300 hover:-translate-y-1 hover:border-coral-500">
                            <span class="flex h-11 w-11 items-center justify-center rounded-full bg-teal-700 text-white">
                                <x-icon :name="$standard->icon ?: 'shield'" class="h-5 w-5" />
                            </span>
                            <h3 class="mt-4 font-semibold">{{ $standard->title }}</h3>
                            <p class="mt-2 text-sm leading-relaxed text-teal-200">{{ $standard->description }}</p>
                        </div>
                    @endforeach
                </div>
            </div>
        </section>
    @endif

</x-layout>
