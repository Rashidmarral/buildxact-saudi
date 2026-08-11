<section class="bg-teal-900 bg-teal-pattern {{ $section->animationClass() }}">
    <div class="mx-auto max-w-4xl px-4 py-16 text-center sm:px-6 lg:px-8">
        @if ($section->heading)
            <h2 class="text-2xl font-bold text-white sm:text-3xl">{{ $section->heading }}</h2>
        @endif
        @if ($section->body)
            <p class="mt-4 text-teal-200">{{ $section->body }}</p>
        @endif
        @if ($section->button_text && $section->button_url)
            <a href="{{ $section->button_url }}" class="mt-7 inline-flex items-center gap-2 rounded-full bg-coral-500 px-7 py-3.5 text-sm font-semibold text-white shadow-sm transition duration-300 hover:-translate-y-0.5 hover:bg-coral-400 hover:shadow-lg">
                {{ $section->button_text }} <x-icon name="arrow-right" class="h-4 w-4" />
            </a>
        @endif
    </div>
</section>
