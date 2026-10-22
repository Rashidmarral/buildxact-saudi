{{--
    Tax Report PDF — rebuilt on the shared, bilingual, branded
    report-PDF design (replacing the old plain black-and-white DomPDF
    layout) and rendered via MpdfRenderer for correct Arabic shaping.
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

@include('documents.print.partials.report.header', ['titleEn' => __('TAX REPORT'), 'titleAr' => 'التقرير الضريبي'])

@include('documents.print.partials.report.period-banner', ['period' => $period, 'labelEn' => __('TAX PERIOD'), 'labelAr' => 'الفترة الضريبية'])

@include('documents.print.partials.report.summary-cards', ['cards' => [
    ['label_en' => __('Output tax (sales)'), 'label_ar' => 'ضريبة المخرجات', 'value' => \App\Support\Money::format($outputTax), 'variant' => 'neutral'],
    ['label_en' => __('Input tax (purchases + expenses)'), 'label_ar' => 'ضريبة المدخلات', 'value' => \App\Support\Money::format($inputTaxPurchases + $expenseTax), 'variant' => 'good'],
    ['label_en' => __('Net tax position'), 'label_ar' => 'صافي الموقف الضريبي', 'value' => \App\Support\Money::format($netTaxPosition), 'variant' => $netTaxPosition >= 0 ? 'warn' : 'good'],
]])

@if ($tab === 'sales')
    @include('documents.print.partials.report.section-title', ['en' => __('OUTPUT TAX — SALES'), 'ar' => 'ضريبة المخرجات — المبيعات'])
    <table class="data-table">
        <thead>
            <tr>
                <th>{{ __('Date') }}</th>
                <th>{{ __('Reference') }}</th>
                <th>{{ __('Customer') }}</th>
                <th class="num">{{ __('Net Amount Excl. Tax') }}</th>
                <th class="num">{{ __('Tax Amount') }}</th>
                <th class="num">{{ __('Total Amount') }}</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($salesRows as $row)
                <tr>
                    <td>{{ $row->issue_date?->format('Y-m-d') }}</td>
                    <td>{{ $row->invoice_number }}</td>
                    <td>{{ $row->client->display_name ?? '' }}</td>
                    <td class="num">{{ number_format((float) $row->subtotal, 2) }}</td>
                    <td class="num">{{ number_format((float) $row->vat_total, 2) }}</td>
                    <td class="num">{{ number_format((float) $row->total, 2) }}</td>
                </tr>
            @endforeach
            <tr class="total-row">
                <td colspan="3">{{ __('TOTAL') }}</td>
                <td class="num">{{ number_format((float) $salesRows->sum('subtotal'), 2) }}</td>
                <td class="num">{{ number_format((float) $salesRows->sum('vat_total'), 2) }}</td>
                <td class="num">{{ number_format((float) $salesRows->sum('total'), 2) }}</td>
            </tr>
        </tbody>
    </table>
@elseif ($tab === 'purchases')
    @include('documents.print.partials.report.section-title', ['en' => __('INPUT TAX — PURCHASES'), 'ar' => 'ضريبة المدخلات — المشتريات'])
    <table class="data-table">
        <thead>
            <tr>
                <th>{{ __('Date') }}</th>
                <th>{{ __('Reference') }}</th>
                <th>{{ __('Supplier') }}</th>
                <th class="num">{{ __('Net Amount Excl. Tax') }}</th>
                <th class="num">{{ __('Tax Amount') }}</th>
                <th class="num">{{ __('Total Amount') }}</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($purchaseRows as $row)
                <tr>
                    <td>{{ $row->bill_date?->format('Y-m-d') }}</td>
                    <td>{{ $row->bill_number }}</td>
                    <td>{{ $row->supplier->display_name ?? '' }}</td>
                    <td class="num">{{ number_format((float) $row->subtotal, 2) }}</td>
                    <td class="num">{{ number_format((float) $row->vat_total, 2) }}</td>
                    <td class="num">{{ number_format((float) $row->total, 2) }}</td>
                </tr>
            @endforeach
            <tr class="total-row">
                <td colspan="3">{{ __('TOTAL') }}</td>
                <td class="num">{{ number_format((float) $purchaseRows->sum('subtotal'), 2) }}</td>
                <td class="num">{{ number_format((float) $purchaseRows->sum('vat_total'), 2) }}</td>
                <td class="num">{{ number_format((float) $purchaseRows->sum('total'), 2) }}</td>
            </tr>
        </tbody>
    </table>
@else
    @include('documents.print.partials.report.section-title', ['en' => __('EXPENSE TAX'), 'ar' => 'ضريبة المصروفات'])
    <table class="data-table">
        <thead>
            <tr>
                <th>{{ __('Date') }}</th>
                <th>{{ __('Vendor') }}</th>
                <th class="num">{{ __('Net Amount Excl. Tax') }}</th>
                <th class="num">{{ __('Tax Amount') }}</th>
                <th class="num">{{ __('Total Amount') }}</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($expenseRows as $row)
                <tr>
                    <td>{{ $row->expense_date?->format('Y-m-d') }}</td>
                    <td>{{ $row->vendor_name }}</td>
                    <td class="num">{{ number_format((float) $row->amount, 2) }}</td>
                    <td class="num">{{ number_format((float) $row->vat_amount, 2) }}</td>
                    <td class="num">{{ number_format((float) ($row->gross_amount ?? $row->amount), 2) }}</td>
                </tr>
            @endforeach
            <tr class="total-row">
                <td colspan="2">{{ __('TOTAL') }}</td>
                <td class="num">{{ number_format((float) $expenseRows->sum('amount'), 2) }}</td>
                <td class="num">{{ number_format((float) $expenseRows->sum('vat_amount'), 2) }}</td>
                <td class="num">{{ number_format((float) $expenseRows->sum(fn ($e) => $e->gross_amount ?? $e->amount), 2) }}</td>
            </tr>
        </tbody>
    </table>
@endif

@if ($tab === 'sales' && (abs($prepaymentVatDueThisPeriod ?? 0) > 0.004 || abs($advanceCreditVatAlreadyReported ?? 0) > 0.004 || abs($prepaymentVatAppliedThisPeriod ?? 0) > 0.004))
    <p style="font-size: 8pt; color: #94a3b8; margin-top: 10px;">
        {{ __('Output tax above is net of advance/prepayment adjustments: :gross gross', ['gross' => \App\Support\Money::format($outputTaxFromInvoices)]) }},
        @if (($prepaymentVatDueThisPeriod ?? 0) > 0.004) {{ __('+:amount VAT due on prepayment invoices issued this period', ['amount' => \App\Support\Money::format($prepaymentVatDueThisPeriod)]) }}, @endif
        @if (($advanceCreditVatAlreadyReported ?? 0) > 0.004) {{ __('−:amount already reported via Advance Credit', ['amount' => \App\Support\Money::format($advanceCreditVatAlreadyReported)]) }}, @endif
        @if (($prepaymentVatAppliedThisPeriod ?? 0) > 0.004) {{ __('−:amount already reported via Prepayment Invoice', ['amount' => \App\Support\Money::format($prepaymentVatAppliedThisPeriod)]) }}, @endif
        {{ __('= :net net', ['net' => \App\Support\Money::format($outputTax)]) }}.
    </p>
@endif

@if ($vatApportionmentEnabled ?? false)
    <p style="font-size: 8pt; color: #94a3b8; margin-top: 10px;">{{ __('Apportioned at :pct% recovery — :amount disallowed as attributable to exempt supplies.', ['pct' => number_format($recoveryPercentage, 2), 'amount' => \App\Support\Money::format($nonRecoverableInputTax)]) }}</p>
@endif

@include('documents.print.partials.report.footer', ['company' => $company])

</body>
</html>
