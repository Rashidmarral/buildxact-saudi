{{--
    Journal export — 12 columns is too wide for A4 portrait, so this
    opens on a landscape sheet, same mPDF sheet-size tag used by the
    Profit & Loss by month report.
--}}
@php($logoData = $embed($company->logo_path ?? null))
<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<style>
@include('documents.print.partials.report.styles')
.journal-export-table th, .journal-export-table td { font-size: 8px; padding: 3px 4px; }
</style>
</head>
<body>
<pagebreak sheet-size="A4-L" />

@include('documents.print.partials.report.header', ['titleEn' => __('JOURNAL EXPORT (ALL POSTINGS)'), 'titleAr' => 'تصدير قيود اليومية (جميع الحركات)', 'template' => $template ?? null])
@include('documents.print.partials.report.footer-banner', ['template' => $template ?? null])

@include('documents.print.partials.report.period-banner', ['period' => $period])

<table class="data-table journal-export-table" style="margin-top: 16px;">
    <thead>
        <tr>
            <th>{{ __('Date') }}</th>
            <th>{{ __('Reference') }}</th>
            <th>{{ __('Source') }}</th>
            <th>{{ __('Code') }}</th>
            <th>{{ __('Account') }}</th>
            <th>{{ __('Description') }}</th>
            <th class="num">{{ __('Debit') }}</th>
            <th class="num">{{ __('Credit') }}</th>
            <th>{{ __('Cost centre') }}</th>
            <th>{{ __('Contact') }}</th>
            <th>{{ __('Location') }}</th>
            <th>{{ __('User') }}</th>
        </tr>
    </thead>
    <tbody>
        @foreach ($rows as $row)
            <tr>
                <td>{{ $row['date']->format('Y-m-d') }}</td>
                <td>{{ $row['reference'] }}</td>
                <td>{{ $row['source'] }}</td>
                <td>{{ $row['code'] }}</td>
                <td>{{ $row['account'] }}</td>
                <td>{{ $row['description'] }}</td>
                <td class="num">{{ $row['debit'] > 0 ? number_format($row['debit'], 2) : '' }}</td>
                <td class="num">{{ $row['credit'] > 0 ? number_format($row['credit'], 2) : '' }}</td>
                <td>{{ $row['costCentre'] }}</td>
                <td>{{ $row['contact'] }}</td>
                <td>{{ $row['location'] }}</td>
                <td>{{ $row['user'] }}</td>
            </tr>
        @endforeach
        <tr class="total-row">
            <td colspan="6">{{ __('TOTAL') }}</td>
            <td class="num">{{ number_format($totalDebit, 2) }}</td>
            <td class="num">{{ number_format($totalCredit, 2) }}</td>
            <td colspan="4"></td>
        </tr>
    </tbody>
</table>

@include('documents.print.partials.report.footer', ['company' => $company])

</body>
</html>
