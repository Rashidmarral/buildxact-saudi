<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}" dir="{{ \App\Support\Locales::dir(app()->getLocale()) }}">
<head>
<meta charset="utf-8">
<style>
    body { font-family: 'DejaVu Sans', sans-serif; font-size: 12px; color: #1e293b; }
    h1 { font-size: 18px; margin-bottom: 2px; }
    p.sub { color: #64748b; margin-top: 0; }
    table { width: 100%; border-collapse: collapse; margin-top: 10px; }
    th, td { padding: 5px 6px; text-align: {{ app()->getLocale() === 'ar' ? 'right' : 'left' }}; }
    th { border-bottom: 1px solid #cbd5e1; color: #64748b; font-weight: normal; }
    td.amount, th.amount { text-align: {{ app()->getLocale() === 'ar' ? 'left' : 'right' }}; }
    tr.total td { border-top: 1px solid #cbd5e1; font-weight: bold; }
    .section { font-weight: bold; margin-top: 14px; }
</style>
</head>
<body>
    <h1>{{ $company->name }}</h1>
    <p class="sub">{{ __('Balance Sheet') }} — {{ __('As of') }} {{ $asOf->format('Y-m-d') }}</p>

    <div class="section">{{ __('Assets') }}</div>
    <table>
        @foreach ($assets as $row)
            <tr><td>{{ $row['account']->label() }}</td><td class="amount">{{ number_format($row['balance'], 2) }}</td></tr>
        @endforeach
        <tr class="total"><td>{{ __('Total assets') }}</td><td class="amount">{{ number_format($totalAssets, 2) }}</td></tr>
    </table>

    <div class="section">{{ __('Liabilities') }}</div>
    <table>
        @foreach ($liabilities as $row)
            <tr><td>{{ $row['account']->label() }}</td><td class="amount">{{ number_format($row['balance'], 2) }}</td></tr>
        @endforeach
        <tr class="total"><td>{{ __('Total liabilities') }}</td><td class="amount">{{ number_format($totalLiabilities, 2) }}</td></tr>
    </table>

    <div class="section">{{ __('Equity') }}</div>
    <table>
        @foreach ($equity as $row)
            <tr><td>{{ $row['account']?->label() ?? $row['label'] }}</td><td class="amount">{{ number_format($row['balance'], 2) }}</td></tr>
        @endforeach
        <tr class="total"><td>{{ __('Total equity') }}</td><td class="amount">{{ number_format($totalEquity, 2) }}</td></tr>
    </table>
</body>
</html>
