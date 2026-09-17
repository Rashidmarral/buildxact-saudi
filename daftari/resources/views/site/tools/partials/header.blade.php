<section class="mx-auto max-w-4xl px-6 pt-12 pb-8">
    <nav class="text-xs text-slate-400 mb-4">
        <a href="{{ route('home') }}" class="hover:text-brand-700">{{ __('Home') }}</a>
        <span class="mx-1">›</span>
        <a href="{{ route('tools.index') }}" class="hover:text-brand-700">{{ __('Free Tools & Templates') }}</a>
        <span class="mx-1">›</span>
        <span>{{ $title }}</span>
    </nav>
    <h1 class="text-3xl md:text-4xl font-extrabold text-slate-900">{{ $title }}</h1>
    <p class="mt-3 text-slate-600">{{ $description }}</p>
</section>
