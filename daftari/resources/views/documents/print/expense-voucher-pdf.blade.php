{{--
    "if i create any expense how can i get it's voucher to record it as
    pdf or in hard form" — a printable voucher for an Expense, in the same
    visual style as voucher-pdf.blade.php (Payment/Receipt Vouchers), but
    built from Expense's own fields rather than forcing Expense to carry
    PaymentVoucher's schema (voucher_number, payee_name, method, ...).
    Deliberately does NOT create a PaymentVoucher record — Expense already
    posts straight to the ledger itself (LedgerPostingService::
    postExpense()), so a second real voucher document would risk double-
    posting the same amount. This is read-only: a document to hand over or
    file, nothing more.
--}}
@php
    $logoData = $embed($company->logo_path ?? null);
    $stampData = $embed($company->stamp_path ?? null);
    $footerData = $embed($template->footer_path ?? null);
    $letterheadData = $embed($template->letterhead_path ?? null);
    $layout = $template->layout ?? null;
    $accent = $template->accent_color ?? '#0f766e';

    $docNumber = 'EXP-'.str_pad((string) $expense->id, 5, '0', STR_PAD_LEFT);
    $grossAmount = (float) ($expense->gross_amount ?? ($expense->amount + $expense->vat_amount));
    $amountWordsEn = \App\Support\NumberToWords::englishRiyals($grossAmount);
    $amountWordsAr = \App\Support\NumberToWords::arabicRiyals($grossAmount);

    if ($expense->paid_by_owner) {
        $paidFrom = __('Owed to owner (reimbursable)');
    } elseif ($expense->employee) {
        $paidFrom = __(":employee's custody", ['employee' => $expense->employee->full_name]);
    } elseif ($expense->bankAccount) {
        $paidFrom = $expense->bankAccount->name;
    } else {
        $paidFrom = __('Unpaid (accounts payable)');
    }
