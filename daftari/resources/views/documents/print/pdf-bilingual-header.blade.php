{{--
    mPDF-safe version of documents.print.bilingual-header: English info
    left, logo centered, Arabic info right — built as a 3-column table
    instead of flex. Shared by the bilingual_classic layout and the
    custom_letterhead layout's no-letterhead-uploaded-yet fallback.
--}}
<table>
    <tr>
        <td style="width: 38%; vertical-align: top; font-size: 9.5pt; line-height: 1.5;">
            <div style="font-size: 11pt; font-weight: bold; color: #0f172a;">{{ $company->name }}</div>
            @if ($company->address)<div>{{ $company->address }}</div>@endif
            @if ($company->city)<div>{{ $company->city }}</div>@endif
            <div>{{ __('Kingdom of Saudi Arabia') }}</div>
            @if ($company->email)<div>{{ $company->email }}</div>@endif
            @if ($company->phone)<div>{{ $company->phone }}</div>@endif
            @if ($company->vat_number)<div>{{ __('VAT number') }} {{ $company->vat_number }}</div>@endif
            @if ($company->cr_number)<div>{{ __('CR Number') }} {{ $company->cr_number }}</div>@endif
        </td>
        <td style="width: 24%; vertical-align: top; text-align: center;">
            @if (($showLogo ?? true) && $logoData)
                <img src="{{ $logoData }}" style="width: 70px; height: 70px;" alt="">
            @endif
        </td>
        <td class="ar" style="width: 38%; vertical-align: top; font-size: 9.5pt; line-height: 1.5;">
            @if ($company->name_ar)<div style="font-size: 11pt; font-weight: bold; color: #0f172a;">{{ $company->name_ar }}</div>@endif
            @if ($company->address)<div>{{ $company->address }}</div>@endif
            @if ($company->city)<div>{{ $company->city }}</div>@endif
            <div>المملكة العربية السعودية</div>
            @if ($company->email)<div>{{ $company->email }}</div>@endif
            @if ($company->phone)<div>{{ $company->phone }}</div>@endif
            @if ($company->vat_number)<div>رقم التسجيل الضريبي {{ $company->vat_number }}</div>@endif
            @if ($company->cr_number)<div>رقم السجل التجاري {{ $company->cr_number }}</div>@endif
        </td>
    </tr>
</table>
