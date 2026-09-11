@if ($section->page === 'home')
<section class="relative overflow-hidden">
    <div class="bg-grid pointer-events-none absolute inset-0 [mask-image:radial-gradient(ellipse_60%_60%_at_50%_0%,black,transparent)]"></div>
    <div class="orb-3d pointer-events-none -top-24 start-1/2 h-96 w-96 -translate-x-1/2 bg-brand-200/40"></div>
    <div class="orb-3d pointer-events-none top-1/3 -end-20 h-64 w-64 bg-emerald-200/30 [animation-delay:-4s] [animation-duration:16s]"></div>
    <div class="orb-3d pointer-events-none bottom-0 start-0 h-56 w-56 bg-brand-300/20 [animation-delay:-8s] [animation-duration:14s]"></div>

    <div class="relative mx-auto grid max-w-7xl items-center gap-12 px-6 pb-20 pt-16 lg:grid-cols-2 lg:pt-24">
        <div class="animate-fade-up">
            @if ($section->badge())
                <span class="inline-flex items-center gap-1.5 rounded-full bg-brand-50 px-3 py-1 text-xs font-semibold text-brand-700 ring-1 ring-inset ring-brand-100">
                    @include('partials.icon', ['name' => 'sparkle', 'class' => 'h-3.5 w-3.5'])
                    {{ $section->badge() }}
                </span>
            @endif
            @if ($section->title())
                <h1 class="mt-5 text-4xl font-extrabold tracking-tight text-slate-900 md:text-5xl">{{ $section->title() }}</h1>
            @endif
            @if ($section->subtitle())
                <p class="mt-5 text-lg text-slate-600">{{ $section->subtitle() }}</p>
            @endif
            <div class="mt-8 flex flex-wrap gap-4">
                <a href="{{ $section->link_url ?: route('register') }}" class="btn-shine rounded-lg bg-brand-600 px-6 py-3 font-semibold text-white shadow-card transition-all hover:-translate-y-0.5 hover:bg-brand-700 hover:shadow-card-hover">{{ $section->linkText() ?: __('Start your free trial') }}</a>
                <a href="{{ route('pricing') }}" class="rounded-lg border border-slate-200 bg-white px-6 py-3 font-semibold text-slate-700 transition-all hover:-translate-y-0.5 hover:border-brand-300 hover:shadow-soft">{{ __('See pricing') }}</a>
            </div>
            <p class="mt-4 text-sm text-slate-400">{{ __('No credit card required · :days-day free trial', ['days' => config('daftari.trial_days')]) }}</p>
        </div>

        <div class="perspective relative animate-fade-up [animation-delay:150ms]">
            @if ($section->image_path)
                <img x-data x-tilt.lg src="{{ \Illuminate\Support\Facades\Storage::url($section->image_path) }}" alt="" class="animate-float rounded-2xl border border-slate-100 shadow-card-hover">
            @else
                <div x-data x-tilt.lg class="animate-float rounded-2xl border border-slate-100 bg-white p-6 shadow-card-hover">
                    <div class="tilt-pop rounded-xl bg-gradient-to-br from-slate-900 to-slate-800 p-5 text-white">
                        <div class="flex items-center justify-between text-sm text-slate-300">
                            <span>{{ __('Tax Invoice') }} · INV-00042</span>
                            <span class="rounded-full bg-brand-500/90 px-2 py-0.5 text-xs">{{ __('Paid') }}</span>
                        </div>
                        <div class="mt-4 text-2xl font-bold">SAR 11,500.00</div>
                        <div class="mt-1 text-xs text-slate-400">{{ __('VAT (15%) included') }}: SAR 1,500.00</div>
                        <div class="mt-4 grid h-24 w-24 grid-cols-4 grid-rows-4 gap-0.5 rounded bg-white p-2">
                            @for ($i = 0; $i < 16; $i++)
                                <div class="{{ rand(0, 1) ? 'bg-slate-900' : 'bg-white' }}"></div>
                            @endfor
                        </div>
                    </div>
                    <div class="mt-4 grid grid-cols-3 gap-3 text-center text-xs text-slate-500">
                        <div class="rounded-lg bg-slate-50 py-3"><div class="text-lg font-bold text-slate-800">128</div>{{ __('Invoices') }}</div>
                        <div class="rounded-lg bg-slate-50 py-3"><div class="text-lg font-bold text-slate-800">SAR 342K</div>{{ __('Revenue') }}</div>
                        <div class="rounded-lg bg-slate-50 py-3"><div class="text-lg font-bold text-slate-800">SAR 51K</div>{{ __('VAT collected') }}</div>
                    </div>
                </div>
            @endif
            <div class="absolute -bottom-4 -start-4 -z-10 h-full w-full rounded-2xl bg-gradient-to-br from-brand-100/70 to-emerald-100/40 sm:-bottom-6 sm:-start-6"></div>
            <div class="absolute -top-6 -end-6 -z-10 h-20 w-20 animate-spin-slow rounded-2xl border-4 border-dashed border-brand-200/60"></div>
        </div>
    </div>
