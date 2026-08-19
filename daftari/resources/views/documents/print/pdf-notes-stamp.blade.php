{{-- Notes + template notes, then the QR and company stamp together as one
     bottom-right unit — scanning and stamping both happen at the bottom of
     a real paper invoice, not mixed in above the line items. --}}
@if (!empty($doc['notes']))
    <div class="notes-block">
        <strong>{{ __('Notes') }}</strong>
        <div class="muted" style="margin-top: 2px; white-space: pre-line;">{{ $doc['notes'] }}</div>
    </div>
@endif

@if ($template && $template->notesFor(app()->getLocale()))
    <div class="muted" style="margin-top: 6px; font-size: 8.5pt; white-space: pre-line;">{{ $template->notesFor(app()->getLocale()) }}</div>
@endif

@if (!empty($doc['qr_code']) || $stampData)
    <table style="margin-top: 20px;">
        <tr>
            <td style="width: 50%;"></td>
            @if (!empty($doc['qr_code']))
                <td style="width: 25%; text-align: center;">
                    @if (!empty($doc['zatca_status']))
                        <div class="zatca-badge">{{ $doc['zatca_status'] === 'cleared' ? __('ZATCA Cleared') : __('ZATCA Reported') }}</div><br>
                    @endif
                    <img src="data:image/png;base64,{{ $doc['qr_code'] }}" class="qr-img" alt="">
                    <div class="muted">{{ __('Scan to verify invoice details') }}</div>
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
