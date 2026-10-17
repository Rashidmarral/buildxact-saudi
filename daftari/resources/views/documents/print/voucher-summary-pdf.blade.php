{{--
    "there is option to get 1 single payment voucher for whole amount" —
    bundles every voucher matching a filter (one supplier/client, an
    optional date range, an optional free-text search) into one branded
    PDF: each voucher listed with its own date/reference/amount, plus a
    grand total. $type is 'payment' or 'receipt'.

    Feature request: a company recording every payment under one
    "reference" supplier (their main contractor) while the real money
    actually goes to several different people/firms, named only in each
    voucher's free-text reference — "I need what we paid to Shahid,
    Ashraf and Aljazeera separately and also in one file". $search
    (optional) filters the voucher list itself, for a single person's
    own PDF. $groups (optional, built by
    PaymentVoucherController::buildVoucherGroups()) renders this same
    combined document broken into one section + subtotal per person
    instead of one flat table, with anything matching none of them
    collected into a final "Other" section — so nothing paid out is
    ever silently left off the combined total.

    "shahid will sign the document as he received this all payments ...
    add sign area at the end that claims the supplier have approved
    that he have received these payment as proof" — a declaration
    sentence plus a two-column signature block (recipient / company
    representative), with the recipient's printed name pre-filled from
    $search when a single-payee PDF was downloaded (the quick-filter
    label), otherwise left blank for manual completion.
--}}
@php
    $logoData = $embed($company->logo_path ?? null);
    $footerData = $embed($template->footer_path ?? null);
    $isPayment = $type === 'payment';
    $accent = $template->accent_color ?? '#0f766e';
    $groups = $groups ?? null;
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
    .party-box { margin-top: 16px; padding: 12px 16px; background: #f8fafc; border-radius: 6px; border-left: 3px solid {{ $accent }}; }
    .party-box .k { color: #64748b; font-size: 8pt; text-transform: uppercase; letter-spacing: 0.4px; }
    .party-box .v { font-weight: bold; color: #0f172a; font-size: 12pt; margin-top: 2px; }
    .search-note { margin-top: 8px; font-size: 8.5pt; color: #64748b; font-style: italic; }
    .summary-table { margin-top: 16px; }
    .summary-table td { padding: 12px; border: 1pt solid #e2e8f0; text-align: center; }
    .summary-table .k { color: #64748b; font-size: 8pt; text-transform: uppercase; letter-spacing: 0.4px; }
    .summary-table .v { font-size: 14pt; font-weight: bold; color: #0f172a; margin-top: 4px; }
    .group-heading { margin-top: 18px; padding: 7px 10px; background: #eef2f7; border-radius: 4px; }
    .group-heading .name { font-size: 11pt; font-weight: bold; color: #0f172a; }
    .group-heading .amount { font-size: 11pt; font-weight: bold; color: {{ $accent }}; text-align: right; }
    .ledger-table { margin-top: 10px; }
    .ledger-table th { background: #f1f5f9; color: #475569; font-size: 8.5pt; text-align: left; padding: 6px 8px; border-bottom: 1pt solid #cbd5e1; }
    .ledger-table td { padding: 6px 8px; border-bottom: 0.5pt solid #e2e8f0; font-size: 9pt; }
    .num { text-align: right; }
    .subtotal-row td { font-weight: bold; background: #fafbfc; border-top: 0.75pt solid #cbd5e1; color: #475569; }
    .total-row td { font-weight: bold; background: #f1f5f9; border-top: 1.5pt solid {{ $accent }}; font-size: 10pt; }
    .declaration { margin-top: 26px; padding: 12px 16px; background: #f8fafc; border-radius: 6px; border-left: 3px solid {{ $accent }}; font-size: 9pt; color: #334155; line-height: 1.6; }
    .sign-grid { margin-top: 22px; }
    .sign-grid td { width: 50%; vertical-align: top; padding-right: 24px; }
    .sign-label { font-size: 8pt; font-weight: bold; color: #64748b; text-transform: uppercase; letter-spacing: 0.4px; }
    .sign-line { border-bottom: 1pt solid #334155; height: 42px; margin-top: 6px; }
    .sign-field { font-size: 9pt; color: #0f172a; margin-top: 10px; }
    .sign-field .fill { border-bottom: 0.75pt dotted #94a3b8; display: inline-block; min-width: 150px; }
</style>
</head>
<body>

<table class="header-table">
    <tr>
        <td style="width: 58%;">
            @if ($logoData)<img src="{{ $logoData }}" style="height: 64px; margin-bottom: 8px;" alt="">@endif
            <div class="company-name">{{ $company->name }}</div>
            @if ($company->name_ar)<div class="company-name-ar">{{ $company->name_ar }}</div>@endif
            <div class="company-detail">
                @if ($company->address || $company->city)<div>{{ $company->address }}{{ $company->address && $company->city ? ', ' : '' }}{{ $company->city }}</div>@endif
                @if ($company->phone || $company->email)<div>{{ $company->phone }}{{ $company->phone && $company->email ? '  ·  ' : '' }}{{ $company->email }}</div>@endif
                @if ($company->vat_number)<div>{{ __('VAT') }}: {{ $company->vat_number }}@if ($company->cr_number)&nbsp;&nbsp;·&nbsp;&nbsp;{{ __('CR') }}: {{ $company->cr_number }}@endif</div>@endif
            </div>
        </td>
        <td style="width: 42%;">
            <div class="doc-title">{{ $isPayment ? __('Payment Summary') : __('Receipt Summary') }}</div>
            <div class="muted" style="text-align: right;">{{ __('Generated') }}: {{ \App\Support\PlatformFormat::date(now()) }}</div>
            @if ($from || $to)
                <div class="muted" style="text-align: right;">{{ __('Period') }}: {{ $from ?: __('Earliest') }} — {{ $to ?: __('Today') }}</div>
            @endif
        </td>
    </tr>
</table>
<div class="accent-rule"></div>

<table class="party-box">
    <tr>
        <td>
            <div class="k">{{ $isPayment ? __('Paid to') : __('Received from') }}</div>
            <div class="v">{{ $party?->name ?? '—' }}</div>
            @if ($party?->vat_number)<div class="muted">{{ __('VAT') }}: {{ $party->vat_number }}</div>@endif
            @if ($party?->cr_number)<div class="muted">{{ __('CR') }}: {{ $party->cr_number }}</div>@endif
        </td>
    </tr>
</table>
@if (! empty($search))
    <div class="search-note">{{ $isPayment ? __('Filtered to vouchers matching ":term" in their reference, payee, or notes.', ['term' => $search]) : __('Filtered to vouchers matching ":term" in their reference, payer, or notes.', ['term' => $search]) }}</div>
@endif

<table class="summary-table">
    <tr>
        <td><div class="k">{{ __('Number of vouchers') }}</div><div class="v">{{ $vouchers->count() }}</div></td>
        <td><div class="k">{{ $isPayment ? __('Total paid') : __('Total received') }}</div><div class="v">{{ \App\Support\Money::format($total) }}</div></td>
    </tr>
</table>

@php
    $ledgerHeader = '
        <thead>
            <tr>
                <th style="width: 14%;">'.__('Voucher no.').'</th>
                <th style="width: 12%;">'.__('Date').'</th>
                <th style="width: 18%;">'.__('Account').'</th>
                <th style="width: 14%;">'.__('Method').'</th>
                <th style="width: 27%;">'.__('Reference').'</th>
                <th style="width: 15%;" class="num">'.__('Amount').'</th>
            </tr>
        </thead>
    ';
@endphp

@if ($groups !== null)
    @foreach ($groups as $group)
        <table class="group-heading">
            <tr>
                <td class="name">{{ $group['label'] }}</td>
                <td class="amount">{{ \App\Support\Money::format($group['total']) }} &nbsp; <span style="font-weight: normal; color: #64748b; font-size: 8.5pt;">({{ trans_choice(':count voucher|:count vouchers', count($group['vouchers']), ['count' => count($group['vouchers'])]) }})</span></td>
            </tr>
        </table>
        <table class="ledger-table">
            {!! $ledgerHeader !!}
            <tbody>
                @forelse ($group['vouchers'] as $voucher)
                    <tr>
                        <td>{{ $voucher->voucher_number }}</td>
                        <td>{{ \App\Support\PlatformFormat::date($voucher->date) }}</td>
                        <td>{{ $voucher->bankAccount?->name ?: '—' }}</td>
                        <td>{{ $voucher->method ? ucfirst(str_replace('_', ' ', $voucher->method)) : '—' }}</td>
                        <td>{{ $voucher->reference ?: '—' }}</td>
                        <td class="num">{{ \App\Support\Money::format($voucher->amount) }}</td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="muted" style="text-align: center; padding: 10px;">{{ __('No vouchers in this group.') }}</td></tr>
                @endforelse
                <tr class="subtotal-row">
                    <td colspan="5">{{ __('Subtotal') }} — {{ $group['label'] }}</td>
                    <td class="num">{{ \App\Support\Money::format($group['total']) }}</td>
                </tr>
            </tbody>
        </table>
    @endforeach
    <table class="ledger-table" style="margin-top: 12px;">
        <tbody>
            <tr class="total-row">
                <td colspan="5">{{ __('GRAND TOTAL') }}</td>
                <td class="num">{{ \App\Support\Money::format($total) }}</td>
            </tr>
        </tbody>
    </table>
@else
    <table class="ledger-table">
        {!! $ledgerHeader !!}
        <tbody>
            @forelse ($vouchers as $voucher)
                <tr>
                    <td>{{ $voucher->voucher_number }}</td>
                    <td>{{ \App\Support\PlatformFormat::date($voucher->date) }}</td>
                    <td>{{ $voucher->bankAccount?->name ?: '—' }}</td>
                    <td>{{ $voucher->method ? ucfirst(str_replace('_', ' ', $voucher->method)) : '—' }}</td>
                    <td>{{ $voucher->reference ?: '—' }}</td>
                    <td class="num">{{ \App\Support\Money::format($voucher->amount) }}</td>
                </tr>
            @empty
                <tr><td colspan="6" class="muted" style="text-align: center; padding: 16px;">{{ __('No vouchers match this filter.') }}</td></tr>
            @endforelse
            <tr class="total-row">
                <td colspan="5">{{ __('TOTAL') }}</td>
                <td class="num">{{ \App\Support\Money::format($total) }}</td>
            </tr>
        </tbody>
    </table>
@endif

<div class="declaration">
    @if ($isPayment)
        {{ __('I, the undersigned, acknowledge receipt of the total amount of :amount from :company, as full settlement for the payment voucher(s) detailed above.', ['amount' => \App\Support\Money::format($total), 'company' => $company->name]) }}
    @else
        {{ __('I, the undersigned, confirm that the total amount of :amount detailed above has been received by :company.', ['amount' => \App\Support\Money::format($total), 'company' => $company->name]) }}
    @endif
</div>

<table class="sign-grid">
    <tr>
        <td>
            <div class="sign-label">{{ $isPayment ? __('Received by (supplier / recipient)') : __('Acknowledged by (payer)') }}</div>
            <div class="sign-line"></div>
            <div class="sign-field">{{ __('Name') }}: @if (! empty($search))<strong>{{ $search }}</strong>@else<span class="fill">&nbsp;</span>@endif</div>
            <div class="sign-field">{{ __('ID / Iqama No.') }}: <span class="fill">&nbsp;</span></div>
            <div class="sign-field">{{ __('Date') }}: <span class="fill">&nbsp;</span></div>
        </td>
        <td>
            <div class="sign-label">{{ __('For :company', ['company' => $company->name]) }}</div>
            <div class="sign-line"></div>
            <div class="sign-field">{{ __('Name') }}: <span class="fill">&nbsp;</span></div>
            <div class="sign-field">{{ __('Position') }}: <span class="fill">&nbsp;</span></div>
            <div class="sign-field">{{ __('Date') }}: <span class="fill">&nbsp;</span></div>
        </td>
    </tr>
</table>

@if ($footerData)
    <img src="{{ $footerData }}" style="width: 100%; margin-top: 20px;" alt="">
@endif

</body>
</html>
