<x-layout title="Page Not Found" description="The page you're looking for doesn't exist or may have moved.">

    <section class="bg-teal-pattern py-24 sm:py-32">
        <div class="mx-auto max-w-2xl px-4 text-center sm:px-6 lg:px-8">
            <span class="hero-fade-in inline-flex items-center gap-2 rounded-full bg-white/10 px-4 py-1.5 text-sm font-medium text-coral-300 ring-1 ring-white/10">
                <x-icon name="search" class="h-4 w-4" /> 404 Error
            </span>
            <h1 class="hero-fade-in hero-fade-in-delay-1 mt-6 text-5xl font-bold tracking-tight text-white sm:text-6xl">Page Not Found</h1>
            <p class="hero-fade-in hero-fade-in-delay-2 mt-6 text-lg leading-relaxed text-teal-200">
                Sorry, we couldn't find the page you were looking for. It may have been moved, renamed, or no longer exists.
            </p>
            <div class="hero-fade-in hero-fade-in-delay-3 mt-10 flex flex-wrap items-center justify-center gap-4">
                <a href="{{ route('home') }}" class="inline-flex items-center gap-2 rounded-full bg-coral-500 px-6 py-3.5 font-semibold text-white shadow-sm transition duration-300 hover:-translate-y-0.5 hover:bg-coral-400 hover:shadow-lg">
                    Back to Home <x-icon name="arrow-right" class="h-4 w-4" />
                </a>
                <a href="{{ route('contact') }}" class="inline-flex items-center gap-2 rounded-full border border-white/25 px-6 py-3.5 font-semibold text-white transition duration-300 hover:-translate-y-0.5 hover:bg-white/10">
                    Contact Us
                </a>
            </div>
        </div>
    </section>

</x-layout>
