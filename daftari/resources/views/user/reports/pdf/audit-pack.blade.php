{{--
    Year-End Audit Pack — one combined PDF bundling every statement a
    SOCPA/Saudi year-end audit PBC list commonly asks for first (Trial
    Balance, full General Ledger, Balance Sheet, Income Statement, Cash
    Flow, Statement of Changes in Equity, Fixed Asset Schedule, Aged
    Receivables/Payables) for the chosen period, so an auditor's first
    request is one download instead of eight. One shared header/footer
    banner (registered once, repeats on every page); each statement
    starts its own page via .page-break and prints its own title band
    so the document still reads like a stack of separate reports.
--}}
@php($logoData = $embed($company->logo_path ?? null))
<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<style>
@include('documents.print.partials.report.styles')
.page-break { page-break-before: always; }
</style>
</head>
<body>

@include('documents.print.partials.report.header', ['titleEn' => __('YEAR-END AUDIT PACK'), 'titleAr' => 'حزمة تدقيق نهاية السنة', 'template' => $template ?? null])
@include('documents.print.partials.report.footer-banner', ['template' => $template ?? null])

@include('documents.print.partials.report.period-banner', ['period' => $period])

@include('documents.print.partials.report.summary-cards', ['cards' => [
    ['label_en' => __('Total assets'), 'label_ar' => 'إجمالي الأصول', 'value' => \App\Support\Money::format($balanceSheet['totalAssets']), 'variant' => 'neutral'],
    ['label_en' => __('Net profit'), 'label_ar' => 'صافي الربح', 'value' => \App\Support\Money::format($incomeStatement['netProfit']), 'variant' => $incomeStatement['netProfit'] >= 0 ? 'good' : 'bad'],
    ['label_en' => __('Closing equity'), 'label_ar' => 'حقوق الملكية الختامية', 'value' => \App\Support\Money::format($equity['totalClosing']), 'variant' => 'good'],
]])

<table class="data-table" style="margin-top: 16px;">
    <thead><tr><th>{{ __('Contents') }}</th></tr></thead>
    <tbody>
        <tr><td>1. {{ __('Trial Balance') }}</td></tr>
        <tr><td>2. {{ __('General Ledger') }}</td></tr>
        <tr><td>3. {{ __('Balance Sheet') }}</td></tr>
        <tr><td>4. {{ __('Income Statement') }}</td></tr>
        <tr><td>5. {{ __('Cash Flow Statement') }}</td></tr>
        <tr><td>6. {{ __('Statement of Changes in Equity') }}</td></tr>
        <tr><td>7. {{ __('Fixed Asset Schedule') }}</td></tr>
        <tr><td>8. {{ __('Aged Receivables') }}</td></tr>
        <tr><td>9. {{ __('Aged Payables') }}</td></tr>
    </tbody>
</table>

{{-- 1. Trial Balance --}}
<div class="page-break">
    @include('documents.print.partials.report.header', ['titleEn' => __('TRIAL BALANCE'), 'titleAr' => 'ميزان المراجعة', 'template' => $template ?? null])
</div>
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
        @foreach ($trialBalanceRows as $row)
            <tr>
                <td>{{ $row['account']->code }}</td>
                <td>{{ $row['account']->name }}</td>
                <td class="num">{{ $row['debit'] > 0 ? number_format($row['debit'], 2) : '' }}</td>
                <td class="num">{{ $row['credit'] > 0 ? number_format($row['credit'], 2) : '' }}</td>
            </tr>
        @endforeach
        <tr class="total-row">
            <td colspan="2">{{ __('TOTAL') }}</td>
            <td class="num">{{ number_format($trialBalanceTotals['debit'], 2) }}</td>
            <td class="num">{{ number_format($trialBalanceTotals['credit'], 2) }}</td>
        </tr>
    </tbody>
</table>