@endphp
<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<style>
    body { font-family: cairo, sans-serif; color: #1e293b; font-size: 10pt; }
    table { border-collapse: collapse; width: 100%; }
    .header-table td { vertical-align: top; }
    .company-name { font-size: 15pt; font-weight: bold; color: #0f172a; }
    .company-name-ar { font-size: 11pt; font-weight: bold; color: #334155; margin-top: 1px; }
    .company-detail { color: #64748b; font-size: 8.5pt; line-height: 1.5; margin-top: 3px; }
    .doc-title { font-size: 16pt; font-weight: bold; color: {{ $accent }}; text-align: right; }
    .muted { color: #64748b; font-size: 9pt; }
    .accent-rule { height: 3px; background: {{ $accent }}; margin-top: 10px; margin-bottom: 16px; }
    .box { border: 1pt solid #e2e8f0; border-radius: 10px; padding: 18px; }
    .amount-strip { background: #f8fafc; border-radius: 8px; padding: 10px 14px; margin-top: 2px; }
    .row-table td { padding: 7px 0; border-bottom: 0.5pt dashed #e2e8f0; font-size: 10pt; vertical-align: top; }
    .k { color: #94a3b8; font-size: 8.5pt; }
    .v { font-weight: bold; color: #0f172a; }
    .sign-table td { text-align: center; font-size: 9pt; color: #94a3b8; padding-top: 40px; border-top: 0.5pt solid #cbd5e1; }
    .footer-contact-table { margin-top: 22px; }
    .footer-contact { border-top: 0.75pt solid #e2e8f0; padding-top: 10px; text-align: center; font-size: 8pt; color: #94a3b8; }
</style>
</head>
<body>

@if ($footerData)
    <htmlpagefooter name="footer-banner">
        <img src="{{ $footerData }}" style="width: 100%;" alt="">
    </htmlpagefooter>
    <sethtmlpagefooter name="footer-banner" page="ALL" />
@endif

@if ($layout === 'custom_letterhead' && $letterheadData)
    <htmlpageheader name="expense-voucher-letterhead">
        <img src="{{ $letterheadData }}" style="width: 100%;" alt="">
    </htmlpageheader>
    <sethtmlpageheader name="expense-voucher-letterhead" page="ALL" show-this-page="1" />
    <table class="header-table">
        <tr>
            <td style="width: 60%;"></td>
            <td style="width: 40%;">
                <div class="doc-title">{{ __('Expense Voucher') }}</div>
                <div class="muted" style="text-align: right;">{{ __('No.') }}: {{ $docNumber }}</div>
                <div class="muted" style="text-align: right;">{{ __('Date') }}: {{ \App\Support\PlatformFormat::date($expense->expense_date) }}</div>
            </td>
        </tr>
    </table>
@else
    <table class="header-table">
        <tr>
            <td style="width: 60%;">
                @if ($logoData)<img src="{{ $logoData }}" style="height: 56px; margin-bottom: 8px;" alt="">@endif
                <div class="company-name">{{ $company->name }}</div>
                @if ($company->name_ar)<div class="company-name-ar">{{ $company->name_ar }}</div>@endif
                <div class="company-detail">
                    @if ($company->address || $company->city)<div>{{ $company->address }}{{ $company->address && $company->city ? ', ' : '' }}{{ $company->city }}</div>@endif
                    @if ($company->phone || $company->email)<div>{{ $company->phone }}{{ $company->phone && $company->email ? '  ·  ' : '' }}{{ $company->email }}</div>@endif
                    @if ($company->vat_number)<div>{{ __('VAT') }}: {{ $company->vat_number }}@if ($company->cr_number)&nbsp;&nbsp;·&nbsp;&nbsp;{{ __('CR') }}: {{ $company->cr_number }}@endif</div>@endif
                </div>
            </td>
            <td style="width: 40%;">
                <div class="doc-title">{{ __('Expense Voucher') }}</div>
                <div class="muted" style="text-align: right;">{{ __('No.') }}: {{ $docNumber }}</div>
                <div class="muted" style="text-align: right;">{{ __('Date') }}: {{ \App\Support\PlatformFormat::date($expense->expense_date) }}</div>
            </td>
        </tr>
    </table>
@endif
<div class="accent-rule"></div>

<div class="box">
    <table class="amount-strip">
        <tr>
            <td style="width: 33%;" class="muted">{{ __('No.') }}: <span class="v">{{ $docNumber }}</span></td>
            <td style="width: 34%; text-align: center;">
                <div style="font-size: 13pt; font-weight: bold; color: #0f172a;">{{ __('Expense Voucher') }}</div>
                <div style="font-size: 10pt; color: #64748b;">سند مصروف</div>
            </td>
            <td style="width: 33%; text-align: right;">
                <div style="font-size: 15pt; font-weight: bold; color: {{ $accent }};">{{ \App\Support\Money::format($grossAmount) }}</div>
            </td>
        </tr>
    </table>

    <table class="row-table" style="margin-top: 14px;">
        <tr>
            <td style="width: 50%;"><span class="k">{{ __('Paid to') }}</span><br><span class="v">{{ $expense->vendor_name ?: '—' }}</span></td>
            <td style="width: 50%; text-align: right;"><span class="k">أصرفوا للمكرم</span><br><span class="v">{{ $expense->vendor_name ?: '—' }}</span></td>
        </tr>
        <tr>
            <td><span class="k">{{ __('The Sum of') }}</span><br><span class="v">{{ $amountWordsEn }}</span></td>
            <td style="text-align: right;"><span class="k">مبلغاً وقدره فقط</span><br><span class="v">{{ $amountWordsAr }}</span></td>
        </tr>
        <tr>
            <td><span class="k">{{ __('Category') }}</span><br><span class="v">{{ $expense->category?->name ?: '—' }}</span></td>
            <td style="text-align: right;"><span class="k">{{ __('Paid from') }}</span><br><span class="v">{{ $paidFrom }}</span></td>
        </tr>
        @if ($expense->description)
            <tr><td colspan="2"><span class="k">{{ __('Description') }}</span><br><span class="v">{{ $expense->description }}</span></td></tr>
        @endif
        @if ((float) $expense->vat_amount > 0)
            <tr><td colspan="2"><span class="k">{{ __('VAT included') }}</span> <span class="v">{{ \App\Support\Money::format($expense->vat_amount) }}</span></td></tr>
        @endif
        @if ($expense->reference)
            <tr><td colspan="2"><span class="k">{{ __('Reference') }}</span> <span class="v">{{ $expense->reference }}</span></td></tr>
        @endif
        @if ($expense->project)
            <tr><td colspan="2"><span class="k">{{ __('Project') }}</span> <span class="v">{{ $expense->project->name }}</span></td></tr>
        @endif
    </table>

    @if ($stampData)
        <table style="margin-top: 20px;">
            <tr>
                <td style="width: 70%;"></td>
                <td style="width: 30%; text-align: center;">
                    <img src="{{ $stampData }}" style="width: {{ $company->stamp_size ?: 90 }}px;" alt="">
                </td>
            </tr>
        </table>
    @endif

    <table class="sign-table" style="margin-top: {{ $stampData ? 10 : 50 }}px;">
        <tr>
            <td style="width: 50%;">{{ __('Accountant') }} / المحاسب</td>
            <td style="width: 50%;">{{ __('Receiver') }} / توقيع المستلم</td>
        </tr>
    </table>
</div>

@if ($company->address || $company->city || $company->phone || $company->email)
    <table class="footer-contact-table">
        <tr><td class="footer-contact">
            @if ($company->address || $company->city)
                <span>{{ $company->address }}{{ $company->address && $company->city ? ', ' : '' }}{{ $company->city }}</span>
            @endif
            @if ($company->phone)
                <span>@if ($company->address || $company->city)&nbsp;&nbsp;·&nbsp;&nbsp;@endif{{ $company->phone }}</span>
            @endif
            @if ($company->email)
                <span>@if ($company->address || $company->city || $company->phone)&nbsp;&nbsp;·&nbsp;&nbsp;@endif{{ $company->email }}</span>
            @endif
        </td></tr>
    </table>
@endif

</body>
</html>
