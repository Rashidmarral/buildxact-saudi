{{-- Bank details (left) + totals (right), shared by the bilingual_classic layout. --}}
<table style="margin-top: 12px;">
    <tr>
        <td style="width: 55%; vertical-align: top; font-size: 9.5pt;">
            @if ($bankAccounts->isNotEmpty())
                @php $ba = $bankAccounts->first(); @endphp
                <div><strong>{{ __('Bank Name') }}:</strong> {{ $ba->bank_name ?: $ba->name }}</div>
                @if ($ba->account_holder_name)<div><strong>{{ __('Account Name') }}:</strong> {{ $ba->account_holder_name }}</div>@endif
                @if ($ba->account_number)<div><strong>{{ __('Account Number') }}:</strong> {{ $ba->account_number }}</div>@endif
                @if ($ba->iban)<div><strong>{{ __('IBAN') }}:</strong> {{ $ba->iban }}</div>@endif
            @endif
        </td>
        <td style="width: 45%; vertical-align: top;">
            <table>
                <tr><td style="padding: 2px 0; color: #64748b;">{{ __('Subtotal') }}</td><td class="text-end" style="padding: 2px 0; color: #64748b;">SAR {{ number_format($doc['subtotal'], 2) }}</td></tr>
                @if (($doc['discount_total'] ?? 0) > 0)
                    <tr><td style="padding: 2px 0; color: #64748b;">{{ __('Discount') }}</td><td class="text-end" style="padding: 2px 0; color: #64748b;">-SAR {{ number_format($doc['discount_total'], 2) }}</td></tr>
                @endif
                <tr><td style="padding: 2px 0; color: #64748b;">{{ __('Total VAT') }}</td><td class="text-end" style="padding: 2px 0; color: #64748b;">SAR {{ number_format($doc['vat_total'], 2) }}</td></tr>
                <tr><td style="padding: 6px 0 2px; font-weight: bold; font-size: 11pt; border-top: 1.5pt solid #1e293b;">{{ __('Total') }}</td><td class="text-end" style="padding: 6px 0 2px; font-weight: bold; font-size: 11pt; border-top: 1.5pt solid #1e293b;">SAR {{ number_format($doc['total'], 2) }}</td></tr>
                @foreach ($doc['extra_rows'] ?? [] as $row)
                    <tr><td style="padding: 2px 0; color: #64748b;">{{ $row['label'] }}</td><td class="text-end" style="padding: 2px 0; color: #64748b;">SAR {{ number_format($row['value'], 2) }}</td></tr>
                @endforeach
            </table>
        </td>
    </tr>
</table>
