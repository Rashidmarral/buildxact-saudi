{{--
    Dedicated POS/Restaurant sale receipt PDF — deliberately NOT part of
    the shared documents.print.pdf layout system (see InvoiceTemplate): a
    receipt is a narrow, thermal-slip-shaped document, structurally
    nothing like an A4 invoice, so it gets its own small template family
    ('receipt_compact' / 'receipt_detailed' — see ReceiptTemplatePresets)
    instead. Rendered at an 80mm-wide page (see MpdfRenderer::resolvePageSize())
    rather than A4/Letter.

    Mirrors documents.print.pos-receipt-body (the on-screen version,
    rendered with Tailwind since a real browser draws that one) — same
    content and language-mode handling, rebuilt with plain table/div CSS
    mPDF can render.
--}}
@php
    $languageMode = $languageMode ?? 'bilingual';
    // Every label goes through this one closure so bilingual mode is
    // consistent everywhere (English / Arabic on one line) rather than
    // only some rows getting a second language.
    $lbl = function (string $key) use ($languageMode) {
        $en = \Illuminate\Support\Facades\Lang::get($key, [], 'en');
        $ar = \Illuminate\Support\Facades\Lang::get($key, [], 'ar');

        return match ($languageMode) {
            'english_only' => $en,
            'arabic_only' => $ar,
            default => $en.' / '.$ar,
        };
    };
    $isDetailed = ($layout ?? 'receipt_compact') === 'receipt_detailed';
    $company = $sale->company;
    $logoData = $isDetailed ? $embed($company->logo_path ?? null) : null;
@endphp
<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<style>
    body { font-family: cairo, sans-serif; color: #1e293b; font-size: 8.5pt; }
    table { border-collapse: collapse; width: 100%; }
    .center { text-align: center; }
    .end { text-align: right; }
    .muted { color: #64748b; font-size: 7.5pt; }
    .faint { color: #94a3b8; font-size: 6.5pt; }
    .bold { font-weight: bold; }
    .divider { border-top: 1px dashed #94a3b8; margin: 6px 0; }
    .row td { padding: 1px 0; vertical-align: top; }
    .total-row td { padding-top: 4px; border-top: 1px dashed #94a3b8; font-size: 10pt; font-weight: bold; }
</style>
</head>
<body>
    @if ($logoData)
        <div class="center"><img src="{{ $logoData }}" style="height: 32px;"></div>
    @endif
    <div class="center bold" style="font-size: 10pt;">{{ $company->name }}</div>
    @if ($isDetailed)
        @if ($company->address)<div class="center muted">{{ $company->address }}</div>@endif
        @if ($company->phone)<div class="center muted">{{ $company->phone }}</div>@endif
    @endif
    @if ($company->vat_number)
        <div class="center muted">{{ $lbl('VAT') }}: {{ $company->vat_number }}</div>
    @endif
    <div class="center muted">{{ $sale->register->name }}</div>
    <div class="center muted">{{ $sale->created_at->format('Y-m-d H:i') }}</div>
    <div class="center muted">{{ $lbl('Receipt') }}: {{ $sale->sale_number }}</div>
    @if ($sale->client)
        <div class="center muted">{{ $lbl('Client') }}: {{ $sale->client->name }}</div>
    @endif

    <div class="divider"></div>

    <table>
        @foreach ($sale->items as $line)
            <tr class="row">
                <td colspan="2">{{ $line->item->name ?? $line->description }}</td>
            </tr>
            <tr class="row muted">
                <td>{{ rtrim(rtrim(number_format($line->quantity, 3), '0'), '.') }} &times; {{ number_format($line->unit_price, 2) }}</td>
                <td class="end">{{ number_format($line->line_total, 2) }}</td>
            </tr>
            @if ($isDetailed && (float) $line->vat_rate > 0)
                <tr class="row faint">
                    <td>{{ $lbl('VAT') }} {{ rtrim(rtrim(number_format($line->vat_rate, 2), '0'), '.') }}%</td>
                    <td class="end">{{ number_format($line->vat_amount, 2) }}</td>
                </tr>
            @endif
        @endforeach
    </table>

    <div class="divider"></div>

    <table>
        <tr class="row"><td>{{ $lbl('Subtotal') }}</td><td class="end">{{ number_format($sale->subtotal, 2) }}</td></tr>
        @if ($sale->discount_total > 0)
            <tr class="row"><td>{{ $lbl('Discount') }}</td><td class="end">-{{ number_format($sale->discount_total, 2) }}</td></tr>
        @endif
        <tr class="row"><td>{{ $lbl('VAT') }}</td><td class="end">{{ number_format($sale->vat_total, 2) }}</td></tr>
        <tr class="total-row"><td>{{ $lbl('Total') }}</td><td class="end">{{ number_format($sale->total, 2) }}</td></tr>
    </table>

    <div class="divider"></div>

    <div class="muted bold" style="text-transform: uppercase;">{{ $lbl('Payments') }}</div>
    <table>
        @foreach ($sale->payments as $payment)
            <tr class="row">
                <td>{{ __(ucfirst($payment->method)) }}</td>
                <td class="end">{{ number_format($payment->amount, 2) }}</td>
            </tr>
        @endforeach
    </table>

    <div class="center" style="margin-top: 12px;">
        <img src="data:image/png;base64,{{ $qr }}" style="height: 90px; width: 90px;">
        <div class="faint" style="margin-top: 2px;">{{ $lbl('Scan to verify sale details') }}</div>
        @if ($isDetailed)
            <div class="faint" style="margin-top: 4px;">{{ $lbl('Thank you for your business.') }}</div>
        @endif
    </div>
</body>
</html>
