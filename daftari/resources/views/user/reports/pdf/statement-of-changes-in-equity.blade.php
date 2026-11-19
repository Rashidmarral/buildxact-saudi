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

@include('documents.print.partials.report.header', ['titleEn' => __('STATEMENT OF CHANGES IN EQUITY'), 'titleAr' => 'بيان التغيرات في حقوق الملكية', 'template' => $template ?? null])
@include('documents.print.partials.report.footer-banner', ['template' => $template ?? null])

@include('documents.print.partials.report.period-banner', ['period' => $period])

@include('documents.print.partials.report.summary-cards', ['cards' => [
    ['label_en' => __('Opening equity'), 'label_ar' => 'حقوق الملكية الافتتاحية', 'value' => \App\Support\Money::format($totalOpening), 'variant' => 'neutral'],
    ['label_en' => __('Net income'), 'label_ar' => 'صافي الدخل', 'value' => \App\Support\Money::format($netIncomeForPeriod), 'variant' => $netIncomeForPeriod >= 0 ? 'good' : 'bad'],
    ['label_en' => __('Closing equity'), 'label_ar' => 'حقوق الملكية الختامية', 'value' => \App\Support\Money::format($totalClosing), 'variant' => 'good'],
]])

<table class="data-table" style="margin-top: 16px;">
    <thead>
        <tr>
            <th>{{ __('Account') }}</th>
            <th class="num">{{ __('Opening') }}</th>
            <th class="num">{{ __('Movement') }}</th>
            <th class="num">{{ __('Net income') }}</th>
            <th class="num">{{ __('Closing') }}</th>
        </tr>
    </thead>
    <tbody>
        @foreach ($rows as $row)
            <tr>
                <td>{{ $row['account']->name }}</td>
                <td class="num">{{ number_format($row['opening'], 2) }}</td>
                <td class="num">{{ $row['movement'] != 0 ? number_format($row['movement'], 2) : '' }}</td>
                <td class="num"></td>
                <td class="num">{{ number_format($row['closing'], 2) }}</td>
            </tr>
        @endforeach
        <tr>
            <td>{{ __('Retained earnings') }}</td>
            <td class="num">{{ number_format($openingRetainedEarnings, 2) }}</td>
            <td class="num"></td>
            <td class="num">{{ number_format($netIncomeForPeriod, 2) }}</td>
            <td class="num">{{ number_format($openingRetainedEarnings + $netIncomeForPeriod, 2) }}</td>
        </tr>
        <tr class="total-row">
            <td>{{ __('TOTAL EQUITY') }}</td>
            <td class="num">{{ number_format($totalOpening, 2) }}</td>
            <td class="num">{{ number_format($totalMovement, 2) }}</td>
            <td class="num">{{ number_format($netIncomeForPeriod, 2) }}</td>
            <td class="num">{{ number_format($totalClosing, 2) }}</td>
        </tr>
    </tbody>
</table>

@include('documents.print.partials.report.footer', ['company' => $company])

</body>
</html>
