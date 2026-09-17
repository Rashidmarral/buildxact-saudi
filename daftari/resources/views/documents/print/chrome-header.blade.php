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
@elseif ($layout === 'bilingual_classic' || $layout === 'custom_letterhead')
    <div class="border-2 rounded-lg mb-6 h-3" style="border-color: {{ $accent }}"></div>
    @include('documents.print.bilingual-header', ['company' => $company, 'showLogo' => $showLogo])
    <div class="border-b-2 border-slate-800 mt-4"></div>
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
