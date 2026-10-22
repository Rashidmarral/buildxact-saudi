{{--
    Navy header band: logo + company name + bilingual report title.
    $titleEn/$titleAr required; $subtitleEn/$subtitleAr optional (e.g.
    "Q3 2026" or a tab label).
--}}
@php
    $subtitleEn = $subtitleEn ?? null;
    $subtitleAr = $subtitleAr ?? null;
@endphp
<table class="header-band">
    <tr>
        <td style="width: 60%;">
            @if ($logoData ?? null)<img src="{{ $logoData }}" style="height: 26px; margin-bottom: 6px;" alt="">@endif
            <div class="header-company">{{ $company->name }}</div>
            <div class="header-title">{{ $titleEn }}</div>
            @if ($subtitleEn)<div class="header-sub">{{ strtoupper($subtitleEn) }}</div>@endif
        </td>
        <td style="width: 40%;" class="ar">
            @if ($company->name_ar)<div class="header-company">{{ $company->name_ar }}</div>@endif
            <div class="header-title">{{ $titleAr }}</div>
            @if ($subtitleAr)<div class="header-sub ar">{{ $subtitleAr }}</div>@endif
        </td>
    </tr>
</table>
