@php($logoData = $embed($company->logo_path ?? null))
<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<style>
@include('documents.print.partials.report.styles')
</style>
</head>
<body>

@include('documents.print.partials.report.header', ['titleEn' => __('WITHHOLDING TAX RETURN'), 'titleAr' => 'إقرار ضريبة الاستقطاع'])

@include('documents.print.partials.report.period-banner', ['period' => $period])

@include('documents.print.partials.report.summary-cards', ['cards' => [
    ['label_en' => __('Total WHT withheld'), 'label_ar' => 'إجمالي الضريبة المستقطعة', 'value' => \App\Support\Money::format($total), 'variant' => 'warn'],
]])

<table class="data-table" style="margin-top: 16px;">
    <thead>
        <tr>
            <th>{{ __('Date') }}</th>
            <th>{{ __('Supplier') }}</th>
            <th>{{ __('Bill') }}</th>
            <th>{{ __('Category') }}</th>
            <th class="num">{{ __('Rate') }}</th>
            <th class="num">{{ __('Taxable base') }}</th>
            <th class="num">{{ __('WHT amount') }}</th>
        </tr>
    </thead>
    <tbody>
        @foreach ($vouchers as $v)
            <tr>
                <td>{{ $v->date->format('Y-m-d') }}</td>
                <td>{{ $v->bill?->supplier?->name }}</td>
                <td>{{ $v->bill?->bill_number }}</td>
                <td>{{ $v->bill?->whtRate?->name }}</td>
                <td class="num">{{ number_format((float) ($v->bill?->whtRate?->rate ?? 0), 2) }}</td>
                <td class="num">{{ number_format((float) ($v->bill?->subtotal ?? 0) - (float) ($v->bill?->discount_total ?? 0), 2) }}</td>
                <td class="num">{{ number_format((float) $v->wht_amount, 2) }}</td>
            </tr>
        @endforeach
        <tr class="total-row">
            <td colspan="6">{{ __('TOTAL') }}</td>
            <td class="num">{{ number_format($total, 2) }}</td>
        </tr>
    </tbody>
</table>

@include('documents.print.partials.report.footer', ['company' => $company])

</body>
</html>
