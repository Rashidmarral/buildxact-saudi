@if ($section->page === 'home')
<section class="daftari-hero relative overflow-hidden">
    <div class="relative mx-auto grid max-w-7xl items-center gap-10 px-6 py-14 lg:grid-cols-2 lg:gap-16 lg:py-24">
        <div class="min-w-0">
            @if ($section->badge())
                <span class="inline-flex items-center gap-1.5 rounded-full bg-brand-50 px-3 py-1 text-xs font-semibold text-brand-700 ring-1 ring-inset ring-brand-100">
                    @include('partials.icon', ['name' => 'sparkle', 'class' => 'h-3.5 w-3.5'])
                    {{ $section->badge() }}
                </span>
            @endif
            @if ($section->title())
                <h1 class="mt-5 text-4xl font-extrabold tracking-tight text-slate-950 sm:text-5xl lg:text-6xl">{{ $section->title() }}</h1>
            @endif
            @if ($section->subtitle())
                <p class="mt-5 max-w-xl text-lg leading-relaxed text-slate-600">{{ $section->subtitle() }}</p>
            @endif
            <div class="mt-8 flex flex-wrap gap-4">
                <a href="{{ $section->link_url ?: route('register') }}" class="btn-shine rounded-lg bg-brand-600 px-6 py-3 font-semibold text-white shadow-card transition-all hover:-translate-y-0.5 hover:bg-brand-700 hover:shadow-card-hover">{{ $section->linkText() ?: __('Start your free trial') }}</a>
                <a href="{{ route('pricing') }}" class="rounded-lg border border-slate-200 bg-white px-6 py-3 font-semibold text-slate-700 transition-all hover:-translate-y-0.5 hover:border-brand-300 hover:shadow-soft">{{ __('See pricing') }}</a>
            </div>
            <p class="mt-4 text-sm text-slate-600">{{ __('No credit card required · :days-day free trial', ['days' => config('daftari.trial_days')]) }}</p>
        </div>

        <div class="min-w-0">
            @if ($section->image_path)
                <img src="{{ \Illuminate\Support\Facades\Storage::url($section->image_path) }}" alt="" class="h-auto w-full rounded-3xl border border-slate-200 shadow-card-hover">
            @else
                <figure class="daftari-product-preview rounded-3xl border border-white/10 p-4 text-white shadow-card-hover sm:p-6">
                    <figcaption class="flex flex-wrap items-center justify-between gap-2 border-b border-white/10 pb-4 text-xs font-semibold">
                        <span>{{ __('Product preview') }}</span>
                        <span class="rounded-full bg-white/10 px-2.5 py-1 text-slate-200">{{ __('Illustrative data') }}</span>
                    </figcaption>
                    <div class="pt-5">
                        <p class="text-xl font-bold">{{ __('Business overview') }}</p>
                        <dl class="mt-4 grid grid-cols-1 gap-2 min-[380px]:grid-cols-3">
                            @foreach ([
                                ['label' => __('Invoices'), 'value' => '128'],
                                ['label' => __('Revenue'), 'value' => 'SAR 342k'],
                                ['label' => __('Collections'), 'value' => 'SAR 286k'],
                            ] as $metric)
                                <div class="min-w-0 rounded-xl bg-white/10 px-3 py-3">
                                    <dt class="text-xs text-slate-200">{{ $metric['label'] }}</dt>
                                    <dd class="mt-1 text-sm font-bold tabular-nums sm:text-base"><bdi>{{ $metric['value'] }}</bdi></dd>
                                </div>
                            @endforeach
                        </dl>
                        <div class="mt-4 rounded-xl bg-white/5 p-4">
                            <p class="text-xs font-semibold text-slate-200">{{ __('Monthly revenue') }}</p>
                            <svg viewBox="0 0 320 90" class="mt-3 h-24 w-full text-emerald-300" aria-hidden="true" preserveAspectRatio="none">
                                <path d="M0 20H320 M0 50H320 M0 80H320" fill="none" stroke="white" stroke-opacity=".14" />
                                <path d="M0 74 C24 75 38 55 62 58 S106 73 135 47 S170 57 198 31 S238 43 262 23 S297 27 320 7" fill="none" stroke="currentColor" stroke-width="3" vector-effect="non-scaling-stroke" />
                            </svg>
                        </div>
                    </div>
                </figure>
            @endif
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
