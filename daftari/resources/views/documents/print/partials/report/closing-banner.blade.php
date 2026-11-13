{{--
    Highlighted closing figure banner. $labelEn/$labelAr, $value
    (formatted string), $subEn/$subAr (optional detail line),
    $variant: 'warn' (amber, default — e.g. amount due) or 'good' (teal
    — e.g. a credit/refund position).
--}}
@php($variant = $variant ?? 'warn')
<table class="closing-banner {{ $variant === 'good' ? 'variant-good' : '' }}">
    <tr>
        <td style="width: 60%;">
            <div class="k">{{ $labelEn }}</div>
            @if (! empty($subEn))<div class="sub">{{ $subEn }}</div>@endif
        </td>
        <td style="width: 40%; text-align: right;">
            <div class="v">{{ $value }}</div>
            @if (! empty($labelAr))<div class="sub ar" style="margin-top: 2px;">{{ $labelAr }}</div>@endif
        </td>
    </tr>
</table>
