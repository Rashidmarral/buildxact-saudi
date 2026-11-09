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

@include('documents.print.partials.report.header', [
    'titleEn' => __('JOURNAL ENTRIES'), 'titleAr' => 'قيود اليومية',
    'subtitleEn' => ($from || $to) ? trim(($from ?? '').' — '.($to ?? '')) : null,
    'template' => $template ?? null,
])
@include('documents.print.partials.report.footer-banner', ['template' => $template ?? null])

@include('documents.print.partials.report.summary-cards', ['cards' => [
    ['label_en' => __('Entries'), 'label_ar' => 'عدد القيود', 'value' => (string) $entries->count(), 'variant' => 'neutral'],
    ['label_en' => __('Total debit'), 'label_ar' => 'إجمالي المدين', 'value' => \App\Support\Money::format($totalDebit), 'variant' => 'neutral'],
    ['label_en' => __('Total credit'), 'label_ar' => 'إجمالي الدائن', 'value' => \App\Support\Money::format($totalCredit), 'variant' => 'neutral'],
]])

<table class="data-table" style="margin-top: 16px;">
    <thead>
        <tr>
            <th>{{ __('Entry') }}</th>
            <th>{{ __('Date') }}</th>
            <th>{{ __('Description') }}</th>
            <th>{{ __('Source') }}</th>
            <th class="num">{{ __('Debit') }}</th>
            <th class="num">{{ __('Credit') }}</th>
        </tr>
    </thead>
    <tbody>
        @foreach ($entries as $entry)
            <tr>
                <td>{{ $entry->entry_number }}</td>
                <td>{{ $entry->entry_date->format('Y-m-d') }}</td>
                <td>{{ $entry->description }}</td>
                <td>{{ ucfirst(str_replace('_', ' ', $entry->source_type ?? '—')) }}</td>
                <td class="num">{{ number_format((float) $entry->total_debit, 2) }}</td>
                <td class="num">{{ number_format((float) $entry->total_credit, 2) }}</td>
            </tr>
        @endforeach
        <tr class="total-row">
            <td colspan="4">{{ __('TOTAL') }}</td>
            <td class="num">{{ number_format($totalDebit, 2) }}</td>
            <td class="num">{{ number_format($totalCredit, 2) }}</td>
        </tr>
    </tbody>
</table>

@include('documents.print.partials.report.footer', ['company' => $company])

</body>
</html>
