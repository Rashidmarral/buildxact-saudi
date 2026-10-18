{{--
    "add reports for bank accounts like custody accounts cash bank using
    beautiful and modern layouts so i can show the owner each account
    reports" — the branded PDF twin of account-reports/show.blade.php:
    opening balance, every receipt/payment/transfer/expense/income that
    moved through this one account, and a closing balance, built from the
    exact same BankAccountLedgerService ledger the on-screen statement
    uses. Visual language matches the redesigned voucher-summary-pdf.blade.php
    from this same session — a real logo, a full company info block, and
    the company's accent color — rather than the older, plainer
    project-cash-flow-pdf.blade.php this account type used to share with
    the gated Project Cash Flow module.
--}}
@php
    $logoData = $embed($company->logo_path ?? null);
    $footerData = $embed($template->footer_path ?? null);
    $accent = $template->accent_color ?? '#0f766e';
    $typeLabels = [
        'receipt' => __('Receipt'),
        'payment' => __('Payment'),
        'expense' => __('Expense'),
        'invoice_payment' => __('Invoice payment'),
        'income' => __('Income'),
        'withdrawal' => __('Withdrawal'),
        'deposit' => __('Deposit'),
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
    .company-name { font-size: 16pt; font-weight: bold; color: #0f172a; }
    .company-name-ar { font-size: 11pt; font-weight: bold; color: #334155; margin-top: 1px; }
    .company-detail { color: #64748b; font-size: 8.5pt; line-height: 1.5; margin-top: 3px; }
    .doc-title { font-size: 17pt; font-weight: bold; color: {{ $accent }}; text-align: right; }
    .muted { color: #64748b; font-size: 9pt; }
    .accent-rule { height: 3px; background: {{ $accent }}; margin-top: 10px; }
    .account-box { margin-top: 16px; padding: 12px 16px; background: #f8fafc; border-radius: 6px; border-left: 3px solid {{ $accent }}; }
    .account-box .k { color: #64748b; font-size: 8pt; text-transform: uppercase; letter-spacing: 0.4px; }
    .account-box .v { font-weight: bold; color: #0f172a; font-size: 12pt; margin-top: 2px; }
    .balance-strip { margin-top: 16px; }
    .balance-strip td { padding: 12px; border: 1pt solid #e2e8f0; text-align: center; }
    .balance-strip .k { color: #64748b; font-size: 8pt; text-transform: uppercase; letter-spacing: 0.4px; }
    .balance-strip .v { font-size: 14pt; font-weight: bold; color: #0f172a; margin-top: 4px; }
    .summary-table { margin-top: 14px; }
    .summary-table td { width: 20%; padding: 10px; border: 1pt solid #e2e8f0; text-align: center; }
    .summary-table .k { color: #64748b; font-size: 8pt; }
    .summary-table .v { font-size: 12pt; font-weight: bold; color: #0f172a; margin-top: 4px; }
    .ledger-table { margin-top: 14px; }
    .ledger-table th { background: #f1f5f9; color: #475569; font-size: 8.5pt; text-align: left; padding: 6px 8px; border-bottom: 1pt solid #cbd5e1; }
    .ledger-table td { padding: 6px 8px; border-bottom: 0.5pt solid #e2e8f0; font-size: 9pt; }
    .ledger-table td:first-child { white-space: nowrap; }
    .num { text-align: right; }
    .in-amount { color: #0f766e; }
    .out-amount { color: #b91c1c; }
    .balance-row td { font-weight: bold; background: #f8fafc; border-top: 0.75pt solid #cbd5e1; }
    .type-pill { display: inline-block; padding: 1px 7px; border-radius: 8px; font-size: 7.5pt; background: #f1f5f9; color: #475569; }
</style>
</head>
<body>

<table class="header-table">
    <tr>
        <td style="width: 58%;">
            @if ($logoData)<img src="{{ $logoData }}" style="height: 110px; max-width: 260px; margin-bottom: 10px;" alt="">@endif
            <div class="company-name">{{ $company->name }}</div>
            @if ($company->name_ar)<div class="company-name-ar">{{ $company->name_ar }}</div>@endif
            <div class="company-detail">
                @if ($company->address || $company->city)<div>{{ $company->address }}{{ $company->address && $company->city ? ', ' : '' }}{{ $company->city }}</div>@endif
                @if ($company->phone || $company->email)<div>{{ $company->phone }}{{ $company->phone && $company->email ? '  ·  ' : '' }}{{ $company->email }}</div>@endif
                @if ($company->vat_number)<div>{{ __('VAT') }}: {{ $company->vat_number }}@if ($company->cr_number)&nbsp;&nbsp;·&nbsp;&nbsp;{{ __('CR') }}: {{ $company->cr_number }}@endif</div>@endif
            </div>
        </td>
        <td style="width: 42%;">
            <div class="doc-title">{{ __('Account Statement') }}</div>
            <div class="muted" style="text-align: right;">{{ __('Generated') }}: {{ \App\Support\PlatformFormat::date(now()) }}</div>
            @if (($period['from'] ?? null) || ($period['to'] ?? null))
                <div class="muted" style="text-align: right;">{{ __('Period') }}: {{ $period['from'] ?: __('Earliest') }} — {{ $period['to'] ?: __('Today') }}</div>
            @endif
        </td>
    </tr>
</table>
<div class="accent-rule"></div>

<table class="account-box">
    <tr>
        <td>
            <div class="k">{{ __('Account') }}</div>
            <div class="v">{{ $account->name }}</div>
            <div class="muted">
                {{ $account->type === 'cash' ? __('Cash') : __('Bank') }}
                @if ($account->is_personal)
                    &nbsp;·&nbsp;{{ __('Custody') }}@if ($account->personal_owner_name) — {{ $account->personal_owner_name }} @endif
                @endif
                @if ($account->bank_name)&nbsp;·&nbsp;{{ $account->bank_name }}@endif
            </div>
        </td>
    </tr>
</table>

<table class="balance-strip">
    <tr>
        <td><div class="k">{{ __('Opening balance') }}</div><div class="v">{{ \App\Support\Money::format($ledger['opening_balance']) }}</div></td>
        <td><div class="k">{{ __('Closing balance') }}</div><div class="v">{{ \App\Support\Money::format($ledger['closing_balance']) }}</div></td>
    </tr>
</table>

<table class="summary-table">
    <tr>
        <td><div class="k">{{ __('Total received') }}</div><div class="v in-amount">{{ \App\Support\Money::format($summary['received']) }}</div></td>
        <td><div class="k">{{ __('Payment vouchers') }}</div><div class="v out-amount">{{ \App\Support\Money::format($summary['paid_vouchers']) }}</div></td>
        <td><div class="k">{{ __('Expenses') }}</div><div class="v out-amount">{{ \App\Support\Money::format($summary['expenses']) }}</div></td>
        <td><div class="k">{{ __('Moved to other accounts') }}</div><div class="v">{{ \App\Support\Money::format($summary['transferred']) }}</div></td>
        <td><div class="k">{{ __('Net cash position') }}</div><div class="v">{{ \App\Support\Money::format($summary['net']) }}</div></td>
    </tr>
</table>

@if ($summary['expense_by_category']->isNotEmpty())
    <table class="ledger-table" style="margin-top: 10px;">
        <thead>
            <tr><th colspan="2">{{ __('Expenses by category') }}</th></tr>
        </thead>
        <tbody>
            @foreach ($summary['expense_by_category'] as $row)
                <tr>
                    <td>{{ $row['category'] }} ({{ $row['count'] }})</td>
                    <td class="num out-amount">{{ \App\Support\Money::format($row['total']) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
@endif

<table class="ledger-table">
    <thead>
        <tr>
            <th style="width: 13%;">{{ __('Date') }}</th>
            <th style="width: 12%;">{{ __('Type') }}</th>
            <th style="width: 13%;">{{ __('Number') }}</th>
            <th style="width: 24%;">{{ __('Party') }}</th>
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
                <td><span class="type-pill">{{ $typeLabels[$row['type']] ?? $row['type'] }}</span></td>
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
