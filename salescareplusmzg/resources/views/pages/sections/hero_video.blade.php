@php
    $videoSrc = $section->video_path ? asset('storage/'.$section->video_path) : $section->video_url;
@endphp
<section class="relative overflow-hidden bg-teal-950 text-white {{ $section->animationClass() }}">
    @if ($videoSrc)
        <video autoplay muted loop playsinline class="absolute inset-0 h-full w-full object-cover opacity-40">
            <source src="{{ $videoSrc }}" type="video/mp4">
        </video>
    @endif
    <div class="absolute inset-0 bg-gradient-to-t from-teal-950 via-teal-950/70 to-teal-950/40"></div>

    <div class="relative mx-auto max-w-4xl px-4 py-24 text-center sm:px-6 lg:px-8 lg:py-32">
        @if ($section->subheading)
            <p class="mb-3 text-sm font-semibold uppercase tracking-wide text-coral-300">{{ $section->subheading }}</p>
        @endif
        @if ($section->heading)
            <h1 class="text-3xl font-bold sm:text-4xl lg:text-5xl">{{ $section->heading }}</h1>
        @endif
        @if ($section->body)
            <p class="mt-5 text-lg text-teal-100">{{ $section->body }}</p>
        @endif
        @if ($section->button_text && $section->button_url)
            <a href="{{ $section->button_url }}" class="mt-8 inline-flex items-center gap-2 rounded-full bg-coral-500 px-7 py-3.5 text-sm font-semibold text-white shadow-sm transition duration-300 hover:-translate-y-0.5 hover:bg-coral-400 hover:shadow-lg">
                {{ $section->button_text }} <x-icon name="arrow-right" class="h-4 w-4" />
            </a>
        @endif
    </div>
</section>
