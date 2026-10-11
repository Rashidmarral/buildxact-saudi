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

@include('documents.print.partials.report.header', ['titleEn' => __('TRIAL BALANCE'), 'titleAr' => 'ميزان المراجعة'])

@include('documents.print.partials.report.period-banner', ['period' => $period])

@include('documents.print.partials.report.summary-cards', ['cards' => [
    ['label_en' => __('Total debit'), 'label_ar' => 'إجمالي المدين', 'value' => \App\Support\Money::format($totalDebit), 'variant' => 'neutral'],
    ['label_en' => __('Total credit'), 'label_ar' => 'إجمالي الدائن', 'value' => \App\Support\Money::format($totalCredit), 'variant' => 'neutral'],
]])

<table class="data-table" style="margin-top: 16px;">
    <thead>
        <tr>
            <th>{{ __('Code') }}</th>
            <th>{{ __('Account') }}</th>
            <th class="num">{{ __('Debit') }}</th>
            <th class="num">{{ __('Credit') }}</th>
        </tr>
    </thead>
    <tbody>
        @foreach ($rows as $row)
            <tr>
                <td>{{ $row['account']->code }}</td>
                <td>{{ $row['account']->name }}</td>
                <td class="num">{{ $row['debit'] > 0 ? number_format($row['debit'], 2) : '' }}</td>
                <td class="num">{{ $row['credit'] > 0 ? number_format($row['credit'], 2) : '' }}</td>
            </tr>
        @endforeach
        <tr class="total-row">
            <td colspan="2">{{ __('TOTAL') }}</td>
            <td class="num">{{ number_format($totalDebit, 2) }}</td>
            <td class="num">{{ number_format($totalCredit, 2) }}</td>
        </tr>
    </tbody>
</table>

@include('documents.print.partials.report.footer', ['company' => $company])

</body>
</html>
