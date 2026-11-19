{{--
    "Receiving Payments Report" — meant to be printed and physically
    signed by the subcontractor on site, showing what they've received
    to date and what remains. Mirrors the uploaded reference sheet's
    calculation (contract value → payments → balance before retention →
    retention held → balance payable now after retention) while also
    showing the milestone-wise schedule (20/30/30/15/5%-style) the
    payments are tracked against.
--}}
@php
    // "you have copy the subcontractor pdf details please update it to
    // project manager milestone and 5% retention not coming in pdf" —
    // this template used to be hardcoded with subcontractor wording
    // (and an always-rendered milestone/retention schedule) regardless
    // of which module rendered it; a Project Manager Budget is the same
    // underlying Subcontract record with party_type=employee, so it was
    // printing "Subcontractor", a milestone table that's always empty
    // (PM budgets never sync milestones), and a 0%-retention banner
    // that's never meaningful for cash advanced to your own staff —
    // matching the Subcontractor Payments vs Project Manager Budgets
    // split everywhere else in the app (nav, show page, PDF button
    // label), not just here.
    $isPm = $subcontract->isEmployeeParty();
    $logoData = $embed($company->logo_path ?? null);
    $totalPaid = $subcontract->totalPaid();
    $totalGross = $subcontract->totalReceivedGross();
    $balanceBeforeRetention = $subcontract->balanceBeforeRetention();
    $retentionAmount = $subcontract->retentionAmount();
    $balanceAfterRetention = $subcontract->balanceAfterRetention();
