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

@include('documents.print.partials.report.header', ['titleEn' => __('EMPLOYEE ADVANCES'), 'titleAr' => 'سلف الموظفين', 'subtitleEn' => __('As of :date', ['date' => now()->format('d M Y')]), 'template' => $template ?? null])
@include('documents.print.partials.report.footer-banner', ['template' => $template ?? null])

@include('documents.print.partials.report.summary-cards', ['cards' => [
    ['label_en' => __('Total advanced'), 'label_ar' => 'إجمالي السلف', 'value' => \App\Support\Money::format($totalAdvanced), 'variant' => 'neutral'],
    ['label_en' => __('Total settled'), 'label_ar' => 'إجمالي المسدد', 'value' => \App\Support\Money::format($totalSettled), 'variant' => 'good'],
    ['label_en' => __('Outstanding'), 'label_ar' => 'المتبقي', 'value' => \App\Support\Money::format($totalOutstanding), 'variant' => $totalOutstanding > 0 ? 'warn' : 'good'],
]])

<table class="data-table" style="margin-top: 16px;">
    <thead>
        <tr>
            <th>{{ __('Employee') }}</th>
            <th>{{ __('Employee No.') }}</th>
            <th class="num">{{ __('Advanced') }}</th>
            <th class="num">{{ __('Settled') }}</th>
            <th class="num">{{ __('Spent') }}</th>
            <th class="num">{{ __('Outstanding') }}</th>
        </tr>
    </thead>
    <tbody>
        @foreach ($rows as $row)
            <tr>
                <td>{{ $row['employee']->full_name }}</td>
                <td>{{ $row['employee']->employee_number }}</td>
                <td class="num">{{ number_format($row['advanced'], 2) }}</td>
                <td class="num">{{ number_format($row['settled'], 2) }}</td>
                <td class="num">{{ number_format($row['spent'], 2) }}</td>
                <td class="num">{{ number_format($row['outstanding'], 2) }}</td>
            </tr>
        @endforeach
        <tr class="total-row">
            <td colspan="2">{{ __('TOTAL') }}</td>
            <td class="num">{{ number_format($totalAdvanced, 2) }}</td>
            <td class="num">{{ number_format($totalSettled, 2) }}</td>
            <td class="num">{{ number_format($totalSpent, 2) }}</td>
            <td class="num">{{ number_format($totalOutstanding, 2) }}</td>
        </tr>
    </tbody>
</table>

@include('documents.print.partials.report.footer', ['company' => $company])

</body>
</html>
