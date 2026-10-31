{{--
    "make a report something like this 110800 cash we have paid to
    shahid shows that then subbase we have given them on 73000 same
    asphalt transportation something like this" — the same payments
    already linked into this subcontract, regrouped by whatever cost
    category the operator typed in on the show page (who got paid /
    what the money was for), each with its own subtotal and a grand
    total that always still equals the contract's own net applied total.
--}}
@php
    $logoData = $embed($company->logo_path ?? null);
    $grandTotal = round(collect($groups)->sum('total'), 2);
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
    'titleEn' => __('Payment Breakdown by Category'), 'titleAr' => 'تفصيل الدفعات حسب الفئة',
    'subtitleEn' => $subcontract->project->name.' — '.$subcontract->supplier->name,
    'subtitleAr' => '',
    'template' => $template ?? null,
])
@include('documents.print.partials.report.footer-banner', ['template' => $template ?? null])

<table class="data-table">
    <thead>
        <tr>
            <th>{{ __('Category') }}</th>
            <th>{{ __('Items') }}</th>
            <th class="num">{{ __('Total') }}</th>
        </tr>
    </thead>
    <tbody>
        @forelse ($groups as $group)
            <tr>
                <td>{{ $group['label'] }}</td>
                <td>{{ trans_choice(':count payment|:count payments', $group['payments']->count(), ['count' => $group['payments']->count()]) }}</td>
                <td class="num">{{ number_format($group['total'], 2) }}</td>
            </tr>
        @empty
            <tr><td colspan="3" style="text-align: center; color: #94a3b8;">{{ __('No categories defined.') }}</td></tr>
        @endforelse
        <tr class="total-row">
            <td colspan="2">{{ __('GRAND TOTAL') }}</td>
            <td class="num">{{ number_format($grandTotal, 2) }}</td>
        </tr>
    </tbody>
</table>

@foreach ($groups as $group)
    @if ($group['payments']->isNotEmpty())
        @include('documents.print.partials.report.section-title', ['en' => strtoupper($group['label']), 'ar' => ''])
        <table class="data-table">
            <thead>
                <tr>
                    <th>{{ __('Date') }}</th>
                    <th>{{ __('Reference') }}</th>
                    <th class="num">{{ __('Amount') }}</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($group['payments'] as $payment)
                    <tr>
                        <td>{{ $payment->paid_at->format('Y-m-d') }}</td>
                        <td>{{ $payment->reference ?: ($payment->paymentVoucher->reference ?? '—') }}</td>
                        <td class="num">{{ number_format((float) $payment->amount, 2) }}</td>
                    </tr>
                @endforeach
                <tr class="total-row">
                    <td colspan="2">{{ __('Subtotal') }}</td>
                    <td class="num">{{ number_format($group['total'], 2) }}</td>
                </tr>
            </tbody>
        </table>
    @endif
@endforeach

@include('documents.print.partials.report.footer', ['company' => $company])

</body>
</html>