{{-- 2. General Ledger --}}
@foreach ($generalLedgerAccounts as $i => $row)
    <div class="page-break">
        @if ($i === 0)
            @include('documents.print.partials.report.header', ['titleEn' => __('GENERAL LEDGER'), 'titleAr' => 'دفتر الأستاذ العام', 'template' => $template ?? null])
        @endif
        @include('documents.print.partials.report.section-title', ['en' => $row['account']->code.' — '.$row['account']->name, 'ar' => $row['account']->name_ar ?? ''])
    </div>
    <table class="data-table">
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
                <td class="num">{{ number_format($row['openingBalance'], 2) }}</td>
            </tr>
            @foreach ($row['lines'] as $line)
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
                <td colspan="3">{{ __('CLOSING BALANCE') }}</td>
                <td class="num">{{ number_format($row['periodDebit'], 2) }}</td>
                <td class="num">{{ number_format($row['periodCredit'], 2) }}</td>
                <td class="num">{{ number_format($row['closingBalance'], 2) }}</td>
            </tr>
        </tbody>
    </table>
@endforeach

{{-- 3. Balance Sheet --}}
@php($equityMovesBalance = $balanceSheet['totalAssets'] - ($balanceSheet['totalLiabilities'] + $balanceSheet['totalEquity']))
<div class="page-break">
    @include('documents.print.partials.report.header', ['titleEn' => __('BALANCE SHEET'), 'titleAr' => 'قائمة المركز المالي', 'subtitleEn' => __('As of :date', ['date' => $period['to']->format('d M Y')]), 'template' => $template ?? null])
</div>

@include('documents.print.partials.report.summary-cards', ['cards' => [
    ['label_en' => __('Total assets'), 'label_ar' => 'إجمالي الأصول', 'value' => \App\Support\Money::format($balanceSheet['totalAssets']), 'variant' => 'neutral'],
    ['label_en' => __('Total liabilities'), 'label_ar' => 'إجمالي الخصوم', 'value' => \App\Support\Money::format($balanceSheet['totalLiabilities']), 'variant' => 'warn'],
    ['label_en' => __('Total equity'), 'label_ar' => 'إجمالي حقوق الملكية', 'value' => \App\Support\Money::format($balanceSheet['totalEquity']), 'variant' => 'good'],
]])

@include('documents.print.partials.report.section-title', ['en' => __('ASSETS'), 'ar' => 'الأصول'])
<table class="data-table">
    <tbody>
        @foreach ($balanceSheet['assets'] as $row)
            <tr><td>{{ $row['account']->label() }}</td><td class="num">{{ number_format($row['balance'], 2) }}</td></tr>
        @endforeach
        <tr class="total-row"><td>{{ __('TOTAL ASSETS') }}</td><td class="num">{{ number_format($balanceSheet['totalAssets'], 2) }}</td></tr>
    </tbody>
</table>

@include('documents.print.partials.report.section-title', ['en' => __('LIABILITIES'), 'ar' => 'الخصوم'])
<table class="data-table">
    <tbody>
        @foreach ($balanceSheet['liabilities'] as $row)
            <tr><td>{{ $row['account']->label() }}</td><td class="num">{{ number_format($row['balance'], 2) }}</td></tr>
        @endforeach
        <tr class="total-row"><td>{{ __('TOTAL LIABILITIES') }}</td><td class="num">{{ number_format($balanceSheet['totalLiabilities'], 2) }}</td></tr>
    </tbody>
</table>

@include('documents.print.partials.report.section-title', ['en' => __('EQUITY'), 'ar' => 'حقوق الملكية'])
<table class="data-table">
    <tbody>
        @foreach ($balanceSheet['equity'] as $row)
            <tr><td>{{ $row['account']?->label() ?? $row['label'] }}</td><td class="num">{{ number_format($row['balance'], 2) }}</td></tr>
        @endforeach
        <tr class="total-row"><td>{{ __('TOTAL EQUITY') }}</td><td class="num">{{ number_format($balanceSheet['totalEquity'], 2) }}</td></tr>
    </tbody>
</table>

@include('documents.print.partials.report.closing-banner', [
    'labelEn' => __('TOTAL LIABILITIES + EQUITY'),
    'labelAr' => 'إجمالي الخصوم وحقوق الملكية',
    'value' => \App\Support\Money::format($balanceSheet['totalLiabilities'] + $balanceSheet['totalEquity']),
    'subEn' => abs($equityMovesBalance) < 0.01 ? __('Balances with total assets.') : __('Does not balance with total assets — review postings.'),
    'variant' => abs($equityMovesBalance) < 0.01 ? 'good' : 'warn',
])

{{-- 4. Income Statement --}}
<div class="page-break">
    @include('documents.print.partials.report.header', ['titleEn' => __('INCOME STATEMENT'), 'titleAr' => 'قائمة الدخل', 'template' => $template ?? null])
