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

@include('documents.print.partials.report.header', ['titleEn' => __('EXPENSE REPORT'), 'titleAr' => 'تقرير المصروفات', 'template' => $template ?? null])
@include('documents.print.partials.report.footer-banner', ['template' => $template ?? null])

@include('documents.print.partials.report.period-banner', ['period' => $period])

@include('documents.print.partials.report.summary-cards', ['cards' => [
    ['label_en' => __('Total expenses'), 'label_ar' => 'إجمالي المصروفات', 'value' => \App\Support\Money::format($total), 'variant' => 'neutral'],
    ['label_en' => __('VAT'), 'label_ar' => 'ضريبة القيمة المضافة', 'value' => \App\Support\Money::format($vatTotal), 'variant' => 'neutral'],
]])

@if (isset($byAccount) && $byAccount->count() > 1)
<table class="data-table" style="margin-top: 16px;">
    <thead>
        <tr>
            <th>{{ __('Account') }}</th>
            <th class="num">{{ __('Amount') }}</th>
            <th class="num">{{ __('VAT') }}</th>
        </tr>
    </thead>
    <tbody>
        @foreach ($byAccount as $row)
            <tr>
                <td>{{ $row['label'] }}</td>
                <td class="num">{{ number_format($row['amount'], 2) }}</td>
                <td class="num">{{ number_format($row['vat_amount'], 2) }}</td>
            </tr>
        @endforeach
    </tbody>
</table>
@endif

<table class="data-table" style="margin-top: 16px;">
    <thead>
        <tr>
            <th>{{ __('Date') }}</th>
            <th>{{ __('Vendor') }}</th>
            <th>{{ __('Category') }}</th>
            <th>{{ __('Description') }}</th>
            <th>{{ __('Account') }}</th>
            <th class="num">{{ __('Amount') }}</th>
            <th class="num">{{ __('VAT') }}</th>
        </tr>
    </thead>
    <tbody>
        @foreach ($expenses as $row)
            <tr>
                <td>{{ $row->expense_date->format('Y-m-d') }}</td>
                <td>{{ $row->vendor_name }}</td>
                <td>{{ $row->category->name ?? '' }}</td>
                <td>{{ $row->description }}</td>
                <td>{{ $row->paid_by_owner ? __('Owed to owner') : ($row->employee ? __(":employee's custody", ['employee' => $row->employee->full_name]) : ($row->bankAccount?->name ?? __('Unpaid (payable)'))) }}</td>
                <td class="num">{{ number_format($row->amount, 2) }}</td>
                <td class="num">{{ number_format($row->vat_amount, 2) }}</td>
            </tr>
        @endforeach
        <tr class="total-row">
            <td colspan="5">{{ __('TOTAL') }}</td>
            <td class="num">{{ number_format($total, 2) }}</td>
            <td class="num">{{ number_format($vatTotal, 2) }}</td>
        </tr>
    </tbody>
</table>

@include('documents.print.partials.report.footer', ['company' => $company])

</body>
</html>
