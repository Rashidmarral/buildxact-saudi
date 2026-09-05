{{--
    mPDF-compatible voucher PDF, table-based to mirror voucher-body.blade.php
    (mPDF's HTML renderer doesn't support flex/grid — see pdf.blade.php's
    header comment for the same reasoning applied to full documents).
--}}
@php
    $isReceipt = $type === 'receipt';
    $logoData = $embed($company->logo_path ?? null);
    $footerData = $embed($template->footer_path ?? null);
    $partyNameEn = $isReceipt ? $voucher->payer_name : $voucher->payee_name;
    $partyNameAr = $voucher->party_name_ar;
    $isCheque = $voucher->method === 'cheque';

    $purpose = $voucher->notes;
    if (! $purpose) {
        if (! $isReceipt && $voucher->bill) {
            $purpose = __('Payment for bill :number', ['number' => $voucher->bill->bill_number]);
        } elseif ($isReceipt && $voucher->invoice) {
            $purpose = __('Payment for invoice :number', ['number' => $voucher->invoice->invoice_number]);
        } elseif (! $isReceipt && $voucher->expense) {
            $purpose = $voucher->expense->description ?: $voucher->expense->vendor_name;
        }
    }

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
    .doc-title { font-size: 15pt; font-weight: bold; color: #0f766e; text-align: right; }
    .muted { color: #64748b; font-size: 9pt; }
    .box { border: 1.5pt solid #1e293b; border-radius: 8px; padding: 16px; margin-top: 16px; }
    .row-table td { padding: 6px 0; border-bottom: 0.5pt dashed #e2e8f0; font-size: 10pt; vertical-align: top; }
    .k { color: #94a3b8; font-size: 8.5pt; }
    .v { font-weight: bold; color: #0f172a; }
    .sign-table td { text-align: center; font-size: 9pt; color: #94a3b8; padding-top: 40px; border-top: 0.5pt solid #cbd5e1; }
    .checkbox { display: inline-block; width: 10px; height: 10px; border: 1pt solid #1e293b; text-align: center; font-size: 8pt; line-height: 10px; }
</style>
</head>
<body>

<table class="header-table" style="border-bottom: 1pt solid #0f172a; padding-bottom: 10px;">
    <tr>
        <td style="width: 65%;">
            @if ($logoData)<img src="{{ $logoData }}" style="height: 36px; margin-bottom: 6px;" alt="">@endif
            <div class="company-name">{{ $company->name }}</div>
            @if ($company->vat_number)<div class="muted">{{ __('VAT') }}: {{ $company->vat_number }}</div>@endif
        </td>
        <td style="width: 35%;">
            <div class="doc-title">{{ $isReceipt ? __('Receipt Voucher') : __('Payment Voucher') }}</div>
            <div class="muted text-end" style="text-align: right;">{{ __('No.') }}: {{ $voucher->voucher_number }}</div>
            <div class="muted text-end" style="text-align: right;">{{ __('Date') }}: {{ \App\Support\PlatformFormat::date($voucher->date) }}</div>
        </td>
    </tr>
</table>

<div class="box">
    <table>
        <tr>
            <td style="width: 33%;" class="muted">{{ __('No.') }}: <span class="v">{{ $voucher->voucher_number }}</span></td>
            <td style="width: 34%; text-align: center;">
                <div style="font-size: 13pt; font-weight: bold;">{{ $isReceipt ? __('Receipt Voucher') : __('Payment Voucher') }}</div>
                <div style="font-size: 10pt; color: #64748b;">{{ $isReceipt ? 'سند قبض' : 'سند صرف' }}</div>
            </td>
            <td style="width: 33%; text-align: right;">
                <div style="font-size: 13pt; font-weight: bold;">{{ \App\Support\Money::format($voucher->amount) }}</div>
            </td>
        </tr>
    </table>

    <table class="row-table" style="margin-top: 10px;">
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
                <span class="checkbox">{{ $isCheque ? 'X' : '' }}</span> {{ __('Cheque') }} / بشيك
                @if ($isCheque)
                    &nbsp;&nbsp; {{ __('No.') }}: <span class="v">{{ $voucher->reference ?: '—' }}</span>
                    &nbsp;&nbsp; {{ __('Bank') }}: <span class="v">{{ $voucher->bankAccount->bank_name ?: $voucher->bankAccount->name }}</span>
                @else
                    &nbsp;&nbsp; {{ __('Account') }}: <span class="v">{{ $voucher->bankAccount->name }}</span>
                @endif
            </td>
        </tr>
        @if ($purpose)
            <tr>
                <td><span class="k">{{ __('For') }}</span><br><span class="v">{{ $purpose }}</span></td>
                <td style="text-align: right;"><span class="k">وذلك مقابل</span><br><span class="v">{{ $purpose }}</span></td>
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

    <table class="sign-table" style="margin-top: 50px;">
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
