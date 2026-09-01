<!DOCTYPE html>
<html lang="{{ $locale }}" dir="{{ $locale === 'ar' ? 'rtl' : 'ltr' }}">
<head>
<meta charset="utf-8">
<style>
    body { font-family: 'DejaVu Sans', sans-serif; font-size: 11px; color: #1e293b; }
    h1 { font-size: 18px; margin-bottom: 2px; }
    h2 { font-size: 13px; margin-top: 18px; margin-bottom: 4px; }
    p.sub { color: #64748b; margin-top: 0; }
    table { width: 100%; border-collapse: collapse; margin-top: 6px; }
    th, td { padding: 4px 5px; text-align: {{ $locale === 'ar' ? 'right' : 'left' }}; }
    th { border-bottom: 1px solid #cbd5e1; color: #64748b; font-weight: normal; }
    td.amount, th.amount { text-align: {{ $locale === 'ar' ? 'left' : 'right' }}; }
    .summary { width: 100%; margin-top: 10px; border-collapse: collapse; }
    .summary td { padding: 6px 8px; border: 1px solid #e2e8f0; }
    .summary td.label { color: #64748b; font-size: 10px; text-transform: uppercase; }
    .summary td.value { font-weight: bold; font-size: 13px; }
</style>
</head>
<body>
    <h1>{{ $company->name }}</h1>
    <p class="sub">{{ __('Tax Report') }}</p>
    <p class="sub">{{ __('Period') }} {{ $period['from']->format('Y-m-d') }} &rarr; {{ $period['to']->format('Y-m-d') }}</p>

    <table class="summary">
        <tr>
            <td class="label">{{ __('Output Tax (Sales)') }}<br><span class="value">{{ \App\Support\Money::format($outputTax) }}</span></td>
            <td class="label">{{ __('Input Tax (Purchases)') }}<br><span class="value">{{ \App\Support\Money::format($inputTaxPurchases) }}</span></td>
            <td class="label">{{ __('Expense Tax') }}<br><span class="value">{{ \App\Support\Money::format($expenseTax) }}</span></td>
            <td class="label">{{ __('Net Tax Position') }}<br><span class="value">{{ \App\Support\Money::format($netTaxPosition) }}</span></td>
        </tr>
    </table>

    @if ($tab === 'sales')
        <h2>{{ __('Output Tax (Sales)') }}</h2>
        <table>
            <thead>
                <tr>
                    <th>{{ __('Date') }}</th>
                    <th>{{ __('Reference') }}</th>
                    <th>{{ __('Customer') }}</th>
                    <th class="amount">{{ __('Net Amount Excl. Tax') }}</th>
                    <th class="amount">{{ __('Tax Amount') }}</th>
                    <th class="amount">{{ __('Total Amount') }}</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($salesRows as $row)
                    <tr>
                        <td>{{ $row->issue_date?->format('Y-m-d') }}</td>
                        <td>{{ $row->invoice_number }}</td>
                        <td>{{ $row->client->name ?? '' }}</td>
                        <td class="amount">{{ number_format((float) $row->subtotal, 2) }}</td>
                        <td class="amount">{{ number_format((float) $row->vat_total, 2) }}</td>
                        <td class="amount">{{ number_format((float) $row->total, 2) }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @elseif ($tab === 'purchases')
        <h2>{{ __('Input Tax (Purchases)') }}</h2>
        <table>
            <thead>
                <tr>
                    <th>{{ __('Date') }}</th>
                    <th>{{ __('Reference') }}</th>
                    <th>{{ __('Supplier') }}</th>
                    <th class="amount">{{ __('Net Amount Excl. Tax') }}</th>
                    <th class="amount">{{ __('Tax Amount') }}</th>
                    <th class="amount">{{ __('Total Amount') }}</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($purchaseRows as $row)
                    <tr>
                        <td>{{ $row->bill_date?->format('Y-m-d') }}</td>
                        <td>{{ $row->bill_number }}</td>
                        <td>{{ $row->supplier->name ?? '' }}</td>
                        <td class="amount">{{ number_format((float) $row->subtotal, 2) }}</td>
                        <td class="amount">{{ number_format((float) $row->vat_total, 2) }}</td>
                        <td class="amount">{{ number_format((float) $row->total, 2) }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @else
        <h2>{{ __('Expense Tax') }}</h2>
        <table>
            <thead>
                <tr>
                    <th>{{ __('Date') }}</th>
                    <th>{{ __('Vendor') }}</th>
                    <th class="amount">{{ __('Net Amount Excl. Tax') }}</th>
                    <th class="amount">{{ __('Tax Amount') }}</th>
                    <th class="amount">{{ __('Total Amount') }}</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($expenseRows as $row)
                    <tr>
                        <td>{{ $row->expense_date?->format('Y-m-d') }}</td>
                        <td>{{ $row->vendor_name }}</td>
                        <td class="amount">{{ number_format((float) $row->amount, 2) }}</td>
                        <td class="amount">{{ number_format((float) $row->vat_amount, 2) }}</td>
                        <td class="amount">{{ number_format((float) ($row->gross_amount ?? $row->amount), 2) }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif
</body>
</html>
