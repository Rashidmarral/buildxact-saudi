<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}" dir="{{ app()->getLocale() === 'ar' ? 'rtl' : 'ltr' }}">
<head>
<meta charset="utf-8">
<style>
    body { font-family: 'DejaVu Sans', sans-serif; font-size: 12px; color: #1e293b; }
    h1 { font-size: 18px; margin-bottom: 2px; }
    p.sub { color: #64748b; margin-top: 0; }
    p.notice { color: #92400e; background: #fffbeb; border: 1px solid #fde68a; padding: 8px 10px; border-radius: 6px; }
    table { width: 100%; border-collapse: collapse; margin-top: 14px; }
    td { padding: 5px 6px; }
    td.amount { text-align: {{ app()->getLocale() === 'ar' ? 'left' : 'right' }}; }
    tr.total td { border-top: 1px solid #cbd5e1; font-weight: bold; }
    tr.due td { font-weight: bold; font-size: 14px; color: #0f766e; }
</style>
</head>
<body>
    <h1>{{ $company->name }}</h1>
    <p class="sub">{{ __('Zakat Estimate') }} — {{ __('Period ending') }} {{ $calculation->period_end_date->format('Y-m-d') }}</p>

    <p class="notice">{{ __('This is an estimate for internal planning only. Your official Zakat return must be filed and verified through ZATCA\'s own Zakat, Tax and Customs Authority portal — consult a qualified accountant before relying on this figure.') }}</p>

    <table>
        <tr><td>{{ __('Total equity') }}</td><td class="amount">{{ number_format($calculation->equity_amount, 2) }}</td></tr>
        <tr><td>{{ __('Long-term liabilities') }}</td><td class="amount">+ {{ number_format($calculation->long_term_liabilities, 2) }}</td></tr>
        <tr><td>{{ __('Net fixed assets') }}</td><td class="amount">- {{ number_format($calculation->net_fixed_assets, 2) }}</td></tr>
        <tr><td>{{ __('Other deductions') }}</td><td class="amount">- {{ number_format($calculation->other_deductions, 2) }}</td></tr>
        <tr class="total"><td>{{ __('Zakat base') }}</td><td class="amount">{{ number_format($calculation->zakat_base, 2) }}</td></tr>
        <tr><td>{{ __('Rate') }}</td><td class="amount">{{ $calculation->rate_type === 'gregorian' ? __('2.5775% (Gregorian)') : __('2.5% (Hijri)') }}</td></tr>
        <tr class="due"><td>{{ __('Zakat due') }}</td><td class="amount">SAR {{ number_format($calculation->zakat_due, 2) }}</td></tr>
    </table>
</body>
</html>
