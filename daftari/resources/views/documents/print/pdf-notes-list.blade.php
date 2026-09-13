{{--
    mPDF equivalent of documents/print/notes-list.blade.php: renders
    free-text notes/terms as one row per line with a small accent-colored
    dot, instead of a single whitespace:pre-line paragraph. Built with a
    <table> rather than flex (mPDF's HTML renderer doesn't support flex).

    Params: $text (string), $accent (hex color, optional), $textStyle
    (inline CSS for the text cell, optional).
--}}
@php
    $__pdfNotesLines = collect(preg_split('/\r\n|\r|\n/', trim((string) $text)))
        ->map(fn ($line) => trim($line))
        ->filter()
        ->values();
@endphp
@if ($__pdfNotesLines->isNotEmpty())
    <table style="margin-top: 4px;">
        @foreach ($__pdfNotesLines as $__pdfNotesLine)
            <tr>
                <td style="width: 14px; vertical-align: middle; padding: 2px 0;">
                    <div style="width: 5px; height: 5px; border-radius: 3px; background-color: {{ $accent ?? '#0f766e' }};"></div>
                </td>
                <td style="padding: 2px 0; {{ $textStyle ?? 'color: #475569; font-size: 9pt;' }}">{{ $__pdfNotesLine }}</td>
            </tr>
        @endforeach
    </table>
@endif
