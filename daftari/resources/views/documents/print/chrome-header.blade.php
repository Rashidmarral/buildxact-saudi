{{--
    Lightweight header chrome shared by documents that don't have a
    line-item table of their own (Receipt/Payment Vouchers) — same logo,
    bilingual company info and accent styling as the full templates, without
    the parts (item table, bank/VAT panel) that only make sense for
    line-item documents.
--}}
@php
    $accent = $template->accent_color ?? '#0f766e';
    $layout = $template->layout ?? 'minimal';
    $showLogo = $template->show_logo ?? true;
@endphp

@if ($layout === 'custom_letterhead' && $template?->letterhead_path)
    <img src="{{ Storage::url($template->letterhead_path) }}" alt="{{ $company->name }}" class="w-full object-contain mb-6">
@elseif ($layout === 'bilingual_classic')
    <div class="border-2 rounded-lg mb-6 h-3" style="border-color: {{ $accent }}"></div>
    <div class="flex items-center justify-between gap-6 pb-4 border-b-2 border-slate-800">
        <div class="text-sm leading-relaxed text-slate-700">
            <h1 class="text-base font-bold text-slate-900">{{ $company->name }}</h1>
            @if ($company->vat_number)<p>{{ __('VAT number') }} {{ $company->vat_number }}</p>@endif
        </div>
        @if ($showLogo && $company->logo_path)
            <img src="{{ Storage::url($company->logo_path) }}" alt="{{ $company->name }}" class="h-16 w-16 shrink-0 rounded-full object-cover">
        @endif
        @if ($company->name_ar)
            <div class="text-sm leading-relaxed text-slate-700 text-end" dir="rtl">
                <h1 class="text-base font-bold text-slate-900">{{ $company->name_ar }}</h1>
                @if ($company->vat_number)<p>رقم التسجيل الضريبي {{ $company->vat_number }}</p>@endif
            </div>
        @endif
    </div>
@else
    <div class="flex items-center justify-between pb-4 border-b {{ $layout === 'bordered' ? 'border-2' : 'border-slate-100' }}" @if ($layout === 'bordered') style="border-color: {{ $accent }}" @endif>
        <div class="flex items-center gap-3">
            @if ($showLogo && $company->logo_path)
                <img src="{{ Storage::url($company->logo_path) }}" alt="{{ $company->name }}" class="h-12 w-12 rounded-lg object-cover border border-slate-100">
            @endif
            <div>
                <h1 class="text-xl font-bold text-slate-900">{{ $company->name }}</h1>
                @if ($company->vat_number)<p class="text-sm text-slate-500">{{ __('VAT') }}: {{ $company->vat_number }}</p>@endif
            </div>
        </div>
    </div>
@endif
