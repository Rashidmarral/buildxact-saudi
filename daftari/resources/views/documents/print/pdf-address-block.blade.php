{{--
    mPDF equivalent of address-block.blade.php — table/inline-style only,
    same $title/$headerStyle/$borderStyle/$rows contract.
--}}
<table style="border: 0.75pt solid #cbd5e1; {{ $borderStyle ?? '' }}">
    <tr><td style="{{ $headerStyle ?? 'background-color: #334155; color: #ffffff;' }} font-weight: bold; padding: 2px 8px; font-size: 8.5pt;">{{ $title }}</td></tr>
    <tr><td style="padding: 0;">
        <table>
            @foreach ($rows as $row)
                <tr style="border-bottom: 0.5pt solid #e2e8f0;">
                    <td style="width: 40%; padding: 1px 8px; font-weight: bold; color: #475569; vertical-align: top; font-size: 7.5pt; line-height: 1.2;">{{ $row['label'] }}</td>
                    <td style="padding: 1px 8px; color: #1e293b; font-size: 7.5pt; line-height: 1.2;">{{ $row['value'] ?: '—' }}</td>
                </tr>
            @endforeach
        </table>
    </td></tr>
</table>
