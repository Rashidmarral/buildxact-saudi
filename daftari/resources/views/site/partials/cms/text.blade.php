@if ($section->title() || $section->body())
<section x-data x-reveal class="mx-auto max-w-6xl px-6 py-16">
    @if ($section->image_path)
        @php($imageFirst = $section->image_position === 'left')
        <div class="grid items-center gap-12 lg:grid-cols-2">
            <div class="order-1 {{ $imageFirst ? 'lg:order-2' : 'lg:order-1' }}">
                @if ($section->title())
                    <h2 class="text-3xl font-extrabold text-slate-900">{{ $section->title() }}</h2>
                @endif
                @if ($section->bodyHtml())
                    <div class="prose-cms mt-5 text-lg text-slate-600">{!! $section->bodyHtml() !!}</div>
                @endif
            </div>
            <div class="order-2 relative {{ $imageFirst ? 'lg:order-1' : 'lg:order-2' }}">
                <img src="{{ \Illuminate\Support\Facades\Storage::url($section->image_path) }}" alt="" class="w-full rounded-2xl border border-slate-100 shadow-card-hover">
                <div class="absolute -bottom-4 -z-10 h-full w-full rounded-2xl bg-brand-100/50 {{ $imageFirst ? '-start-4' : '-end-4' }}"></div>
            </div>
        </div>
    @else
        <div class="mx-auto max-w-4xl">
            @if ($section->title())
                <h2 class="text-3xl font-extrabold text-slate-900">{{ $section->title() }}</h2>
            @endif
            @if ($section->bodyHtml())
                <div class="prose-cms mt-5 text-lg text-slate-600">{!! $section->bodyHtml() !!}</div>
            @endif
        </div>
    @endif
</section>
@endif
