<x-layout title="About Us" description="Building trust through quality pharmaceutical distribution — learn about Sales Care Plus MZG's story, mission and team in Muzaffargarh, Pakistan.">

    <section class="bg-teal-pattern py-16 sm:py-20">
        <div class="mx-auto max-w-4xl px-4 text-center sm:px-6 lg:px-8">
            <span class="hero-fade-in inline-flex items-center gap-2 rounded-full bg-white/10 px-4 py-1.5 text-sm font-medium text-coral-300 ring-1 ring-white/10">
                <x-icon name="building" class="h-4 w-4" /> About Us
            </span>
            <h1 class="hero-fade-in hero-fade-in-delay-1 mt-6 text-4xl font-bold tracking-tight text-white sm:text-5xl">{{ \App\Models\Setting::get('about_hero_heading', 'A Decade of Pharmaceutical Excellence') }}</h1>
            <p class="hero-fade-in hero-fade-in-delay-2 mt-6 text-lg leading-relaxed text-teal-200">
                {{ \App\Models\Setting::get('about_hero_subheading') }}
            </p>
        </div>
    </section>

    <section class="mx-auto max-w-7xl px-4 py-20 sm:px-6 lg:px-8">
        <div class="grid gap-12 lg:grid-cols-2 lg:items-start">
            <div class="reveal">
                <span class="text-sm font-semibold uppercase tracking-wide text-coral-600">{{ \App\Models\Setting::get('about_story_tagline') }}</span>
                <h2 class="mt-3 text-3xl font-bold text-teal-900">{{ \App\Models\Setting::get('about_story_heading') }}</h2>
                @foreach (explode("\n\n", (string) \App\Models\Setting::get('about_story_body')) as $paragraph)
                    <p class="mt-4 leading-relaxed text-slate-600 first:mt-5">{{ $paragraph }}</p>
                @endforeach
                @if ($highlights->isNotEmpty())
                    <ul class="mt-8 space-y-4">
                        @foreach ($highlights as $point)
                            <li class="flex items-start gap-3">
                                <span class="mt-0.5 flex h-6 w-6 shrink-0 items-center justify-center rounded-full bg-coral-100 text-coral-600">
                                    <x-icon name="check-circle" class="h-4 w-4" />
                                </span>
                                <div>
                                    <p class="font-semibold text-teal-900">{{ $point->title }}</p>
                                    <p class="text-sm text-slate-500">{{ $point->description }}</p>
                                </div>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </div>

            <div class="reveal-stagger grid grid-cols-2 gap-4 rounded-3xl bg-slate-50 p-6">
                <div class="rounded-2xl bg-white p-6 text-center shadow-sm transition-transform duration-300 hover:-translate-y-1">
                    <p class="text-3xl font-bold text-teal-900">{{ config('company.stats.years') }}+</p>
                    <p class="mt-1 text-sm text-slate-500">Years of Excellence</p>
                </div>
                <div class="rounded-2xl bg-white p-6 text-center shadow-sm transition-transform duration-300 hover:-translate-y-1">
                    <p class="text-3xl font-bold text-teal-900">{{ config('company.stats.professionals') }}+</p>
                    <p class="mt-1 text-sm text-slate-500">Professionals</p>
                </div>
                <div class="rounded-2xl bg-white p-6 text-center shadow-sm transition-transform duration-300 hover:-translate-y-1">
                    <p class="text-3xl font-bold text-teal-900">{{ $principalsCount }}</p>
                    <p class="mt-1 text-sm text-slate-500">Principal Companies</p>
                </div>
                <div class="rounded-2xl bg-white p-6 text-center shadow-sm transition-transform duration-300 hover:-translate-y-1">
                    <p class="text-3xl font-bold text-teal-900">{{ config('company.stats.monthly_reach') }}</p>
                    <p class="mt-1 text-sm text-slate-500">Pharmacies Reached</p>
                </div>
            </div>
        </div>
    </section>

    @if ($certifications->isNotEmpty())
        <section class="border-y border-slate-100 bg-slate-50 py-10">
            <div class="reveal-stagger mx-auto grid max-w-7xl grid-cols-2 gap-6 px-4 sm:px-6 md:grid-cols-4 lg:px-8">
                @foreach ($certifications as $certification)
                    <div class="flex items-center gap-3">
                        <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-white text-coral-600 shadow-sm">
                            <x-icon :name="$certification->icon" class="h-5 w-5" />
                        </span>
                        <span class="text-sm font-medium text-teal-900">{{ $certification->title }}</span>
                    </div>
                @endforeach
            </div>
            <div class="reveal mt-6 text-center">
                <a href="{{ route('quality') }}" class="inline-flex items-center gap-1.5 text-sm font-semibold text-coral-600 hover:text-coral-700">
                    View full Quality &amp; Certifications page <x-icon name="arrow-right" class="h-3.5 w-3.5" />
                </a>
            </div>
        </section>
    @endif

    @if ($values->isNotEmpty())
        <section class="mx-auto max-w-7xl px-4 py-20 sm:px-6 lg:px-8">
            <div class="reveal-stagger grid gap-6 md:grid-cols-3">
                @foreach ($values as $value)
                    <div class="hover-tilt-card rounded-2xl border border-slate-100 bg-white p-8 shadow-sm transition duration-300 hover:shadow-lg">
                        <span class="flex h-12 w-12 items-center justify-center rounded-xl bg-teal-900 text-coral-400">
                            <x-icon :name="$value->icon ?: 'badge-check'" class="h-6 w-6" />
                        </span>
                        <h3 class="mt-5 text-xl font-bold text-teal-900">{{ $value->title }}</h3>
                        <p class="mt-3 leading-relaxed text-slate-600">{{ $value->description }}</p>
                    </div>
                @endforeach
            </div>
        </section>
    @endif

    @if ($team->isNotEmpty())
        <section class="bg-teal-950 py-20 text-white">
            <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                <div class="reveal mx-auto max-w-2xl text-center">
                    <span class="text-sm font-semibold uppercase tracking-wide text-coral-400">Leadership</span>
                    <h2 class="mt-3 text-3xl font-bold sm:text-4xl">Meet Our Team</h2>
                </div>
                <div class="reveal-stagger mt-12 grid grid-cols-1 gap-6 sm:grid-cols-2 lg:grid-cols-4">
                    @foreach ($team as $member)
                        <div class="rounded-2xl border border-teal-800 bg-teal-900/60 p-6 text-center transition duration-300 hover:-translate-y-1 hover:border-coral-500">
                            <span class="mx-auto flex h-16 w-16 items-center justify-center rounded-full bg-coral-500 text-lg font-bold text-white transition-transform duration-300 hover:scale-110">
                                {{ $member->initials }}
                            </span>
                            <h3 class="mt-4 font-semibold">{{ $member->name }}</h3>
                            <p class="text-sm text-coral-400">{{ $member->designation }}</p>
                            <p class="mt-3 text-xs leading-relaxed text-teal-200">{{ $member->bio }}</p>
                        </div>
                    @endforeach
                </div>
                <div class="reveal mt-10 text-center">
                    <a href="{{ route('careers') }}" class="inline-flex items-center gap-2 font-semibold text-coral-400 hover:text-coral-300">
                        Join Our Team <x-icon name="arrow-right" class="h-4 w-4" />
                    </a>
                </div>
            </div>
        </section>
    @endif

</x-layout>
