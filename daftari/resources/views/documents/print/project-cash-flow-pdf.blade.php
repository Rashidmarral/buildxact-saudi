{{--
    mPDF-compatible Project Cash Flow / Bank Account statement PDF —
    table-based like voucher-pdf.blade.php (mPDF doesn't support
    flex/grid). Does not branch on $template->layout — that enum is
    shaped for line-item documents (invoices/quotations); this statement
    only reuses the template's cosmetic fields (logo, footer, page size),
    same as voucher-pdf.blade.php already does.
--}}
@php
    $logoData = $embed($company->logo_path ?? null);
    $footerData = $embed($template->footer_path ?? null);
    $typeLabels = [
        'receipt' => __('Receipt'),
        'payment' => __('Payment'),
        'transfer' => __('Transfer'),
    ];
@endphp
<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<style>
    body { font-family: cairo, sans-serif; color: #1e293b; font-size: 9.5pt; }
    table { border-collapse: collapse; width: 100%; }
    .header-table td { vertical-align: top; }
    .company-name { font-size: 15pt; font-weight: bold; color: #0f172a; }
    .doc-title { font-size: 15pt; font-weight: bold; color: #0f766e; text-align: right; }
    .muted { color: #64748b; font-size: 9pt; }
    .summary-table td { width: 25%; padding: 10px; border: 1pt solid #e2e8f0; text-align: center; }
    .summary-table .k { color: #64748b; font-size: 8pt; }
    .summary-table .v { font-size: 12pt; font-weight: bold; color: #0f172a; margin-top: 4px; }
    .ledger-table { margin-top: 14px; }
    .ledger-table th { background: #f1f5f9; color: #475569; font-size: 8.5pt; text-align: left; padding: 6px 8px; border-bottom: 1pt solid #cbd5e1; }
    .ledger-table td { padding: 6px 8px; border-bottom: 0.5pt solid #e2e8f0; font-size: 9pt; }
    .num { text-align: right; }
    .in-amount { color: #0f766e; }
    .out-amount { color: #b91c1c; }
    .balance-row td { font-weight: bold; background: #f8fafc; }
</style>
</head>
<body>

<table class="header-table" style="border-bottom: 1pt solid #0f172a; padding-bottom: 10px;">
    <tr>
        <td style="width: 60%;">
            @if ($logoData)<img src="{{ $logoData }}" style="height: 36px; margin-bottom: 6px;" alt="">@endif
            <div class="company-name">{{ $company->name }}</div>
            @if ($company->vat_number)<div class="muted">{{ __('VAT') }}: {{ $company->vat_number }}</div>@endif
        </td>
        <td style="width: 40%;">
            <div class="doc-title">{{ $title }}</div>
            <div class="muted" style="text-align: right;">{{ $subject }}</div>
            <div class="muted" style="text-align: right;">{{ __('Generated') }}: {{ \App\Support\PlatformFormat::date(now()) }}</div>
        </td>
    </tr>
</table>

@if ($summary)
    <table class="summary-table" style="margin-top: 16px;">
        <tr>
            <td><div class="k">{{ __('Total received') }}</div><div class="v in-amount">{{ \App\Support\Money::format($summary['received']) }}</div></td>
            <td><div class="k">{{ __('Total paid') }}</div><div class="v out-amount">{{ \App\Support\Money::format($summary['paid']) }}</div></td>
            <td><div class="k">{{ __('Total transferred out') }}</div><div class="v out-amount">{{ \App\Support\Money::format($summary['transferred']) }}</div></td>
            <td><div class="k">{{ __('Net cash position') }}</div><div class="v">{{ \App\Support\Money::format($summary['net']) }}</div></td>
        </tr>
    </table>
@endif

<table class="ledger-table">
    <thead>
        <tr>
            <th style="width: 11%;">{{ __('Date') }}</th>
            <th style="width: 12%;">{{ __('Type') }}</th>
            <th style="width: 14%;">{{ __('Number') }}</th>
            <th style="width: 25%;">{{ __('Party') }}</th>
            <th style="width: 14%;" class="num">{{ __('In') }}</th>
            <th style="width: 14%;" class="num">{{ __('Out') }}</th>
            <th style="width: 14%;" class="num">{{ __('Balance') }}</th>
        </tr>
    </thead>
    <tbody>
        <tr class="balance-row">
            <td colspan="6">{{ __('Opening balance') }}</td>
            <td class="num">{{ \App\Support\Money::format($ledger['opening_balance']) }}</td>
        </tr>
        @forelse ($ledger['rows'] as $row)
            <tr>
                <td>{{ \App\Support\PlatformFormat::date($row['date']) }}</td>
                <td>{{ $typeLabels[$row['type']] }}</td>
                <td>{{ $row['number'] ?: '—' }}</td>
                {{-- mPDF's Cairo font has no glyph for "→" (U+2192, used in the
                     on-screen party string for transfer rows) — falls back to
                     a plain ASCII arrow here rather than a broken/missing
                     glyph box in the rendered PDF. --}}
                <td>{{ str_replace('→', '->', $row['party']) }}</td>
                <td class="num in-amount">{{ $row['in_amount'] > 0 ? \App\Support\Money::format($row['in_amount']) : '—' }}</td>
                <td class="num out-amount">{{ $row['out_amount'] > 0 ? \App\Support\Money::format($row['out_amount']) : '—' }}</td>
                <td class="num">{{ \App\Support\Money::format($row['balance_after']) }}</td>
            </tr>
        @empty
            <tr><td colspan="7" class="muted" style="text-align: center; padding: 16px;">{{ __('No cash movement recorded yet.') }}</td></tr>
        @endforelse
        <tr class="balance-row">
            <td colspan="6">{{ __('Closing balance') }}</td>
            <td class="num">{{ \App\Support\Money::format($ledger['closing_balance']) }}</td>
        </tr>
    </tbody>
</table>

@if ($footerData)
    <img src="{{ $footerData }}" style="width: 100%; margin-top: 20px;" alt="">
@endif

</body>
</html>
