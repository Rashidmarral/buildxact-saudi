{{--
    Navy period banner. $period: array with Carbon 'from'/'to'.
    Optional $labelEn/$labelAr override the default "STATEMENT PERIOD"
    wording (e.g. a VAT return PDF might say "TAX PERIOD").
--}}
@php
    $labelEn = $labelEn ?? __('REPORT PERIOD');
    $labelAr = $labelAr ?? 'فترة التقرير';
@endphp
<table class="period-banner">
    <tr>
        <td style="width: 60%;">{{ $labelEn }}: {{ __('FROM :from TO :to', ['from' => strtoupper($period['from']->format('d M Y')), 'to' => strtoupper($period['to']->format('d M Y'))]) }}</td>
        <td style="width: 40%;" class="ar">{{ $labelAr }}: من {{ $period['from']->format('d M Y') }} إلى {{ $period['to']->format('d M Y') }}</td>
    </tr>
</table>