</div>

@include('documents.print.partials.report.summary-cards', ['cards' => [
    ['label_en' => __('Net sales'), 'label_ar' => 'صافي المبيعات', 'value' => \App\Support\Money::format($incomeStatement['netSales']), 'variant' => 'neutral'],
    ['label_en' => __('Gross profit'), 'label_ar' => 'إجمالي الربح', 'value' => \App\Support\Money::format($incomeStatement['grossProfit']), 'variant' => 'good'],
    ['label_en' => __('Net profit'), 'label_ar' => 'صافي الربح', 'value' => \App\Support\Money::format($incomeStatement['netProfit']), 'variant' => $incomeStatement['netProfit'] >= 0 ? 'good' : 'bad'],
]])

@include('documents.print.partials.report.section-title', ['en' => __('REVENUE'), 'ar' => 'الإيرادات'])
<table class="data-table">
    <tbody>
        @foreach ($incomeStatement['revenueLines'] as $row)
            <tr><td>{{ $row['account']->name }}</td><td class="num">{{ number_format($row['amount'], 2) }}</td></tr>
        @endforeach
        <tr class="total-row"><td>{{ __('NET SALES') }}</td><td class="num">{{ number_format($incomeStatement['netSales'], 2) }}</td></tr>
    </tbody>
</table>

@include('documents.print.partials.report.section-title', ['en' => __('EXPENSES'), 'ar' => 'المصروفات'])
<table class="data-table">
    <tbody>
        @foreach ($incomeStatement['expenseLines'] as $row)
            <tr><td>{{ $row['account']->name }}</td><td class="num">{{ number_format($row['amount'], 2) }}</td></tr>
        @endforeach
    </tbody>
</table>

@include('documents.print.partials.report.closing-banner', [
    'labelEn' => __('NET PROFIT'),
    'labelAr' => 'صافي الربح',
    'subEn' => __('Gross profit :gross — Operating profit :op', ['gross' => number_format($incomeStatement['grossProfit'], 2), 'op' => number_format($incomeStatement['operatingProfit'], 2)]),
    'value' => \App\Support\Money::format($incomeStatement['netProfit']),
    'variant' => $incomeStatement['netProfit'] >= 0 ? 'good' : 'warn',
])

{{-- 5. Cash Flow Statement --}}
<div class="page-break">
    @include('documents.print.partials.report.header', ['titleEn' => __('CASH FLOW STATEMENT'), 'titleAr' => 'قائمة التدفقات النقدية', 'template' => $template ?? null])
</div>

@include('documents.print.partials.report.summary-cards', ['cards' => [
    ['label_en' => __('Net change in cash'), 'label_ar' => 'صافي التغير في النقدية', 'value' => \App\Support\Money::format($cashFlow['netChange']), 'variant' => $cashFlow['netChange'] >= 0 ? 'good' : 'bad'],
    ['label_en' => __('Opening cash & bank'), 'label_ar' => 'النقدية الافتتاحية', 'value' => \App\Support\Money::format($cashFlow['openingCash']), 'variant' => 'neutral'],
    ['label_en' => __('Closing cash & bank'), 'label_ar' => 'النقدية الختامية', 'value' => \App\Support\Money::format($cashFlow['closingCash']), 'variant' => 'neutral'],
]])

@include('documents.print.partials.report.section-title', ['en' => __('OPERATING ACTIVITIES'), 'ar' => 'الأنشطة التشغيلية'])
<table class="data-table">
    <tbody>
        <tr><td>{{ __('Net income') }}</td><td class="num">{{ number_format($cashFlow['netIncome'], 2) }}</td></tr>
        <tr><td>{{ __('Depreciation') }}</td><td class="num">{{ number_format($cashFlow['depreciation'], 2) }}</td></tr>
        @foreach ($cashFlow['workingCapitalLines'] as $row)
            <tr><td>{{ $row['account']->name }}</td><td class="num">{{ number_format($row['cashEffect'], 2) }}</td></tr>
        @endforeach
        <tr class="total-row"><td>{{ __('NET CASH FROM OPERATING ACTIVITIES') }}</td><td class="num">{{ number_format($cashFlow['operatingTotal'], 2) }}</td></tr>
    </tbody>
</table>

