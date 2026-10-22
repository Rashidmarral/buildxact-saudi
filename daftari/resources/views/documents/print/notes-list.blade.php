{{--
    Renders free-text notes/terms as a bulleted list with a small
    accent-colored dot per line, instead of a single wall-of-text
    paragraph — matching how modern invoicing tools (Zoho, QuickBooks)
    present these fields. Splits on line breaks; blank separator lines
    are dropped rather than rendered as empty bullets. A single-line value
    still renders correctly, as one bulleted point.

    Params: $text (string), $accent (hex color, optional), $class (text
    color classes, optional), $dir ('rtl' to render an Arabic secondary
    block right-aligned, optional — defaults to the ambient direction).
--}}
@php
    $__notesLines = collect(preg_split('/\r\n|\r|\n/', trim((string) $text)))
        ->map(fn ($line) => trim($line))
        ->filter()
        ->values();
@endphp
@if ($__notesLines->isNotEmpty())
    <ul @if (($dir ?? null) === 'rtl') dir="rtl" @endif class="mt-1.5 space-y-1.5 {{ $class ?? 'text-slate-600' }}">
        @foreach ($__notesLines as $__notesLine)
            <li class="flex items-start gap-2">
                <span class="mt-[0.45rem] h-1.5 w-1.5 flex-shrink-0 rounded-full" style="background-color: {{ $accent ?? '#0f766e' }}"></span>
                <span>{{ $__notesLine }}</span>
            </li>
        @endforeach
    </ul>
@endif
