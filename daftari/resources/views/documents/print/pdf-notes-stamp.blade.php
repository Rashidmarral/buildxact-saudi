{{-- Notes + template notes, then the QR and company stamp together as one
     bottom-right unit — scanning and stamping both happen at the bottom of
     a real paper invoice, not mixed in above the line items. --}}
@if (!empty($doc['notes']))
    <div class="notes-block">
        <strong>{{ $lbl('Notes') }}</strong>
        @include('documents.print.pdf-notes-list', ['text' => $doc['notes'], 'accent' => $accent])
    </div>
@endif

@if ($template && ($template->notes_en || $template->notes_ar))
    @include('documents.print.pdf-template-notes', ['en' => $template->notes_en, 'ar' => $template->notes_ar, 'primary' => $primary, 'secondary' => $secondary, 'accent' => $accent, 'textStyle' => 'color: #64748b; font-size: 8.5pt;'])
@endif

@if ($template && ($template->terms_en || $template->terms_ar))
    <div class="notes-block">
        <strong>{{ $lbl('Terms & Conditions') }}</strong>
        @include('documents.print.pdf-template-notes', ['en' => $template->terms_en, 'ar' => $template->terms_ar, 'primary' => $primary, 'secondary' => $secondary, 'accent' => $accent])
    </div>
@endif

@if (!empty($doc['qr_code']) || $stampData)
    <table style="margin-top: 10px;">
        <tr>
            <td style="width: 50%;"></td>
            @if (!empty($doc['qr_code']))
                <td style="width: 25%; text-align: center;">
                    @if (!empty($doc['zatca_status']))
                        <div class="zatca-badge">{{ $doc['zatca_status'] === 'cleared' ? $lbl('ZATCA Cleared') : $lbl('ZATCA Reported') }}</div><br>
                    @endif
                    <img src="data:image/png;base64,{{ $doc['qr_code'] }}" class="qr-img" alt="">
                    <div class="muted">{{ $lbl('Scan to verify invoice details') }}</div>
                </td>
            @endif
            @if ($stampData)
                <td style="width: 25%; text-align: center; vertical-align: bottom;">
                    <img src="{{ $stampData }}" class="stamp-img" style="width: {{ $stampSize ?? 90 }}px; height: {{ $stampSize ?? 90 }}px;" alt="">
                </td>
            @endif
        </tr>
    </table>
@endif