@include('documents.print.partials.report.section-title', ['en' => __('INVESTING ACTIVITIES'), 'ar' => 'الأنشطة الاستثمارية'])
<table class="data-table">
    <tbody>
        <tr><td>{{ __('Fixed asset acquisitions') }}</td><td class="num">{{ number_format(-$cashFlow['acquisitions'], 2) }}</td></tr>
        <tr><td>{{ __('Fixed asset disposal proceeds') }}</td><td class="num">{{ number_format($cashFlow['disposalProceeds'], 2) }}</td></tr>
        <tr class="total-row"><td>{{ __('NET CASH FROM INVESTING ACTIVITIES') }}</td><td class="num">{{ number_format($cashFlow['investingTotal'], 2) }}</td></tr>
    </tbody>
</table>

@include('documents.print.partials.report.section-title', ['en' => __('FINANCING ACTIVITIES'), 'ar' => 'الأنشطة التمويلية'])
<table class="data-table">
    <tbody>
        @foreach ($cashFlow['equityLines'] as $row)
            <tr><td>{{ $row['account']->name }}</td><td class="num">{{ number_format($row['change'], 2) }}</td></tr>
        @endforeach
        <tr class="total-row"><td>{{ __('NET CASH FROM FINANCING ACTIVITIES') }}</td><td class="num">{{ number_format($cashFlow['financingTotal'], 2) }}</td></tr>
    </tbody>
</table>

@include('documents.print.partials.report.closing-banner', [
    'labelEn' => __('CLOSING CASH & BANK'),
    'labelAr' => 'النقدية الختامية',
    'subEn' => __('Opening :opening + net change :change', ['opening' => number_format($cashFlow['openingCash'], 2), 'change' => number_format($cashFlow['netChange'], 2)]),
    'value' => \App\Support\Money::format($cashFlow['closingCash']),
    'variant' => 'good',
])

{{-- 6. Statement of Changes in Equity --}}
<div class="page-break">
    @include('documents.print.partials.report.header', ['titleEn' => __('STATEMENT OF CHANGES IN EQUITY'), 'titleAr' => 'بيان التغيرات في حقوق الملكية', 'template' => $template ?? null])
</div>

