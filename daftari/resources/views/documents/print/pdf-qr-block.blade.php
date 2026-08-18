{{-- Right-aligned QR + ZATCA-cleared badge block, shared by bilingual_classic and custom_letterhead. --}}
@if (!empty($doc['qr_code']))
    <table style="margin-top: 10px;">
        <tr>
            <td style="width: 70%;"></td>
            <td style="width: 30%; text-align: right;">
                @if (!empty($doc['zatca_status']))
                    <div class="zatca-badge">{{ $doc['zatca_status'] === 'cleared' ? __('ZATCA Cleared') : __('ZATCA Reported') }}</div><br>
                @endif
                <img src="data:image/png;base64,{{ $doc['qr_code'] }}" class="qr-img" alt="">
                <div class="muted">{{ __('Scan to verify invoice details') }}</div>
            </td>
        </tr>
    </table>
@endif
