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

@include('documents.print.partials.report.header', ['titleEn' => __('FIXED ASSET SCHEDULE'), 'titleAr' => 'جدول الأصول الثابتة', 'template' => $template ?? null])
@include('documents.print.partials.report.footer-banner', ['template' => $template ?? null])

@include('documents.print.partials.report.period-banner', ['period' => $period])

@include('documents.print.partials.report.summary-cards', ['cards' => [
    ['label_en' => __('Opening NBV'), 'label_ar' => 'صافي القيمة الدفترية الافتتاحية', 'value' => \App\Support\Money::format($totals['openingNbv']), 'variant' => 'neutral'],
    ['label_en' => __('Depreciation'), 'label_ar' => 'الإهلاك', 'value' => \App\Support\Money::format($totals['depreciation']), 'variant' => 'neutral'],
    ['label_en' => __('Closing NBV'), 'label_ar' => 'صافي القيمة الدفترية الختامية', 'value' => \App\Support\Money::format($totals['closingNbv']), 'variant' => 'good'],
]])

<table class="data-table" style="margin-top: 16px;">
    <thead>
        <tr>
            <th>{{ __('Code') }}</th>
            <th>{{ __('Asset') }}</th>
            <th>{{ __('Category') }}</th>
            <th class="num">{{ __('Opening NBV') }}</th>
            <th class="num">{{ __('Additions') }}</th>
            <th class="num">{{ __('Depreciation') }}</th>
            <th class="num">{{ __('Disposals') }}</th>
            <th class="num">{{ __('Closing NBV') }}</th>
        </tr>
    </thead>
    <tbody>
        @foreach ($rows as $row)
            <tr>
                <td>{{ $row['asset']->asset_code }}</td>
                <td>{{ $row['asset']->name }}</td>
                <td>{{ $row['asset']->category }}</td>
                <td class="num">{{ number_format($row['openingNbv'], 2) }}</td>
                <td class="num">{{ $row['additions'] > 0 ? number_format($row['additions'], 2) : '' }}</td>
                <td class="num">{{ $row['depreciation'] > 0 ? '('.number_format($row['depreciation'], 2).')' : '' }}</td>
                <td class="num">{{ $row['disposals'] > 0 ? '('.number_format($row['disposals'], 2).')' : '' }}</td>
                <td class="num">{{ number_format($row['closingNbv'], 2) }}</td>
            </tr>
        @endforeach
        <tr class="total-row">
            <td colspan="3">{{ __('TOTAL') }}</td>
            <td class="num">{{ number_format($totals['openingNbv'], 2) }}</td>
            <td class="num">{{ number_format($totals['additions'], 2) }}</td>
            <td class="num">{{ $totals['depreciation'] > 0 ? '('.number_format($totals['depreciation'], 2).')' : number_format(0, 2) }}</td>
            <td class="num">{{ $totals['disposals'] > 0 ? '('.number_format($totals['disposals'], 2).')' : number_format(0, 2) }}</td>
            <td class="num">{{ number_format($totals['closingNbv'], 2) }}</td>
        </tr>
    </tbody>
</table>

@include('documents.print.partials.report.footer', ['company' => $company])

</body>
</html>
