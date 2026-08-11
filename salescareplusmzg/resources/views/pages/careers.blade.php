<x-layout title="Careers" description="Join the Sales Care Plus MZG team in Muzaffargarh — opportunities in sales, logistics, warehousing and pharmacy support.">

    <section class="bg-teal-pattern py-16 sm:py-20">
        <div class="mx-auto max-w-4xl px-4 text-center sm:px-6 lg:px-8">
            <span class="hero-fade-in inline-flex items-center gap-2 rounded-full bg-white/10 px-4 py-1.5 text-sm font-medium text-coral-300 ring-1 ring-white/10">
                <x-icon name="users" class="h-4 w-4" /> Careers
            </span>
            <h1 class="hero-fade-in hero-fade-in-delay-1 mt-6 text-4xl font-bold tracking-tight text-white sm:text-5xl">{{ \App\Models\Setting::get('careers_hero_heading', 'Build Your Career With Us') }}</h1>
            <p class="hero-fade-in hero-fade-in-delay-2 mt-6 text-lg leading-relaxed text-teal-200">
                {{ \App\Models\Setting::get('careers_hero_subheading') }}
            </p>
        </div>
    </section>

    <section class="mx-auto max-w-7xl px-4 pt-16 sm:px-6 lg:px-8">
        <div class="reveal-scale flex aspect-[21/9] items-center justify-center overflow-hidden rounded-3xl bg-teal-50">
            <x-illustration name="team" class="h-full max-w-xl" />
        </div>
    </section>

    <section class="mx-auto max-w-7xl px-4 py-20 sm:px-6 lg:px-8">
        @if ($jobs->isNotEmpty())
            <div class="reveal-stagger grid grid-cols-1 gap-6 md:grid-cols-2">
                @foreach ($jobs as $job)
                    <div class="hover-tilt-card rounded-2xl border border-slate-100 p-7 shadow-sm transition duration-300 hover:shadow-lg">
                        <h3 class="text-lg font-bold text-teal-900">{{ $job->title }}</h3>
                        @if ($job->subtitle)
                            <p class="mt-1 text-xs font-medium uppercase tracking-wide text-coral-600">{{ $job->subtitle }}</p>
                        @endif
                        <p class="mt-3 leading-relaxed text-slate-600">{{ $job->description }}</p>
                        <a href="{{ route('contact') }}" class="group mt-5 inline-flex items-center gap-1.5 text-sm font-semibold text-coral-600 hover:text-coral-700">
                            Apply now <x-icon name="arrow-right" class="h-3.5 w-3.5 transition-transform duration-300 group-hover:translate-x-1" />
                        </a>
                    </div>
                @endforeach
            </div>
        @else
            <p class="rounded-2xl border border-dashed border-slate-200 px-4 py-16 text-center text-slate-400">
                No open roles right now — check back soon, or send us your CV below.
            </p>
        @endif

        <div class="reveal mt-14 rounded-2xl bg-teal-50/60 p-8 text-center">
            <h2 class="text-xl font-bold text-teal-900">{{ \App\Models\Setting::get('careers_cta_heading', "Don't see a role that fits?") }}</h2>
            <p class="mt-2 text-slate-600">{{ \App\Models\Setting::get('careers_cta_body') }}</p>
            <a href="{{ route('contact') }}" class="mt-5 inline-flex items-center gap-2 rounded-full bg-teal-900 px-6 py-3 font-semibold text-white transition duration-300 hover:-translate-y-0.5 hover:bg-teal-800 hover:shadow-lg">
                Get in Touch
            </a>
        </div>
    </section>

</x-layout>