</section>
@else
<section x-data x-reveal class="relative overflow-hidden">
    <div class="orb-3d pointer-events-none -top-20 start-1/2 h-80 w-80 -translate-x-1/2 bg-brand-100/50"></div>
    <div class="orb-3d pointer-events-none bottom-0 -end-16 h-56 w-56 bg-emerald-100/30 [animation-delay:-6s] [animation-duration:15s]"></div>

    @if ($section->image_path)
        @php($imageFirst = $section->image_position === 'left')
        <div class="relative mx-auto grid max-w-6xl items-center gap-12 px-6 py-16 lg:grid-cols-2">
            <div class="order-1 {{ $imageFirst ? 'lg:order-2' : 'lg:order-1' }} {{ in_array($section->page, ['contact']) ? 'text-center lg:text-start' : '' }}">
                @if ($section->badge())
                    <span class="inline-flex items-center gap-1.5 rounded-full bg-brand-50 px-3 py-1 text-xs font-semibold text-brand-700 ring-1 ring-inset ring-brand-100">{{ $section->badge() }}</span>
                @endif
                @if ($section->title())
                    <h1 class="mt-4 text-4xl font-extrabold tracking-tight text-slate-900">{{ $section->title() }}</h1>
                @endif
                @if ($section->subtitle())
                    <p class="mt-4 text-lg text-slate-600">{{ $section->subtitle() }}</p>
                @endif
                @if ($section->linkText())
                    <a href="{{ $section->link_url ?: route('register') }}" class="btn-shine mt-6 inline-block rounded-lg bg-brand-600 px-6 py-3 font-semibold text-white shadow-card transition-all hover:-translate-y-0.5 hover:bg-brand-700 hover:shadow-card-hover">{{ $section->linkText() }}</a>
                @endif
            </div>
            <div class="perspective order-2 relative {{ $imageFirst ? 'lg:order-1' : 'lg:order-2' }}">
                <img x-data x-tilt src="{{ \Illuminate\Support\Facades\Storage::url($section->image_path) }}" alt="" class="w-full rounded-2xl border border-slate-100 shadow-card-hover">
                <div class="absolute -bottom-4 -z-10 h-full w-full rounded-2xl bg-brand-100/50 {{ $imageFirst ? '-start-4' : '-end-4' }}"></div>
            </div>
        </div>
    @else
        <div class="relative mx-auto max-w-4xl px-6 py-16 {{ in_array($section->page, ['contact']) ? 'text-center' : '' }}">
            @if ($section->badge())
                <span class="inline-flex items-center gap-1.5 rounded-full bg-brand-50 px-3 py-1 text-xs font-semibold text-brand-700 ring-1 ring-inset ring-brand-100">{{ $section->badge() }}</span>
            @endif
            @if ($section->title())
                <h1 class="mt-4 text-4xl font-extrabold text-slate-900">{{ $section->title() }}</h1>
            @endif
            @if ($section->subtitle())
                <p class="mt-4 text-lg text-slate-600">{{ $section->subtitle() }}</p>
            @endif
        </div>
    @endif
</section>
@endif
