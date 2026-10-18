{{--
    mPDF-compatible voucher PDF, table-based to mirror voucher-body.blade.php
    (mPDF's HTML renderer doesn't support flex/grid — see pdf.blade.php's
    header comment for the same reasoning applied to full documents).

    "my company arabic name not coming ... remove account details like
    operating snb account ... company stamp not coming on pdf as well"
    — three real bugs/gaps fixed together: $company->name_ar was never
    rendered at all (only the English name); the bank account's own name
    (e.g. "SNB — Operating Account") was printed next to the payment
    method checkboxes, an internal detail with nothing to do with the
    counterparty signing this voucher; and $company->stamp_path — already
    supported on every other printed document (see pdf.blade.php,
    purchase-order-pdf.blade.php) — was simply missing from this one.
    Visual language upgraded to match the redesigned voucher-summary-pdf.blade.php
    from this same session: a real logo, a full company info block, and
    the company's accent color, instead of the older plain header.
--}}
@php
    $isReceipt = $type === 'receipt';
    $logoData = $embed($company->logo_path ?? null);
    $stampData = $embed($company->stamp_path ?? null);
    $footerData = $embed($template->footer_path ?? null);
    $accent = $template->accent_color ?? '#0f766e';
    $partyNameEn = $isReceipt ? $voucher->payer_name : $voucher->payee_name;
    $partyNameAr = $voucher->party_name_ar;
    $isCheque = $voucher->method === 'cheque';

    $purpose = $voucher->notes ?: $voucher->defaultPurpose();
    $purposeAr = $voucher->notes_ar ?: $purpose;

    $amountWordsEn = \App\Support\NumberToWords::englishRiyals((float) $voucher->amount);
    $amountWordsAr = \App\Support\NumberToWords::arabicRiyals((float) $voucher->amount);
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
    .checkbox { display: inline-block; width: 10px; height: 10px; border: 1pt solid #1e293b; text-align: center; font-size: 8pt; line-height: 10px; }
</style>
</head>
<body>

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
            <div class="doc-title">{{ $isReceipt ? __('Receipt Voucher') : __('Payment Voucher') }}</div>
            <div class="muted" style="text-align: right;">{{ __('No.') }}: {{ $voucher->voucher_number }}</div>
            <div class="muted" style="text-align: right;">{{ __('Date') }}: {{ \App\Support\PlatformFormat::date($voucher->date) }}</div>
        </td>
    </tr>
</table>
<div class="accent-rule"></div>

<div class="box">
    <table class="amount-strip">
        <tr>
            <td style="width: 34%;">
                <div style="font-size: 13pt; font-weight: bold; color: #0f172a;">{{ $isReceipt ? __('Receipt Voucher') : __('Payment Voucher') }}</div>
                <div style="font-size: 10pt; color: #64748b;">{{ $isReceipt ? 'سند قبض' : 'سند صرف' }}</div>
            </td>
            <td style="width: 33%; text-align: center;" class="muted">{{ __('No.') }}: <span class="v">{{ $voucher->voucher_number }}</span></td>
            <td style="width: 33%; text-align: right;">
                <div style="font-size: 15pt; font-weight: bold; color: {{ $accent }};">{{ \App\Support\Money::format($voucher->amount) }}</div>
            </td>
        </tr>
    </table>

    <table class="row-table" style="margin-top: 14px;">
        <tr>
            <td style="width: 50%;"><span class="k">{{ $isReceipt ? __('Received from') : __('Payment To') }}</span><br><span class="v">{{ $partyNameEn }}</span></td>
            <td style="width: 50%; text-align: right;"><span class="k">{{ $isReceipt ? 'استلمنا من' : 'أصرفوا للمكرم' }}</span><br><span class="v">{{ $partyNameAr ?: $partyNameEn }}</span></td>
        </tr>
        @if ($voucher->party_vat_number)
            <tr>
                <td><span class="k">{{ __('VAT number') }}</span><br><span class="v">{{ $voucher->party_vat_number }}</span></td>
                <td style="text-align: right;"><span class="k">الرقم الضريبي</span><br><span class="v">{{ $voucher->party_vat_number }}</span></td>
            </tr>
        @endif
        <tr>
            <td><span class="k">{{ __('The Sum of') }}</span><br><span class="v">{{ $amountWordsEn }}</span></td>
            <td style="text-align: right;"><span class="k">مبلغاً وقدره فقط</span><br><span class="v">{{ $amountWordsAr }}</span></td>
        </tr>
        <tr>
            <td colspan="2">
                <span class="checkbox">{{ $voucher->method === 'cash' ? 'X' : '' }}</span> {{ __('Cash') }} / نقداً
                &nbsp;&nbsp;
                <span class="checkbox">{{ $voucher->method === 'bank_transfer' ? 'X' : '' }}</span> {{ __('Bank transfer') }} / حوالة بنكية
                &nbsp;&nbsp;
                <span class="checkbox">{{ $voucher->method === 'card' ? 'X' : '' }}</span> {{ __('Card') }} / بطاقة
                &nbsp;&nbsp;
                <span class="checkbox">{{ $isCheque ? 'X' : '' }}</span> {{ __('Cheque') }} / بشيك
                @if ($isCheque)
                    &nbsp;&nbsp; {{ __('No.') }}: <span class="v">{{ $voucher->reference ?: '—' }}</span>
                    &nbsp;&nbsp; {{ __('Bank') }}: <span class="v">{{ $voucher->bankAccount->bank_name ?: $voucher->bankAccount->name }}</span>
                @endif
            </td>
        </tr>
        @if ($purpose)
            <tr>
                <td><span class="k">{{ __('For') }}</span><br><span class="v">{{ $purpose }}</span></td>
                <td style="text-align: right;"><span class="k">وذلك مقابل</span><br><span class="v">{{ $purposeAr }}</span></td>
            </tr>
        @endif
        @if ($voucher->counterAccount)
            <tr><td colspan="2"><span class="k">{{ __('Counter account') }}</span> <span class="v">{{ $voucher->counterAccount->label() }}</span></td></tr>
        @endif
        @if (! $isReceipt && (float) $voucher->wht_amount > 0)
            <tr><td colspan="2"><span class="k">{{ __('Withholding tax deducted') }}</span> <span class="v">{{ \App\Support\Money::format($voucher->wht_amount) }}</span></td></tr>
        @endif
        @if ($voucher->reference && ! $isCheque)
            <tr><td colspan="2"><span class="k">{{ __('Reference') }}</span> <span class="v">{{ $voucher->reference }}</span></td></tr>
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

@if ($footerData)
    <img src="{{ $footerData }}" style="width: 100%; margin-top: 20px;" alt="">
@endif

</body>
</html>
