{{--
    Dual-language company header — details left in English, right in
    Arabic, logo centered — built entirely from company data, no uploaded
    image required. Shared by the "Bilingual Classic" layout and used as
    the "Custom Letterhead" layout's fallback when no letterhead image has
    been uploaded yet.
--}}
<div class="flex items-start justify-between gap-6">
    <div class="text-sm leading-relaxed text-slate-700">
        <h1 class="text-base font-bold text-slate-900">{{ $company->name }}</h1>
        @if ($company->address)<p>{{ $company->address }}</p>@endif
        @if ($company->city)<p>{{ $company->city }}</p>@endif
        <p>{{ __('Kingdom of Saudi Arabia') }}</p>
        @if ($company->email)<p>{{ $company->email }}</p>@endif
        @if ($company->phone)<p>{{ $company->phone }}</p>@endif
        @if ($company->vat_number)<p>{{ __('VAT number') }} {{ $company->vat_number }}</p>@endif
        @if ($company->cr_number)<p>{{ __('CR Number') }} {{ $company->cr_number }}</p>@endif
    </div>

    @if (($showLogo ?? true) && $company->logo_path)
        <img src="{{ Storage::url($company->logo_path) }}" alt="{{ $company->name }}" class="h-24 w-24 shrink-0 rounded-full object-cover">
    @endif

    <div class="text-sm leading-relaxed text-slate-700 text-end" dir="rtl">
        @if ($company->name_ar)<h1 class="text-base font-bold text-slate-900">{{ $company->name_ar }}</h1>@endif
        @if ($company->address)<p>{{ $company->address }}</p>@endif
        @if ($company->city)<p>{{ $company->city }}</p>@endif
        <p>المملكة العربية السعودية</p>
        @if ($company->email)<p>{{ $company->email }}</p>@endif
        @if ($company->phone)<p>{{ $company->phone }}</p>@endif
        @if ($company->vat_number)<p>رقم التسجيل الضريبي {{ $company->vat_number }}</p>@endif
        @if ($company->cr_number)<p>رقم السجل التجاري {{ $company->cr_number }}</p>@endif
    </div>
</div>
