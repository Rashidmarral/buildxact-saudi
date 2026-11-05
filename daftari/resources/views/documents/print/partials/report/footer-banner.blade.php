{{--
    Repeating footer-image banner for any report PDF — registered as
    mPDF's own <htmlpagefooter> (not a plain inline <img>) so a
    company's uploaded footer, when $template->footer_path is set, is
    pinned to the bottom of every page a report spans, independent of
    which layout the template otherwise uses. Must be @include'd near
    the top of <body> (alongside this folder's header partial), since
    mPDF only applies sethtmlpagefooter from that point in the HTML
    stream onward. The plain-text footer.blade.php note still renders
    separately at the end of the document — this is purely the image
    banner, exactly like account-statement.blade.php/letter-pdf.blade.php
    already register their own.
--}}
@php
    $template = $template ?? null;
    $reportFooterData = $embed($template->footer_path ?? null);
@endphp
@if ($reportFooterData)
    <htmlpagefooter name="report-footer">
        <img src="{{ $reportFooterData }}" style="width: 100%;" alt="">
    </htmlpagefooter>
    <sethtmlpagefooter name="report-footer" page="ALL" />
@endif
