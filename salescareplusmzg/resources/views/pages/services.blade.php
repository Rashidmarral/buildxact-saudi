<x-layout title="Our Services" description="From warehousing to last-mile delivery, discover the distribution services Sales Care Plus MZG provides to pharmacies, clinics and hospitals in Muzaffargarh.">

    <section class="bg-teal-pattern py-16 sm:py-20">
        <div class="mx-auto max-w-4xl px-4 text-center sm:px-6 lg:px-8">
            <span class="hero-fade-in inline-flex items-center gap-2 rounded-full bg-white/10 px-4 py-1.5 text-sm font-medium text-coral-300 ring-1 ring-white/10">
                <x-icon name="truck" class="h-4 w-4" /> What We Do
            </span>
            <h1 class="hero-fade-in hero-fade-in-delay-1 mt-6 text-4xl font-bold tracking-tight text-white sm:text-5xl">{{ \App\Models\Setting::get('services_hero_heading', 'End-to-End Distribution Services') }}</h1>
            <p class="hero-fade-in hero-fade-in-delay-2 mt-6 text-lg leading-relaxed text-teal-200">
                {{ \App\Models\Setting::get('services_hero_subheading') }}
            </p>
        </div>
    </section>

    <section class="mx-auto max-w-7xl px-4 pt-16 sm:px-6 lg:px-8">
        <div class="grid gap-10 lg:grid-cols-2 lg:items-center">
            <div class="reveal-scale flex aspect-[16/10] items-center justify-center overflow-hidden rounded-3xl bg-teal-50 p-8">
                <x-illustration name="delivery" class="w-full max-w-md" />
            </div>
            <div class="reveal">
                <span class="text-sm font-semibold uppercase tracking-wide text-coral-600">{{ \App\Models\Setting::get('services_intro_tagline') }}</span>
                <h2 class="mt-3 text-3xl font-bold text-teal-900">{{ \App\Models\Setting::get('services_intro_heading') }}</h2>
                <p class="mt-5 leading-relaxed text-slate-600">
                    {{ \App\Models\Setting::get('services_intro_body') }}
                </p>
            </div>
        </div>
    </section>

    @if ($services->isNotEmpty())
        <section class="mx-auto max-w-7xl px-4 py-20 sm:px-6 lg:px-8">
            <div class="reveal-stagger grid grid-cols-1 gap-6 md:grid-cols-2 lg:grid-cols-3">
                @foreach ($services as $service)
                    <div class="hover-tilt-card rounded-2xl border border-slate-100 p-7 shadow-sm transition duration-300 hover:shadow-lg">
                        <span class="flex h-12 w-12 items-center justify-center rounded-xl bg-teal-900 text-coral-400">
                            <x-icon :name="$service->icon ?: 'truck'" class="h-6 w-6" />
                        </span>
                        <h3 class="mt-5 text-lg font-bold text-teal-900">{{ $service->title }}</h3>
                        <p class="mt-3 leading-relaxed text-slate-600">{{ $service->description }}</p>
                    </div>
                @endforeach
            </div>
        </section>
    @endif

    @if ($steps->isNotEmpty())
        <section class="bg-slate-50 py-20">
            <div class="mx-auto max-w-5xl px-4 sm:px-6 lg:px-8">
                <div class="reveal mx-auto max-w-2xl text-center">
                    <span class="text-sm font-semibold uppercase tracking-wide text-coral-600">How It Works</span>
                    <h2 class="mt-3 text-3xl font-bold text-teal-900 sm:text-4xl">Getting Supplied, Simplified</h2>
                </div>
                <ol class="reveal-stagger mt-14 grid grid-cols-1 gap-8 sm:grid-cols-2 lg:grid-cols-4">
                    @foreach ($steps as $step)
                        <li class="reveal-tilt relative rounded-2xl bg-white p-6 shadow-sm transition duration-300 hover:-translate-y-1 hover:shadow-lg">
                            <span class="text-3xl font-bold text-teal-200">{{ $step->icon }}</span>
                            <h3 class="mt-3 font-semibold text-slate-800">{{ $step->title }}</h3>
                            <p class="mt-2 text-sm leading-relaxed text-slate-500">{{ $step->description }}</p>
                        </li>
                    @endforeach
                </ol>
            </div>
        </section>
    @endif

    <section class="bg-coral-500">
        <div class="reveal mx-auto max-w-7xl px-4 py-16 text-center sm:px-6 lg:px-8">
            <h2 class="text-3xl font-bold text-white sm:text-4xl">Let's Set Up Your Supply Account</h2>
            <p class="mx-auto mt-4 max-w-xl text-white/90">Reach out today and our team will guide you through onboarding in minutes.</p>
            <a href="{{ route('contact') }}" class="mt-8 inline-flex items-center gap-2 rounded-full bg-white px-6 py-3.5 font-semibold text-coral-700 shadow-sm transition duration-300 hover:-translate-y-0.5 hover:bg-coral-50 hover:shadow-lg">
                Contact Us <x-icon name="arrow-right" class="h-4 w-4" />
            </a>
        </div>
    </section>

</x-layout>
