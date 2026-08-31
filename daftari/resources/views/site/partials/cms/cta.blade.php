@if ($section->title() || $section->subtitle())
<section class="mx-auto max-w-7xl px-6 py-20">
    <div class="rounded-2xl bg-brand-600 px-8 py-14 text-center text-white">
        @if ($section->title())
            <h2 class="text-2xl md:text-3xl font-bold">{{ $section->title() }}</h2>
        @endif
        @if ($section->subtitle())
            <p class="mt-3 text-brand-50">{{ $section->subtitle() }}</p>
        @endif
        <a href="{{ $section->link_url ?: route('register') }}" class="mt-6 inline-block rounded-lg bg-white px-6 py-3 font-semibold text-brand-700 hover:bg-brand-50">{{ $section->linkText() ?: __('Start your free trial') }}</a>
    </div>
</section>
@endif
