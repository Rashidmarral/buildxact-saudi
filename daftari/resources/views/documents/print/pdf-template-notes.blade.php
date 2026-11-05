{{--
    mPDF equivalent of documents/print/template-notes.blade.php — see
    that file for why this can't just call notesFor()/termsFor().

    Params: $en, $ar, $primary, $secondary, $accent, $textStyle
    (optional).
--}}
@php
    $__pdfTemplateNotesPrimary = $primary($en, $ar);
@endphp
@if ($__pdfTemplateNotesPrimary)
    @include('documents.print.pdf-notes-list', ['text' => $__pdfTemplateNotesPrimary, 'accent' => $accent, 'textStyle' => $textStyle ?? null])
    @if ($secondary($ar))
        @include('documents.print.pdf-notes-list', ['text' => $ar, 'accent' => $accent, 'textStyle' => 'color: #94a3b8; font-size: 8.5pt;', 'dir' => 'rtl'])
    @endif
@endif
