<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}" dir="{{ app()->getLocale() === 'ar' ? 'rtl' : 'ltr' }}">
<head>
<meta charset="utf-8">
<style>
    body { font-family: 'DejaVu Sans', sans-serif; font-size: 12px; color: #1e293b; }
    .header-table { width: 100%; border-collapse: collapse; }
    .header-table td { vertical-align: top; }
    h1 { font-size: 20px; margin: 0 0 4px; }
    h2 { font-size: 16px; margin: 0; }
    p { margin: 2px 0; }
    .muted { color: #64748b; }
    .end { text-align: {{ app()->getLocale() === 'ar' ? 'left' : 'right' }}; }
    .badge-cleared { display: inline-block; padding: 3px 10px; border: 1px solid #059669; color: #059669; border-radius: 4px; font-size: 11px; font-weight: bold; margin-top: 4px; }
    .label { text-transform: uppercase; color: #94a3b8; font-size: 10px; margin-bottom: 2px; }
    table.items { width: 100%; border-collapse: collapse; margin-top: 20px; }
    table.items th { border-bottom: 1px solid #cbd5e1; text-align: {{ app()->getLocale() === 'ar' ? 'right' : 'left' }}; padding: 6px 4px; color: #64748b; font-weight: normal; font-size: 11px; }
    table.items td { padding: 6px 4px; border-bottom: 1px solid #f1f5f9; font-size: 11px; }
    .totals-table { width: 260px; margin-{{ app()->getLocale() === 'ar' ? 'right' : 'left' }}: auto; margin-top: 14px; border-collapse: collapse; }
    .totals-table td { padding: 3px 0; font-size: 12px; }
    .totals-table tr.grand td { border-top: 2px solid #0f172a; font-weight: bold; font-size: 13px; padding-top: 6px; }
    .qr-img { height: 100px; width: 100px; }
    .footer { margin-top: 30px; text-align: center; font-size: 10px; color: #94a3b8; border-top: 1px solid #e2e8f0; padding-top: 8px; }
</style>
</head>
<body>
    <table class="header-table">
        <tr>
            <td style="width: 60%;">
                <h1>{{ $company->name }}</h1>
                @if ($company->vat_number)<p class="muted">{{ __('VAT') }}: {{ $company->vat_number }}</p>@endif
                @if ($company->address)<p class="muted">{{ $company->address }}</p>@endif
            </td>
            <td style="width: 40%;" class="end">
                <h2>{{ $doc['type_label'] }}</h2>
                <p>{{ $doc['number'] }}</p>
                <p class="muted">{{ $doc['date_label'] }}: {{ $doc['date']->format('Y-m-d') }}</p>
                @if ($zatcaCleared)
                    <span class="badge-cleared">&#10003; {{ __('ZATCA Cleared') }}</span>
                @endif
            </td>
        </tr>
    </table>

    <table class="header-table" style="margin-top: 24px;">
        <tr>
            <td style="width: 60%;">
                <p class="label">{{ $doc['party_label'] }}</p>
                <p style="font-weight: bold;">{{ $doc['party']->name }}</p>
                @if ($doc['party']->vat_number)<p class="muted">{{ __('VAT') }}: {{ $doc['party']->vat_number }}</p>@endif
                @if (method_exists($doc['party'], 'fullAddress') && $doc['party']->fullAddress())<p class="muted">{{ $doc['party']->fullAddress() }}</p>@endif
            </td>
            <td style="width: 40%;" class="end">
                @if (! empty($doc['qr_code']))
                    <img class="qr-img" src="data:image/png;base64,{{ $doc['qr_code'] }}">
                @endif
            </td>
        </tr>
    </table>

    <table class="items">
        <thead>
            <tr>
                <th>{{ __('Description') }}</th>
                <th class="end">{{ __('Qty') }}</th>
                <th class="end">{{ __('Unit price') }}</th>
                <th class="end">{{ __('VAT') }}</th>
                <th class="end">{{ __('Total') }}</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($doc['lines'] as $line)
                <tr>
                    <td>{{ $line->description }}</td>
                    <td class="end">{{ rtrim(rtrim(number_format($line->quantity, 2), '0'), '.') }}</td>
                    <td class="end">SAR {{ number_format($line->unit_price, 2) }}</td>
                    <td class="end">SAR {{ number_format($line->vat_amount, 2) }}</td>
                    <td class="end">SAR {{ number_format($line->line_total, 2) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <table class="totals-table">
        <tr><td>{{ __('Subtotal') }}</td><td class="end">SAR {{ number_format($doc['subtotal'], 2) }}</td></tr>
        @if (($doc['discount_total'] ?? 0) > 0)
            <tr><td>{{ __('Discount') }}</td><td class="end">-SAR {{ number_format($doc['discount_total'], 2) }}</td></tr>
        @endif
        <tr><td>{{ __('VAT') }}</td><td class="end">SAR {{ number_format($doc['vat_total'], 2) }}</td></tr>
        <tr class="grand"><td>{{ __('Total') }}</td><td class="end">SAR {{ number_format($doc['total'], 2) }}</td></tr>
        @foreach ($doc['extra_rows'] ?? [] as $row)
            <tr><td>{{ $row['label'] }}</td><td class="end">SAR {{ number_format($row['value'], 2) }}</td></tr>
        @endforeach
    </table>

    @if (! empty($doc['notes']))
        <p style="margin-top: 20px; font-size: 11px;" class="muted">{{ $doc['notes'] }}</p>
    @endif

    <div class="footer">{{ $company->name }} &middot; {{ $doc['number'] }}</div>
</body>
</html>
