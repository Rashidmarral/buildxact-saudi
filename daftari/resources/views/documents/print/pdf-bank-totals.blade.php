{{-- Bank details (left) + totals (right), shared by the bilingual_classic layout. --}}
<table style="margin-top: 12px;">
    <tr>
        <td style="width: 55%; vertical-align: top; font-size: 9.5pt;">
            @if ($bankAccounts->isNotEmpty())
                @php $ba = $bankAccounts->first(); @endphp
                <div><strong>{{ $lbl('Bank Name') }}:</strong> {{ $ba->bank_name ?: $ba->name }}</div>
                @if ($ba->account_holder_name)<div><strong>{{ $lbl('Account Name') }}:</strong> {{ $ba->account_holder_name }}</div>@endif
                @if ($ba->account_number)<div><strong>{{ $lbl('Account Number') }}:</strong> {{ $ba->account_number }}</div>@endif
                @if ($ba->iban)<div><strong>{{ $lbl('IBAN') }}:</strong> {{ $ba->iban }}</div>@endif
            @endif
        </td>
        <td style="width: 45%; vertical-align: top;">
            <table>
                <tr><td style="padding: 2px 0; color: #64748b;">{{ $lbl('Subtotal') }}</td><td class="text-end" style="padding: 2px 0; color: #64748b;">{{ \App\Support\Money::format($doc['subtotal']) }}</td></tr>
                @if (($doc['discount_total'] ?? 0) > 0)
                    <tr><td style="padding: 2px 0; color: #64748b;">{{ $lbl('Discount') }}</td><td class="text-end" style="padding: 2px 0; color: #64748b;">-{{ \App\Support\Money::format($doc['discount_total']) }}</td></tr>
                @endif
                <tr><td style="padding: 2px 0; color: #64748b;">{{ $lbl('Total VAT') }}</td><td class="text-end" style="padding: 2px 0; color: #64748b;">{{ \App\Support\Money::format($doc['vat_total']) }}</td></tr>
                <tr><td style="padding: 6px 0 2px; font-weight: bold; font-size: 11pt; border-top: 1.5pt solid #1e293b;">{{ $lbl('Total') }}</td><td class="text-end" style="padding: 6px 0 2px; font-weight: bold; font-size: 11pt; border-top: 1.5pt solid #1e293b;">{{ \App\Support\Money::format($doc['total']) }}</td></tr>
                @foreach ($doc['extra_rows'] ?? [] as $row)
                    <tr><td style="padding: 2px 0; color: #64748b;">{{ $row['label'] }}</td><td class="text-end" style="padding: 2px 0; color: #64748b;">{{ \App\Support\Money::format($row['value']) }}</td></tr>
                @endforeach
            </table>
        </td>
    </tr>
</table>
