{{--
    Daftari's own subscription billing receipt — issued by the platform to
    a company for a plan payment, not a company-branded document like the
    invoice/quotation/bill templates. Always the same simple layout;
    Daftari's own legal/VAT details (App\Support\PlatformBranding, editable
    by a super admin) are shown only once configured — never fabricated.
--}}
@php
    $billing = \App\Support\PlatformBranding::all();
@endphp
<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<style>
    body { font-family: cairo, sans-serif; color: #1e293b; font-size: 10pt; }
    table { border-collapse: collapse; width: 100%; }
    .muted { color: #64748b; font-size: 9pt; }
    .text-end { text-align: right; }
    .text-center { text-align: center; }
    .title { font-size: 20pt; font-weight: bold; color: #0f172a; }
    .paid-badge { background-color: #d1fae5; color: #047857; font-size: 9pt; font-weight: bold; padding: 4px 12px; }
    .footer-note { margin-top: 24px; font-size: 8.5pt; color: #94a3b8; text-align: center; border-top: 0.5pt solid #e2e8f0; padding-top: 8px; }
    .logo { height: 40px; margin-bottom: 6px; }
</style>
</head>
<body>

<table style="margin-bottom: 20px;">
    <tr>
        <td>
            @if ($billing['pdf_logo_path'] ?? $billing['logo_path'])
                <img class="logo" src="{{ Storage::url($billing['pdf_logo_path'] ?: $billing['logo_path']) }}" alt="{{ $billing['name'] }}">
            @endif
            <div class="title">{{ $billing['name'] }}</div>
            @if ($billing['address'])<div class="muted">{{ $billing['address'] }}</div>@endif
            @if ($billing['vat_number'])<div class="muted">{{ __('VAT number') }}: {{ $billing['vat_number'] }}</div>@endif
            @if ($billing['cr_number'])<div class="muted">{{ __('CR Number') }}: {{ $billing['cr_number'] }}</div>@endif
            @if ($billing['phone'])<div class="muted">{{ $billing['phone'] }}</div>@endif
            @if ($billing['email'])<div class="muted">{{ $billing['email'] }}</div>@endif
        </td>
        <td class="text-end">
            <div class="title">{{ __('Receipt') }}</div>
            <div class="muted">#{{ $payment->id }}</div>
        </td>
    </tr>
</table>

<table style="margin-bottom: 20px; border-top: 1pt solid #e2e8f0; border-bottom: 1pt solid #e2e8f0; padding: 10px 0;">
    <tr>
        <td style="padding: 10px 0;">
            <div class="muted">{{ __('Billed to') }}</div>
            <div style="font-weight: bold;">{{ $company->name }}</div>
            @if ($company->name_ar)<div class="muted">{{ $company->name_ar }}</div>@endif
            @if ($company->vat_number)<div class="muted">{{ __('VAT number') }}: {{ $company->vat_number }}</div>@endif
            @if ($company->cr_number)<div class="muted">{{ __('CR Number') }}: {{ $company->cr_number }}</div>@endif
        </td>
        <td class="text-end" style="padding: 10px 0;">
            <div class="muted">{{ __('Date paid') }}</div>
            <div style="font-weight: bold;">{{ optional($payment->paid_at)->format('Y-m-d') ?? '—' }}</div>
            <div style="margin-top: 6px;"><span class="paid-badge">{{ __('Paid') }}</span></div>
        </td>
    </tr>
</table>

<table style="margin-top: 10px;">
    <thead>
        <tr style="border-bottom: 1pt solid #1e293b;">
            <td style="padding: 8px 0; font-weight: bold;">{{ __('Description') }}</td>
            <td style="padding: 8px 0; font-weight: bold;" class="text-end">{{ __('Amount') }}</td>
        </tr>
    </thead>
    <tbody>
        <tr style="border-bottom: 0.5pt solid #e2e8f0;">
            <td style="padding: 10px 0;">
                {{ __(':plan plan subscription', ['plan' => $payment->plan->name ?? '—']) }}
                @if ($payment->subscription)
                    <div class="muted">{{ ucfirst($payment->subscription->billing_cycle) }}</div>
                @endif
            </td>
            <td style="padding: 10px 0;" class="text-end">{{ $payment->currency }} {{ number_format($payment->amount, 2) }}</td>
        </tr>
    </tbody>
</table>

<table style="margin-top: 10px;">
    <tr>
        <td style="width: 60%;"></td>
        <td style="width: 40%; border-top: 1.5pt solid #1e293b; padding-top: 8px;">
            <table>
                <tr>
                    <td style="font-weight: bold; font-size: 12pt;">{{ __('Total paid') }}</td>
                    <td class="text-end" style="font-weight: bold; font-size: 12pt;">{{ $payment->currency }} {{ number_format($payment->amount, 2) }}</td>
                </tr>
            </table>
        </td>
    </tr>
</table>

@if ($payment->reference)
    <p class="muted" style="margin-top: 16px;">{{ __('Reference') }}: {{ $payment->reference }}</p>
@endif

<div class="footer-note">{{ __('This receipt confirms payment for your Daftari subscription.') }}</div>

</body>
</html>
