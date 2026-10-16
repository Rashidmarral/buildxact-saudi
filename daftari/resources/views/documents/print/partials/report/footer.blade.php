{{--
    Thin footer note: company contact + generation timestamp.

    Wrapped in a <table> rather than a bare trailing <div> — mPDF
    overestimates the page-break space a bare <div> right after a
    </table> needs at the end of the document flow, forcing a spurious
    blank extra page (see PdfPageDensityTest for the invoice-layout
    case this was first diagnosed from).
--}}
<table style="margin-top: 8px;">
    <tr><td class="footer-note">
        @if ($company->phone){{ __('Phone') }}: {{ $company->phone }}&nbsp;&nbsp;@endif
        @if ($company->vat_number){{ __('VAT No.') }}: {{ $company->vat_number }}&nbsp;&nbsp;@endif
        {{ __('Generated') }}: {{ \App\Support\PlatformFormat::date(now()) }}
    </td></tr>
</table>
