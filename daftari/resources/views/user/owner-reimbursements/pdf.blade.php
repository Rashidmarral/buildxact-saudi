{{--
    Owner Reimbursements statement — every expense paid personally out
    of pocket, with its settlement status, plus the running total still
    owed. Not a period statement (there's no natural "period" for an
    open-ended list of claims), so this skips the period banner and
    puts "as of" in the header subtitle instead.
--}}
@php
    $logoData = $embed($company->logo_path ?? null);
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
    'titleEn' => __('Owner Reimbursements'), 'titleAr' => 'مستحقات المالك',
    'subtitleEn' => __('As of :date', ['date' => \App\Support\PlatformFormat::date(now())]),
    'subtitleAr' => 'كما في '.now()->format('d M Y'),
    'template' => $template ?? null,
])
@include('documents.print.partials.report.footer-banner', ['template' => $template ?? null])

@include('documents.print.partials.report.summary-cards', ['cards' => [
    ['label_en' => __('Currently owed'), 'label_ar' => 'المستحق حالياً', 'value' => \App\Support\Money::format($totalOutstanding), 'variant' => 'warn'],
    ['label_en' => __('Total claims'), 'label_ar' => 'إجمالي المطالبات', 'value' => $expenses->count(), 'variant' => 'neutral'],
]])

@include('documents.print.partials.report.section-title', ['en' => __('CLAIM DETAIL'), 'ar' => 'تفاصيل المطالبات'])
<table class="data-table">
    <thead>
        <tr>
            <th>{{ __('Date') }}</th>
            <th>{{ __('Description') }}</th>
            <th>{{ __('Category') }}</th>
            <th class="num">{{ __('Amount') }}</th>
            <th>{{ __('Status') }}</th>
        </tr>
    </thead>
    <tbody>
        @foreach ($expenses as $expense)
            <tr>
                <td>{{ $expense->expense_date->format('Y-m-d') }}</td>
                <td>{{ $expense->vendor_name ?: $expense->description }}</td>
                <td>{{ $expense->category?->name ?? '—' }}</td>
                <td class="num">{{ number_format((float) ($expense->gross_amount ?? ($expense->amount + $expense->vat_amount)), 2) }}</td>
                <td>{{ $expense->isSettled() ? __('Reimbursed') : __('Outstanding') }}</td>
            </tr>
        @endforeach
    </tbody>
</table>

@include('documents.print.partials.report.closing-banner', [
    'labelEn' => __('Total currently owed to the owner'),
    'labelAr' => 'إجمالي المستحق للمالك',
    'value' => \App\Support\Money::format($totalOutstanding),
    'variant' => 'warn',
])

@include('documents.print.partials.report.footer', ['company' => $company])

</body>
</html>
