{{--
    mPDF equivalent of documents/print/signature.blade.php — included by
    each layout branch in pdf.blade.php right before that layout's
    closing footer-note div, so the signature sits inside the document
    body, above the bottom-of-page strip, not below it. Relies on
    $template, $primary and $secondary already being in scope (this is an
    @include, not a component, so the parent's variables carry over).
--}}
@if ($template && $template->show_signature)
    <table style="margin-top: {{ $signatureGap }}px;">
        <tr>
            <td style="width: 60%;"></td>
            <td style="width: 40%; text-align: center;">
                <div style="border-bottom: 0.75pt solid #94a3b8; height: {{ $template->isCompact() ? 26 : 40 }}px;"></div>
                <div class="muted" style="margin-top: 4px;">{{ $primary($template->signature_label_en ?: __('Authorized Signature'), $template->signature_label_ar) }}</div>
                @if ($secondary($template->signature_label_ar))<div class="muted ar">{{ $template->signature_label_ar }}</div>@endif
            </td>
        </tr>
    </table>
@endif
