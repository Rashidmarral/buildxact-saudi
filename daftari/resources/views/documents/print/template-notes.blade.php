{{--
    Bilingual-aware wrapper around notes-list for InvoiceTemplate's
    notes_en/notes_ar or terms_en/terms_ar pair.

    Bug: this used to call notesFor()/termsFor(app()->getLocale()) —
    app()->getLocale() is the CURRENT VIEWER's UI language (whoever is
    logged in and looking at/generating the document), not the
    document's own language_mode. A "bilingual" document showed only
    one language of template notes/terms, picked by whichever locale
    happened to be active, instead of both. This now follows the same
    $primary/$secondary split every other bilingual field on the
    document already uses: English primary in bilingual mode, Arabic
    secondary underneath — never keyed off the viewer's own locale.

    Params: $en, $ar (the two fields), $primary, $secondary (the
    closures already defined at the top of body.blade.php/pdf.blade.php),
    $accent, $class (primary text color classes, optional).
--}}
@php
    $__templateNotesPrimary = $primary($en, $ar);
@endphp
@if ($__templateNotesPrimary)
    @include('documents.print.notes-list', ['text' => $__templateNotesPrimary, 'accent' => $accent, 'class' => $class ?? 'text-slate-500'])
    @if ($secondary($ar))
        @include('documents.print.notes-list', ['text' => $ar, 'accent' => $accent, 'class' => 'mt-1.5 text-slate-400', 'dir' => 'rtl'])
    @endif
@endif
