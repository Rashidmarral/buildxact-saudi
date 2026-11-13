{{--
    Cash Tracker report PDF — a plain running ledger (deposits in,
    withdrawals out, running balance) plus a by-party breakdown, for the
    standalone informal cash log (see CashTracker's own doc comment).
--}}
@php
    $logoData = $embed($company->logo_path ?? null);
    $running = 0;
@endphp
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
    'titleEn' => $tracker->name, 'titleAr' => $tracker->name,
    'subtitleEn' => __('Cash tracker report — as of :date', ['date' => \App\Support\PlatformFormat::date(now())]),
    'subtitleAr' => 'تقرير متابعة النقدية — كما في '.now()->format('d M Y'),
    'template' => $template ?? null,
])
@include('documents.print.partials.report.footer-banner', ['template' => $template ?? null])

@include('documents.print.partials.report.summary-cards', ['cards' => [
    ['label_en' => __('Total deposited'), 'label_ar' => 'إجمالي الإيداع', 'value' => \App\Support\Money::format($tracker->totalDeposited(), $tracker->currency), 'variant' => 'neutral'],
    ['label_en' => __('Total withdrawn'), 'label_ar' => 'إجمالي المسحوب', 'value' => \App\Support\Money::format($tracker->totalWithdrawn(), $tracker->currency), 'variant' => 'neutral'],
    ['label_en' => __('Current balance'), 'label_ar' => 'الرصيد الحالي', 'value' => \App\Support\Money::format($tracker->balance(), $tracker->currency), 'variant' => 'warn'],
]])

@include('documents.print.partials.report.section-title', ['en' => __('ENTRIES'), 'ar' => 'الحركات'])
<table class="data-table">
    <thead>
        <tr>
            <th>{{ __('Date') }}</th>
            <th>{{ __('Party') }}</th>
            <th>{{ __('Purpose') }}</th>
            <th class="num">{{ __('Deposit') }}</th>
            <th class="num">{{ __('Withdrawn') }}</th>
            <th class="num">{{ __('Balance') }}</th>
        </tr>
    </thead>
    <tbody>
        @foreach ($tracker->entries as $entry)
            @php($running += $entry->isDeposit() ? $entry->amount : -$entry->amount)
            <tr>
                <td>{{ $entry->date->format('Y-m-d') }}</td>
                <td>{{ $entry->party_name ?: '—' }}</td>
                <td>{{ $entry->purpose ?: '—' }}</td>
                <td class="num">{{ $entry->isDeposit() ? number_format($entry->amount, 2) : '—' }}</td>
                <td class="num">{{ ! $entry->isDeposit() ? number_format($entry->amount, 2) : '—' }}</td>
                <td class="num">{{ number_format($running, 2) }}</td>
            </tr>
        @endforeach
        <tr class="total-row">
            <td colspan="3">{{ __('TOTAL') }}</td>
            <td class="num">{{ number_format($tracker->totalDeposited(), 2) }}</td>
            <td class="num">{{ number_format($tracker->totalWithdrawn(), 2) }}</td>
            <td class="num">{{ number_format($tracker->balance(), 2) }}</td>
        </tr>
    </tbody>
</table>

@include('documents.print.partials.report.section-title', ['en' => __('BY PARTY'), 'ar' => 'حسب الطرف'])
<table class="data-table">
    <thead>
        <tr>
            <th>{{ __('Party') }}</th>
            <th class="num">{{ __('Deposited') }}</th>
            <th class="num">{{ __('Withdrawn') }}</th>
            <th class="num">{{ __('Net') }}</th>
        </tr>
    </thead>
    <tbody>
        @foreach ($summary as $row)
            <tr>
                <td>{{ $row['party'] }}</td>
                <td class="num">{{ number_format($row['deposited'], 2) }}</td>
                <td class="num">{{ number_format($row['withdrawn'], 2) }}</td>
                <td class="num">{{ number_format($row['net'], 2) }}</td>
            </tr>
        @endforeach
    </tbody>
</table>

@include('documents.print.partials.report.closing-banner', [
    'labelEn' => __('Current balance'),
    'labelAr' => 'الرصيد الحالي',
    'value' => \App\Support\Money::format($tracker->balance(), $tracker->currency),
    'variant' => 'warn',
])

@include('documents.print.partials.report.footer', ['company' => $company])

</body>
</html>
