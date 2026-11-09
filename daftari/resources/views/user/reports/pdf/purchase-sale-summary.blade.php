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

@include('documents.print.partials.report.header', ['titleEn' => __('PURCHASE & SALE SUMMARY'), 'titleAr' => 'ملخص المبيعات والمشتريات', 'template' => $template ?? null])
@include('documents.print.partials.report.footer-banner', ['template' => $template ?? null])

@include('documents.print.partials.report.period-banner', ['period' => $period])

@include('documents.print.partials.report.summary-cards', ['cards' => [
    ['label_en' => __('Sales').' ('.$salesCount.')', 'label_ar' => 'المبيعات', 'value' => \App\Support\Money::format($salesTotal), 'variant' => 'good'],
    ['label_en' => __('Purchases').' ('.$purchasesCount.')', 'label_ar' => 'المشتريات', 'value' => \App\Support\Money::format($purchasesTotal), 'variant' => 'neutral'],
    ['label_en' => __('Net'), 'label_ar' => 'صافي', 'value' => \App\Support\Money::format($net), 'variant' => $net >= 0 ? 'good' : 'bad'],
]])

<table class="data-table" style="margin-top: 16px;">
    <thead>
        <tr>
            <th>{{ __('Month') }}</th>
            <th class="num">{{ __('Sales') }}</th>
            <th class="num">{{ __('Purchases') }}</th>
            <th class="num">{{ __('Net') }}</th>
        </tr>
    </thead>
    <tbody>
        @foreach ($rows as $row)
            <tr>
                <td>{{ $row['month']->translatedFormat('F Y') }}</td>
                <td class="num">{{ number_format($row['sales'], 2) }}</td>
                <td class="num">{{ number_format($row['purchases'], 2) }}</td>
                <td class="num">{{ number_format($row['net'], 2) }}</td>
            </tr>
        @endforeach
        <tr class="total-row">
            <td>{{ __('TOTAL') }}</td>
            <td class="num">{{ number_format($salesTotal, 2) }}</td>
            <td class="num">{{ number_format($purchasesTotal, 2) }}</td>
            <td class="num">{{ number_format($net, 2) }}</td>
        </tr>
    </tbody>
</table>

@include('documents.print.partials.report.footer', ['company' => $company])

</body>
</html>
