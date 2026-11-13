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
    'titleEn' => __('UNCLEARED BANK ITEMS'), 'titleAr' => 'بنود بنكية غير مسواة',
    'subtitleEn' => $bankAccount->name.' — '.__('As at :date', ['date' => $asOf->format('d M Y')]),
    'template' => $template ?? null,
])
@include('documents.print.partials.report.footer-banner', ['template' => $template ?? null])

<table class="data-table" style="margin-top: 16px;">
    <thead>
        <tr>
            <th>{{ __('Date') }}</th>
            <th>{{ __('Description') }}</th>
            <th class="num">{{ __('Amount') }}</th>
        </tr>
    </thead>
    <tbody>
        @foreach ($rows as $row)
            <tr>
                <td>{{ $row['date']->format('Y-m-d') }}</td>
                <td>{{ $row['label'] }}</td>
                <td class="num">{{ number_format($row['amount'], 2) }}</td>
            </tr>
        @endforeach
        <tr class="total-row">
            <td colspan="2">{{ __('NET EFFECT ON BALANCE') }}</td>
            <td class="num">{{ number_format($total, 2) }}</td>
        </tr>
    </tbody>
</table>

@include('documents.print.partials.report.footer', ['company' => $company])

</body>
</html>
