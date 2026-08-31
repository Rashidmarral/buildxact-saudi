@if ($section->title() || $section->body())
<section class="mx-auto max-w-4xl px-6 py-16">
    @if ($section->title())
        <h1 class="text-4xl font-extrabold text-slate-900">{{ $section->title() }}</h1>
    @endif
    @if ($section->body())
        <div class="mt-6 space-y-4 text-lg text-slate-600">
            @foreach (explode("\n\n", $section->body()) as $paragraph)
                @if (trim($paragraph) !== '')
                    <p>{{ $paragraph }}</p>
                @endif
            @endforeach
        </div>
    @endif
</section>
@endif
