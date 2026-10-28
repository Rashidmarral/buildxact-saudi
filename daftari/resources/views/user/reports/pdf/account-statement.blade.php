{{--
    Account Statement PDF (customer or supplier) — rebuilt on the
    shared, bilingual, branded report-PDF design. Replaces the old
    separate monolingual pdf-ar/pdf-en exports: the shared design
    already shows both languages side by side, so one PDF covers both.

    A company's own Invoice Templates design (document_type =
    'account_statement') can swap the standard navy header band for a
    full-bleed letterhead image, and/or add a footer banner image —
    the exact same mechanism letter-pdf.blade.php and
    client-statement-pdf.blade.php already offer, registered as mPDF's
    own repeating <htmlpageheader>/<htmlpagefooter> so either image
    repeats on every page a long statement spans, not just the first.
--}}
@php
    $logoData = $embed($company->logo_path ?? null);
    $isCustomer = $type === 'customer';
    $letterheadData = $embed($template->letterhead_path ?? null);
    $footerData = $embed($template->footer_path ?? null);
    $layout = $template->layout ?? null;
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

@if ($layout === 'custom_letterhead' && $letterheadData)
    <htmlpageheader name="statement-letterhead">
        <img src="{{ $letterheadData }}" style="width: 100%;" alt="">
    </htmlpageheader>
    <sethtmlpageheader name="statement-letterhead" page="ALL" show-this-page="1" />
@else
    @include('documents.print.partials.report.header', ['titleEn' => __('ACCOUNT STATEMENT'), 'titleAr' => 'كشف حساب'])
@endif

@if ($footerData)
    <htmlpagefooter name="statement-footer">
        <img src="{{ $footerData }}" style="width: 100%;" alt="">
    </htmlpagefooter>
    <sethtmlpagefooter name="statement-footer" page="ALL" />
@endif

<table class="party-box">
    <tr>
        <td style="width: 60%;">
            <div class="k">{{ $isCustomer ? __('Customer') : __('Supplier') }}</div>
            <div class="v">{{ $party->display_name ?? $party->name }}</div>
            @if ($party->vat_number)<div class="muted">{{ __('VAT No.') }} {{ $party->vat_number }}</div>@endif
        </td>
        <td style="width: 40%;" class="ar">
            <div class="k">{{ $isCustomer ? 'العميل' : 'المورد' }}</div>
            @if ($party->name_ar)<div class="v">{{ $party->name_ar }}</div>@endif
        </td>
    </tr>
</table>

@include('documents.print.partials.report.period-banner', ['period' => $period])

@include('documents.print.partials.report.section-title', ['en' => __('TRANSACTION LEDGER'), 'ar' => 'سجل الحركات'])
<table class="data-table">
    <thead>
        <tr>
            <th>{{ __('Date') }}</th>
            <th>{{ __('Description') }}</th>
            <th class="num">{{ __('Debit') }}</th>
            <th class="num">{{ __('Credit') }}</th>
            <th class="num">{{ __('Balance') }}</th>
        </tr>
    </thead>
    <tbody>
        <tr>
            <td colspan="4" class="muted" style="font-style: italic;">{{ __('Opening balance') }}</td>
            <td class="num" style="font-weight: bold;">{{ number_format($openingBalance, 2) }}</td>
        </tr>
        @foreach ($lines as $line)
            <tr>
                <td>{{ $line->date->format('Y-m-d') }}</td>
                <td>{{ $line->description }}</td>
                <td class="num">{{ $line->debit > 0 ? number_format($line->debit, 2) : '' }}</td>
                <td class="num">{{ $line->credit > 0 ? number_format($line->credit, 2) : '' }}</td>
                <td class="num" style="font-weight: bold;">{{ number_format($line->balance, 2) }}</td>
            </tr>
        @endforeach
    </tbody>
</table>

@include('documents.print.partials.report.closing-banner', [
    'labelEn' => __('CLOSING BALANCE'),
    'labelAr' => 'الرصيد الختامي',
    'value' => \App\Support\Money::format($closingBalance),
    'variant' => $closingBalance >= 0 ? 'warn' : 'good',
])

@include('documents.print.partials.report.footer', ['company' => $company])

</body>
</html>
