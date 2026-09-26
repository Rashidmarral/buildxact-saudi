<!DOCTYPE html>
<html lang="{{ $locale }}" dir="{{ $locale === 'ar' ? 'rtl' : 'ltr' }}">
<head>
<meta charset="utf-8">
<style>
    body { font-family: 'DejaVu Sans', sans-serif; font-size: 12px; color: #1e293b; }
    h1 { font-size: 18px; margin-bottom: 2px; }
    p.sub { color: #64748b; margin-top: 0; }
    table { width: 100%; border-collapse: collapse; margin-top: 10px; }
    th, td { padding: 5px 6px; text-align: {{ $locale === 'ar' ? 'right' : 'left' }}; }
    th { border-bottom: 1px solid #cbd5e1; color: #64748b; font-weight: normal; }
    td.amount, th.amount { text-align: {{ $locale === 'ar' ? 'left' : 'right' }}; }
    tr.opening td { font-style: italic; color: #94a3b8; }
</style>
</head>
<body>
    <h1>{{ $company->name }}</h1>
    <p class="sub">{{ __('Account Statement') }} — {{ $party->name }}</p>
    <p class="sub">{{ __('Period') }} {{ $period['from']->format('Y-m-d') }} &rarr; {{ $period['to']->format('Y-m-d') }}</p>

    <table>
        <thead>
            <tr>
                <th>{{ __('Date') }}</th>
                <th>{{ __('Description') }}</th>
                <th class="amount">{{ __('Debit') }}</th>
                <th class="amount">{{ __('Credit') }}</th>
                <th class="amount">{{ __('Balance') }}</th>
            </tr>
        </thead>
        <tbody>
            <tr class="opening">
                <td colspan="4">{{ __('Opening balance') }}</td>
                <td class="amount">{{ number_format($openingBalance, 2) }}</td>
            </tr>
            @foreach ($lines as $line)
                <tr>
                    <td>{{ $line->date->format('Y-m-d') }}</td>
                    <td>{{ $line->description }}</td>
                    <td class="amount">{{ $line->debit > 0 ? number_format($line->debit, 2) : '' }}</td>
                    <td class="amount">{{ $line->credit > 0 ? number_format($line->credit, 2) : '' }}</td>
                    <td class="amount">{{ number_format($line->balance, 2) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
</body>
</html>
