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
    'titleEn' => __('ACCOUNT LEDGER'), 'titleAr' => 'دفتر الحساب',
    'subtitleEn' => $account->code.' — '.$account->name,
    'template' => $template ?? null,
])
@include('documents.print.partials.report.footer-banner', ['template' => $template ?? null])

@include('documents.print.partials.report.summary-cards', ['cards' => [
    ['label_en' => __('Opening balance'), 'label_ar' => 'الرصيد الافتتاحي', 'value' => \App\Support\Money::format($openingBalance), 'variant' => 'neutral'],
    ['label_en' => __('Closing balance'), 'label_ar' => 'الرصيد الختامي', 'value' => \App\Support\Money::format($closingBalance), 'variant' => 'good'],
]])

<table class="data-table" style="margin-top: 16px;">
    <thead>
        <tr>
            <th>{{ __('Date') }}</th>
            <th>{{ __('Entry') }}</th>
            <th>{{ __('Description') }}</th>
            <th class="num">{{ __('Debit') }}</th>
            <th class="num">{{ __('Credit') }}</th>
            <th class="num">{{ __('Balance') }}</th>
        </tr>
    </thead>
    <tbody>
        <tr>
            <td colspan="5"><em>{{ __('Opening balance') }}</em></td>
            <td class="num">{{ number_format($openingBalance, 2) }}</td>
        </tr>
        @foreach ($lines as $line)
            <tr>
                <td>{{ $line->journalEntry->entry_date->format('Y-m-d') }}</td>
                <td>{{ $line->journalEntry->entry_number }}</td>
                <td>{{ $line->journalEntry->description }}</td>
                <td class="num">{{ $line->debit > 0 ? number_format($line->debit, 2) : '' }}</td>
                <td class="num">{{ $line->credit > 0 ? number_format($line->credit, 2) : '' }}</td>
                <td class="num">{{ number_format($line->running_balance, 2) }}</td>
            </tr>
        @endforeach
        <tr class="total-row">
            <td colspan="5">{{ __('CLOSING BALANCE') }}</td>
            <td class="num">{{ number_format($closingBalance, 2) }}</td>
        </tr>
    </tbody>
</table>

@include('documents.print.partials.report.footer', ['company' => $company])

</body>
</html>