@include('documents.print.partials.report.summary-cards', ['cards' => [
    ['label_en' => __('Opening equity'), 'label_ar' => 'حقوق الملكية الافتتاحية', 'value' => \App\Support\Money::format($equity['totalOpening']), 'variant' => 'neutral'],
    ['label_en' => __('Net income'), 'label_ar' => 'صافي الدخل', 'value' => \App\Support\Money::format($equity['netIncomeForPeriod']), 'variant' => $equity['netIncomeForPeriod'] >= 0 ? 'good' : 'bad'],
    ['label_en' => __('Closing equity'), 'label_ar' => 'حقوق الملكية الختامية', 'value' => \App\Support\Money::format($equity['totalClosing']), 'variant' => 'good'],
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
        @foreach ($equity['rows'] as $row)
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
            <td class="num">{{ number_format($equity['openingRetainedEarnings'], 2) }}</td>
            <td class="num"></td>
            <td class="num">{{ number_format($equity['netIncomeForPeriod'], 2) }}</td>
            <td class="num">{{ number_format($equity['openingRetainedEarnings'] + $equity['netIncomeForPeriod'], 2) }}</td>
        </tr>
        <tr class="total-row">
            <td>{{ __('TOTAL EQUITY') }}</td>
            <td class="num">{{ number_format($equity['totalOpening'], 2) }}</td>
            <td class="num">{{ number_format($equity['totalMovement'], 2) }}</td>
            <td class="num">{{ number_format($equity['netIncomeForPeriod'], 2) }}</td>
            <td class="num">{{ number_format($equity['totalClosing'], 2) }}</td>
        </tr>
    </tbody>
</table>

{{-- 7. Fixed Asset Schedule --}}
<div class="page-break">
    @include('documents.print.partials.report.header', ['titleEn' => __('FIXED ASSET SCHEDULE'), 'titleAr' => 'جدول الأصول الثابتة', 'template' => $template ?? null])
</div>

@include('documents.print.partials.report.summary-cards', ['cards' => [
    ['label_en' => __('Opening NBV'), 'label_ar' => 'صافي القيمة الدفترية الافتتاحية', 'value' => \App\Support\Money::format($fixedAssetTotals['openingNbv']), 'variant' => 'neutral'],
    ['label_en' => __('Depreciation'), 'label_ar' => 'الإهلاك', 'value' => \App\Support\Money::format($fixedAssetTotals['depreciation']), 'variant' => 'neutral'],
    ['label_en' => __('Closing NBV'), 'label_ar' => 'صافي القيمة الدفترية الختامية', 'value' => \App\Support\Money::format($fixedAssetTotals['closingNbv']), 'variant' => 'good'],
]])

<table class="data-table" style="margin-top: 16px;">
    <thead>
        <tr>
            <th>{{ __('Code') }}</th>
            <th>{{ __('Asset') }}</th>
            <th>{{ __('Category') }}</th>
            <th class="num">{{ __('Opening NBV') }}</th>
            <th class="num">{{ __('Additions') }}</th>
            <th class="num">{{ __('Depreciation') }}</th>
            <th class="num">{{ __('Disposals') }}</th>
            <th class="num">{{ __('Closing NBV') }}</th>
        </tr>
    </thead>
    <tbody>
        @foreach ($fixedAssetRows as $row)
            <tr>
                <td>{{ $row['asset']->asset_code }}</td>
                <td>{{ $row['asset']->name }}</td>
                <td>{{ $row['asset']->category }}</td>
                <td class="num">{{ number_format($row['openingNbv'], 2) }}</td>
                <td class="num">{{ $row['additions'] > 0 ? number_format($row['additions'], 2) : '' }}</td>
                <td class="num">{{ $row['depreciation'] > 0 ? '('.number_format($row['depreciation'], 2).')' : '' }}</td>
                <td class="num">{{ $row['disposals'] > 0 ? '('.number_format($row['disposals'], 2).')' : '' }}</td>
                <td class="num">{{ number_format($row['closingNbv'], 2) }}</td>
            </tr>
        @endforeach
        <tr class="total-row">
            <td colspan="3">{{ __('TOTAL') }}</td>
            <td class="num">{{ number_format($fixedAssetTotals['openingNbv'], 2) }}</td>
            <td class="num">{{ number_format($fixedAssetTotals['additions'], 2) }}</td>
            <td class="num">{{ $fixedAssetTotals['depreciation'] > 0 ? '('.number_format($fixedAssetTotals['depreciation'], 2).')' : number_format(0, 2) }}</td>
            <td class="num">{{ $fixedAssetTotals['disposals'] > 0 ? '('.number_format($fixedAssetTotals['disposals'], 2).')' : number_format(0, 2) }}</td>
            <td class="num">{{ number_format($fixedAssetTotals['closingNbv'], 2) }}</td>
        </tr>
    </tbody>
</table>

{{-- 8. Aged Receivables --}}
<div class="page-break">
    @include('documents.print.partials.report.header', ['titleEn' => __('AGED RECEIVABLES'), 'titleAr' => 'أعمار الذمم المدينة', 'subtitleEn' => __('As of :date', ['date' => now()->format('d M Y')]), 'template' => $template ?? null])
</div>

@include('documents.print.partials.report.summary-cards', ['cards' => [
    ['label_en' => __('Current'), 'label_ar' => 'جارٍ', 'value' => \App\Support\Money::format($receivablesTotals['current']), 'variant' => 'good'],
    ['label_en' => __('1-90 days overdue'), 'label_ar' => 'متأخر 1-90 يوماً', 'value' => \App\Support\Money::format($receivablesTotals['days_1_30'] + $receivablesTotals['days_31_60'] + $receivablesTotals['days_61_90']), 'variant' => 'warn'],
    ['label_en' => __('90+ days overdue'), 'label_ar' => 'متأخر أكثر من 90 يوماً', 'value' => \App\Support\Money::format($receivablesTotals['days_over_90']), 'variant' => 'bad'],
]])

<table class="data-table" style="margin-top: 16px;">
    <thead>
        <tr>
            <th>{{ __('Customer') }}</th>
            <th class="num">{{ __('Current') }}</th>
            <th class="num">{{ __('1-30 days') }}</th>
            <th class="num">{{ __('31-60 days') }}</th>
            <th class="num">{{ __('61-90 days') }}</th>
            <th class="num">{{ __('90+ days') }}</th>
            <th class="num">{{ __('Total') }}</th>
        </tr>
    </thead>
    <tbody>
        @foreach ($receivables as $row)
            <tr>
                <td>{{ $row['party']->name }}</td>
                <td class="num">{{ number_format($row['buckets']['current'], 2) }}</td>
                <td class="num">{{ number_format($row['buckets']['days_1_30'], 2) }}</td>
                <td class="num">{{ number_format($row['buckets']['days_31_60'], 2) }}</td>
                <td class="num">{{ number_format($row['buckets']['days_61_90'], 2) }}</td>
                <td class="num">{{ number_format($row['buckets']['days_over_90'], 2) }}</td>
                <td class="num">{{ number_format($row['total'], 2) }}</td>
            </tr>
        @endforeach
        <tr class="total-row">
            <td>{{ __('TOTAL') }}</td>
            <td class="num">{{ number_format($receivablesTotals['current'], 2) }}</td>
            <td class="num">{{ number_format($receivablesTotals['days_1_30'], 2) }}</td>
            <td class="num">{{ number_format($receivablesTotals['days_31_60'], 2) }}</td>
            <td class="num">{{ number_format($receivablesTotals['days_61_90'], 2) }}</td>
            <td class="num">{{ number_format($receivablesTotals['days_over_90'], 2) }}</td>
            <td class="num">{{ number_format($receivablesTotals['total'], 2) }}</td>
        </tr>
    </tbody>
</table>

{{-- 9. Aged Payables --}}
<div class="page-break">
    @include('documents.print.partials.report.header', ['titleEn' => __('AGED PAYABLES'), 'titleAr' => 'أعمار الذمم الدائنة', 'subtitleEn' => __('As of :date', ['date' => now()->format('d M Y')]), 'template' => $template ?? null])
</div>

@include('documents.print.partials.report.summary-cards', ['cards' => [
    ['label_en' => __('Current'), 'label_ar' => 'جارٍ', 'value' => \App\Support\Money::format($payablesTotals['current']), 'variant' => 'good'],
    ['label_en' => __('1-90 days overdue'), 'label_ar' => 'متأخر 1-90 يوماً', 'value' => \App\Support\Money::format($payablesTotals['days_1_30'] + $payablesTotals['days_31_60'] + $payablesTotals['days_61_90']), 'variant' => 'warn'],
    ['label_en' => __('90+ days overdue'), 'label_ar' => 'متأخر أكثر من 90 يوماً', 'value' => \App\Support\Money::format($payablesTotals['days_over_90']), 'variant' => 'bad'],
]])

<table class="data-table" style="margin-top: 16px;">
    <thead>
        <tr>
            <th>{{ __('Supplier') }}</th>
            <th class="num">{{ __('Current') }}</th>
            <th class="num">{{ __('1-30 days') }}</th>
            <th class="num">{{ __('31-60 days') }}</th>
            <th class="num">{{ __('61-90 days') }}</th>
            <th class="num">{{ __('90+ days') }}</th>
            <th class="num">{{ __('Total') }}</th>
        </tr>
    </thead>
    <tbody>
        @foreach ($payables as $row)
            <tr>
                <td>{{ $row['party']->name }}</td>
                <td class="num">{{ number_format($row['buckets']['current'], 2) }}</td>
                <td class="num">{{ number_format($row['buckets']['days_1_30'], 2) }}</td>
                <td class="num">{{ number_format($row['buckets']['days_31_60'], 2) }}</td>
                <td class="num">{{ number_format($row['buckets']['days_61_90'], 2) }}</td>
                <td class="num">{{ number_format($row['buckets']['days_over_90'], 2) }}</td>
                <td class="num">{{ number_format($row['total'], 2) }}</td>
            </tr>
        @endforeach
        <tr class="total-row">
            <td>{{ __('TOTAL') }}</td>
            <td class="num">{{ number_format($payablesTotals['current'], 2) }}</td>
            <td class="num">{{ number_format($payablesTotals['days_1_30'], 2) }}</td>
            <td class="num">{{ number_format($payablesTotals['days_31_60'], 2) }}</td>
            <td class="num">{{ number_format($payablesTotals['days_61_90'], 2) }}</td>
            <td class="num">{{ number_format($payablesTotals['days_over_90'], 2) }}</td>
            <td class="num">{{ number_format($payablesTotals['total'], 2) }}</td>
        </tr>
    </tbody>
</table>

@include('documents.print.partials.report.footer', ['company' => $company])

</body>
</html>