@endphp
<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<style>
@include('documents.print.partials.report.styles')
.sign-table { width: 100%; margin-top: 28px; border-collapse: collapse; }
.sign-table td { width: 50%; vertical-align: top; padding-top: 24px; }
.sign-line { border-top: 1px solid #94a3b8; margin-top: 36px; padding-top: 4px; font-size: 10px; color: #475569; }
</style>
</head>
<body>

@include('documents.print.partials.report.header', [
    'titleEn' => $isPm ? __('Budget Report') : __('Receiving Payments Report'), 'titleAr' => $isPm ? 'تقرير الميزانية' : 'تقرير استلام الدفعات',
    'subtitleEn' => $subcontract->project->name.' — '.$subcontract->partyName(),
    'subtitleAr' => '',
    'template' => $template ?? null,
])
@include('documents.print.partials.report.footer-banner', ['template' => $template ?? null])

<table class="summary-table" style="margin-top: 4px;">
    <tr>
        <td style="width: 50%;">
            <div class="k">{{ __('Project') }}</div>
            <div class="v" style="font-size: 12px;">{{ $subcontract->project->name }}</div>
        </td>
        <td style="width: 50%;">
            <div class="k">{{ $isPm ? __('Project manager') : __('Subcontractor') }}</div>
            <div class="v" style="font-size: 12px;">{{ $subcontract->partyName() }}</div>
        </td>
    </tr>
    <tr>
        <td><div class="k">{{ $isPm ? __('Reference') : __('Contract No.') }}</div><div class="v" style="font-size: 12px;">{{ $subcontract->contract_number ?: '—' }}</div></td>
        <td><div class="k">{{ __('Report date') }}</div><div class="v" style="font-size: 12px;">{{ \App\Support\PlatformFormat::date(now()) }}</div></td>
    </tr>
</table>

@if ($subcontract->boqItems->isNotEmpty())
    @include('documents.print.partials.report.section-title', ['en' => $isPm ? __('BUDGET BREAKDOWN') : __('CONTRACT BREAKDOWN (BOQ)'), 'ar' => $isPm ? 'تفصيل الميزانية' : 'تفصيل جدول الكميات'])
    <table class="data-table">
        <thead>
            <tr>
                <th>{{ __('Description') }}</th>
                <th class="num">{{ __('Quantity') }}</th>
                <th>{{ __('Unit') }}</th>
                <th class="num">{{ __('Rate') }}</th>
                <th class="num">{{ __('Amount') }}</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($subcontract->boqItems as $item)
                <tr>
                    <td>{{ $item->description }}</td>
                    <td class="num">{{ $item->quantity !== null ? number_format((float) $item->quantity, 2) : '—' }}</td>
                    <td>{{ $item->unit ?: '—' }}</td>
                    <td class="num">{{ $item->rate !== null ? number_format((float) $item->rate, 2) : '—' }}</td>
                    <td class="num">{{ number_format((float) $item->amount, 2) }}</td>
                </tr>
            @endforeach
            <tr class="total-row">
                <td colspan="4">{{ $isPm ? __('TOTAL BUDGET') : __('TOTAL CONTRACT VALUE') }}</td>
                <td class="num">{{ number_format((float) $subcontract->contract_value, 2) }}</td>
            </tr>
        </tbody>
    </table>
@endif

{{--
    "do you now want a 5% retention and a milestone-style schedule to
    appear for the Project Manager Budget too... Yes" — a Project
    Manager Budget now gets the exact same milestone payment schedule
    as a Subcontract, shown unconditionally below (not gated on
    $isPm); the Works/Sub-projects list is an additional, PM-specific
    breakdown shown alongside it whenever the budget tracks more than
    one piece of work.
--}}
@include('documents.print.partials.report.section-title', ['en' => __('PAYMENT SCHEDULE'), 'ar' => 'جدول الدفعات'])
<table class="data-table">
    <thead>
        <tr>
            <th>{{ __('Milestone') }}</th>
            <th class="num">{{ __('%') }}</th>
            <th class="num">{{ __('Amount') }}</th>
            <th class="num">{{ __('Received') }}</th>
            <th class="num">{{ __('Balance') }}</th>
        </tr>
    </thead>
    <tbody>
        @foreach ($subcontract->milestones as $milestone)
            <tr>
                <td>
                    {{ $milestone->name }}
                    @if ($milestone->requires_handover_approval)
                        <span style="font-size: 9px; color: #94a3b8;">({{ $milestone->approved_at ? __('approved') : __('pending handover approval') }})</span>
                    @endif
                </td>
                <td class="num">{{ number_format((float) $milestone->percentage, 2) }}%</td>
                <td class="num">{{ number_format((float) $milestone->amount, 2) }}</td>
                <td class="num">{{ number_format($milestone->paidAmount(), 2) }}</td>
                <td class="num">{{ number_format($milestone->balance(), 2) }}</td>
            </tr>
        @endforeach
    </tbody>
</table>

@if ($subcontract->works->isNotEmpty())
    @include('documents.print.partials.report.section-title', ['en' => __('WORKS / SUB-PROJECTS'), 'ar' => 'الأعمال / المشاريع الفرعية'])
    <table class="data-table">
        <thead>
            <tr>
                <th>{{ __('Work') }}</th>
                <th class="num">{{ __('Budget') }}</th>
                <th class="num">{{ __('Paid') }}</th>
                <th class="num">{{ __('Balance') }}</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($subcontract->works as $work)
                <tr>
                    <td>{{ $work->name }}</td>
                    <td class="num">{{ $work->budget_amount !== null ? number_format((float) $work->budget_amount, 2) : '—' }}</td>
                    <td class="num">{{ number_format($work->paidAmount(), 2) }}</td>
                    <td class="num">{{ $work->balance() !== null ? number_format($work->balance(), 2) : '—' }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
@endif

@if ($reportCategoryRows ?? null)
    {{--
        "shahid payment vouchers 124800 shows detail wise in report
        other alhazera and ashraf payment shows as Sub Base 146.00 trip
        500.00 unit price 73,000.00 total" — an itemized category still
        lists every real payment underneath it (date/reference/amount);
        a measured category collapses to one quantity × rate summary
        line, like a site measurement sheet rather than a voucher list.
    --}}
    @include('documents.print.partials.report.section-title', ['en' => __('PAYMENTS RECEIVED TO DATE — BY CATEGORY'), 'ar' => 'الدفعات المستلمة حتى الآن — حسب الفئة'])
    <table class="data-table">
        <thead>
            <tr>
                <th>{{ __('Category') }}</th>
                <th class="num">{{ __('Quantity') }}</th>
                <th>{{ __('Unit') }}</th>
                <th class="num">{{ __('Rate') }}</th>
                <th class="num">{{ __('Amount') }}</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($reportCategoryRows as $row)
                <tr>
                    <td>{{ $row['category']->label }}</td>
                    <td class="num">{{ $row['category']->quantity !== null ? number_format((float) $row['category']->quantity, 2) : '—' }}</td>
                    <td>{{ $row['category']->unit ?: '—' }}</td>
                    <td class="num">{{ $row['category']->rate !== null ? number_format((float) $row['category']->rate, 2) : '—' }}</td>
                    <td class="num">{{ number_format($row['total'], 2) }}</td>
                </tr>
            @endforeach
            <tr class="total-row">
                <td colspan="4">{{ $isPm ? __('TOTAL APPLIED TO BUDGET') : __('TOTAL APPLIED TO CONTRACT') }}</td>
                <td class="num">{{ number_format($totalPaid, 2) }}</td>
            </tr>
        </tbody>
    </table>
    {{--
        "subbase material 73000 and asphalt 107352.20... these payment
        should adjust in milestone" — a Measured category's amount is a
        site-measurement valuation (qty × rate), not a second payment on
        top of the itemized rows above it: the real cash is counted once,
        under the category whose match keywords claimed it (or under the
        catch-all), and the row below always foots to that same real
        total — never the sum of each row's own printed amount, since a
        Measured row's figure is deliberately independent of what it
        matched.
    --}}
    <p style="font-size: 9px; color: #94a3b8; margin-top: 4px;">
        {{ __('Each payment is counted under one category only. A "Measured" row shows a site-measurement value (quantity × rate) for reference — not an additional amount on top of the others — so the categories\' own printed amounts do not need to add up to the total above.') }}
    </p>

    @foreach ($reportCategoryRows as $row)
        @if ($row['category']->display_mode === \App\Models\SubcontractReportCategory::ITEMIZED && $row['payments']->isNotEmpty())
            @include('documents.print.partials.report.section-title', ['en' => strtoupper($row['category']->label), 'ar' => ''])
            <table class="data-table">
                <thead>
                    <tr>
                        <th>{{ __('Date') }}</th>
                        <th>{{ __('Reference') }}</th>
                        <th class="num">{{ __('Applied (net)') }}</th>
                        <th class="num">{{ __('Cash (incl. VAT)') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($row['payments'] as $payment)
                        <tr>
                            <td>{{ $payment->paid_at->format('Y-m-d') }}</td>
                            <td>{{ $payment->reference ?: ($payment->paymentVoucher->voucher_number ?? '—') }}</td>
                            <td class="num">{{ number_format((float) $payment->amount, 2) }}</td>
                            <td class="num">{{ $payment->gross_amount !== null && abs((float) $payment->gross_amount - (float) $payment->amount) > 0.004 ? number_format((float) $payment->gross_amount, 2) : '—' }}</td>
                        </tr>
                    @endforeach
                    <tr class="total-row">
                        <td colspan="2">{{ __('Subtotal') }}</td>
                        <td class="num">{{ number_format($row['total'], 2) }}</td>
                        <td class="num"></td>
                    </tr>
                </tbody>
            </table>
        @endif
    @endforeach
@else
    @include('documents.print.partials.report.section-title', ['en' => __('PAYMENTS RECEIVED TO DATE'), 'ar' => 'الدفعات المستلمة حتى الآن'])
    <table class="data-table">
        <thead>
            <tr>
                <th>{{ __('Date') }}</th>
                <th>{{ __('Milestone') }}</th>
                <th>{{ __('Reference') }}</th>
                <th class="num">{{ __('Applied (net)') }}</th>
                <th class="num">{{ __('Cash (incl. VAT)') }}</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($subcontract->payments->sortBy('paid_at') as $payment)
                <tr>
                    <td>{{ $payment->paid_at->format('Y-m-d') }}</td>
                    <td>{{ $payment->milestone->name ?? __('Unassigned') }}</td>
                    <td>{{ $payment->reference ?: ($payment->paymentVoucher->voucher_number ?? '—') }}</td>
                    <td class="num">{{ number_format((float) $payment->amount, 2) }}</td>
                    <td class="num">{{ $payment->gross_amount !== null && abs((float) $payment->gross_amount - (float) $payment->amount) > 0.004 ? number_format((float) $payment->gross_amount, 2) : '—' }}</td>
                </tr>
            @empty
                <tr><td colspan="5" style="text-align: center; color: #94a3b8;">{{ __('No payments recorded yet.') }}</td></tr>
            @endforelse
            <tr class="total-row">
                <td colspan="3">{{ __('TOTAL') }}</td>
                <td class="num">{{ number_format($totalPaid, 2) }}</td>
                <td class="num">{{ abs($totalGross - $totalPaid) > 0.004 ? number_format($totalGross, 2) : '—' }}</td>
            </tr>
        </tbody>
    </table>
@endif

<table class="summary-table" style="margin-top: 14px;">
    <tr>
        <td style="width: 25%;">
            <div class="k">{{ $isPm ? __('Total budget') : __('Total contract value') }}</div>
            <div class="v v-neutral">{{ number_format((float) $subcontract->contract_value, 2) }}</div>
        </td>
        <td style="width: 25%;">
            <div class="k">{{ $isPm ? __('Applied to budget (net)') : __('Applied to contract (net)') }}</div>
            <div class="v v-good">{{ number_format($totalPaid, 2) }}</div>
        </td>
        <td style="width: 25%;">
            <div class="k">{{ __('Cash received (incl. VAT)') }}</div>
            <div class="v v-neutral">{{ number_format($totalGross, 2) }}</div>
        </td>
        <td style="width: 25%;">
            <div class="k">{{ __('Balance before retention') }}</div>
            <div class="v v-neutral">{{ number_format($balanceBeforeRetention, 2) }}</div>
        </td>
    </tr>
</table>

{{--
    "do you now want a 5% retention and a milestone-style schedule to
    appear for the Project Manager Budget too... Yes" — both retention
    banners now render unconditionally; a Project Manager Budget's own
    retention_percent (set on its own form, same as a Subcontract's)
    drives these exactly the same way.
--}}
@include('documents.print.partials.report.closing-banner', [
    'labelEn' => __('Retention withheld (:pct% of contract value)', ['pct' => number_format((float) $subcontract->retention_percent, 2)]),
    'labelAr' => 'الدفعة المحتجزة',
    'value' => number_format($retentionAmount, 2),
    'variant' => 'warn',
])

@include('documents.print.partials.report.closing-banner', [
    'labelEn' => __('Balance payable now after retention'),
    'labelAr' => 'المتبقي المستحق الدفع بعد خصم الدفعة المحتجزة',
    'value' => number_format($balanceAfterRetention, 2),
    'variant' => $balanceAfterRetention > 0 ? 'warn' : 'good',
])

<table class="sign-table">
    <tr>
        <td>
            <div class="sign-line">
                {{ $isPm ? __('Project manager signature & date') : __('Subcontractor signature & date') }}<br>
                <span style="font-size: 9px;">{{ $subcontract->partyName() }}</span>
            </div>
        </td>
        <td>
            <div class="sign-line">
                {{ __('Company representative signature & date') }}<br>
                <span style="font-size: 9px;">{{ $company->name }}</span>
            </div>
        </td>
    </tr>
</table>
<p style="font-size: 9px; color: #94a3b8; margin-top: 10px;">
    @if ($isPm)
        {{ __('By signing above, the project manager acknowledges having received a total of :total in cash to date, :net of which is applied against this budget, and agrees the remaining balance due is :balance (before release of the retention held per the terms above).', [
            'total' => number_format($totalGross, 2),
            'net' => number_format($totalPaid, 2),
            'balance' => number_format($balanceBeforeRetention, 2),
        ]) }}
    @else
        {{ __('By signing above, the subcontractor acknowledges having received a total of :total in cash to date, :net of which is applied against this contract, and agrees the remaining balance due is :balance (before release of the retention held per the terms above).', [
            'total' => number_format($totalGross, 2),
            'net' => number_format($totalPaid, 2),
            'balance' => number_format($balanceBeforeRetention, 2),
        ]) }}
    @endif
</p>

@include('documents.print.partials.report.footer', ['company' => $company])

</body>
</html>
