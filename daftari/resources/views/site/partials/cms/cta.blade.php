@if ($section->title() || $section->subtitle())
<section class="mx-auto max-w-7xl px-6 py-20">
    <div x-data x-reveal class="relative overflow-hidden rounded-2xl bg-gradient-to-br from-brand-600 to-brand-800 px-8 py-14 text-center text-white shadow-card-hover">
        <div class="orb-3d pointer-events-none -end-16 -top-16 h-64 w-64 bg-white/10"></div>
        <div class="orb-3d pointer-events-none -bottom-16 -start-16 h-64 w-64 bg-white/10 [animation-delay:-5s] [animation-duration:16s]"></div>
        <div class="pointer-events-none absolute inset-0 animate-spin-slow opacity-20 [background:conic-gradient(from_0deg,transparent,rgb(255_255_255/0.25),transparent_60%)] [mask-image:radial-gradient(circle_at_center,black,transparent_70%)]"></div>
        @if ($section->title())
            <h2 class="relative text-2xl font-bold md:text-3xl">{{ $section->title() }}</h2>
        @endif
        @if ($section->subtitle())
            <p class="relative mt-3 text-brand-50">{{ $section->subtitle() }}</p>
        @endif
        <a href="{{ $section->link_url ?: route('register') }}" class="btn-shine relative mt-6 inline-block rounded-lg bg-white px-6 py-3 font-semibold text-brand-700 shadow-lg transition-transform hover:-translate-y-0.5 hover:bg-brand-50">{{ $section->linkText() ?: __('Start your free trial') }}</a>
    </div>
</section>
@endif
