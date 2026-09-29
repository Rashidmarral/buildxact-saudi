{{--
    mPDF-compatible per-machine revenue/cost statement — structurally
    identical to project-cash-flow-pdf.blade.php's summary-boxes-then-
    table shape (reused, not redesigned). Does not branch on
    $template->layout — only its cosmetic fields (logo, footer, page
    size) are reused, same as every other non-line-item document here.
--}}
@php
    $logoData = $embed($company->logo_path ?? null);
    $footerData = $embed($template->footer_path ?? null);
    $typeLabels = ['revenue' => __('Revenue'), 'expense' => __('Expense')];
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
    .summary-table td { width: 33.33%; padding: 10px; border: 1pt solid #e2e8f0; text-align: center; }
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

<table class="summary-table" style="margin-top: 16px;">
    <tr>
        <td><div class="k">{{ __('Total revenue') }}</div><div class="v in-amount">{{ \App\Support\Money::format($summary['revenue']) }}</div></td>
        <td><div class="k">{{ __('Total running cost') }}</div><div class="v out-amount">{{ \App\Support\Money::format($summary['cost']) }}</div></td>
        <td><div class="k">{{ __('Net result (est.)') }}</div><div class="v">{{ \App\Support\Money::format($summary['net']) }}</div></td>
    </tr>
</table>

<table class="ledger-table">
    <thead>
        <tr>
            <th style="width: 14%;">{{ __('Date') }}</th>
            <th style="width: 14%;">{{ __('Type') }}</th>
            <th style="width: 16%;">{{ __('Number') }}</th>
            <th style="width: 28%;">{{ __('Party') }}</th>
            <th style="width: 14%;" class="num">{{ __('In') }}</th>
            <th style="width: 14%;" class="num">{{ __('Out') }}</th>
        </tr>
    </thead>
    <tbody>
        @forelse ($rows as $row)
            <tr>
                <td>{{ \App\Support\PlatformFormat::date($row['date']) }}</td>
                <td>{{ $typeLabels[$row['type']] ?? $row['type'] }}</td>
                <td>{{ $row['number'] ?: '—' }}</td>
                <td>{{ $row['party'] ?: '—' }}</td>
                <td class="num in-amount">{{ $row['in_amount'] > 0 ? \App\Support\Money::format($row['in_amount']) : '—' }}</td>
                <td class="num out-amount">{{ $row['out_amount'] > 0 ? \App\Support\Money::format($row['out_amount']) : '—' }}</td>
            </tr>
        @empty
            <tr><td colspan="6" class="muted" style="text-align: center; padding: 16px;">{{ __('No revenue or expenses recorded yet.') }}</td></tr>
        @endforelse
    </tbody>
</table>

@if ($footerData)
    <img src="{{ $footerData }}" style="width: 100%; margin-top: 20px;" alt="">
@endif

</body>
</html>
