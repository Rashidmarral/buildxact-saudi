@extends('layouts.app')

@section('title', __('Invoice Templates'))

@php
    $typeLabels = [
        'all' => __('All types'),
        'invoice' => __('Invoices'),
        'quotation' => __('Quotations'),
        'proforma' => __('Proforma invoices'),
        'bill' => __('Bills'),
        'purchase_order' => __('Purchase orders'),
        'receipt_voucher' => __('Receipt vouchers'),
        'payment_voucher' => __('Payment vouchers'),
        'voucher_summary' => __('Voucher summaries'),
        'client_statement' => __('Client statements'),
        'account_statement' => __('Account Statement (Reports)'),
        'project_cash_flow' => __('Project Cash Flow'),
        'letter' => __('Letters & Agreements'),
        'machinery_statement' => __('Machinery Statement'),
    ];
    $fontFamilyLabels = [
        'cairo' => __('Cairo (default)'),
        'tajawal' => __('Tajawal'),
        'ibm_plex_sans_arabic' => __('IBM Plex Sans Arabic'),
        'noto_naskh_arabic' => __('Noto Naskh Arabic'),
        'inter' => __('Inter'),
    ];
    $fontSizeLabels = [
        'small' => __('Small'),
        'medium' => __('Medium (default)'),
        'large' => __('Large'),
    ];
    $borderStyleLabels = [
        'none' => __('None (default)'),
        'thin' => __('Thin frame'),
        'thick' => __('Thick frame'),
        'double' => __('Double-line frame'),
    ];
@endphp

@section('content')
<div class="mb-6 flex flex-wrap items-start justify-between gap-3">
    <div>
        <h2 class="text-lg font-semibold text-slate-900">{{ __('Template Customization') }}</h2>
        <p class="text-sm text-slate-500 mt-1">{{ __('Pick a design, choose every document type it should apply to, then customize it.') }}</p>
    </div>
    <a href="{{ route('app.invoice-templates.gallery') }}" class="shrink-0 rounded-lg border border-slate-200 bg-white px-4 py-2 text-sm font-semibold text-slate-600 hover:border-slate-300">
        {{ __('Back to gallery') }}
    </a>
</div>

<div class="grid lg:grid-cols-3 gap-6 items-start">
    <div class="bg-white rounded-xl border border-slate-100 p-5">
        <form method="GET" class="mb-3">
            <select name="type" onchange="this.form.submit()" class="w-full rounded-lg border border-slate-200 text-sm focus:border-brand-500 focus:ring-brand-500">
                <option value="">{{ __('All types') }}</option>
                @foreach ($documentTypes as $dt)
                    <option value="{{ $dt }}" @selected($type === $dt)>{{ $typeLabels[$dt] }}</option>
                @endforeach
            </select>
        </form>
        <div class="flex gap-2 mb-4">
            <button type="button" onclick="document.getElementById('starter-modal').showModal()" class="flex-1 rounded-lg bg-brand-50 text-brand-700 px-4 py-2 text-sm font-semibold hover:bg-brand-100">{{ __('Starter templates') }}</button>
            <button type="button" onclick="document.getElementById('new-template-modal').showModal()" class="flex-1 rounded-lg bg-slate-900 text-white px-4 py-2 text-sm font-semibold hover:bg-slate-800">{{ __('New template') }}</button>
        </div>

        @if ($designs->isEmpty())
            <div class="rounded-lg border border-dashed border-slate-200 px-4 py-10 text-center">
                <p class="text-sm text-slate-500">{{ __('No templates yet.') }}</p>
                <p class="text-xs text-slate-400 mt-1">{{ __('Click "New template" to create one.') }}</p>
            </div>
        @else
            <ul class="space-y-1">
                @foreach ($designs as $design)
                    <li>
                        <a href="{{ route('app.invoice-templates.index', array_filter(['type' => $type, 'template' => $design->primary->id])) }}"
                           class="block rounded-lg px-3 py-2 text-sm {{ $selected && $selected->design_group === $design->design_group ? 'bg-brand-50 text-brand-800' : 'text-slate-600 hover:bg-slate-50' }}">
                            <span class="flex items-center gap-2 truncate">
                                <span class="inline-block h-3 w-3 rounded-full shrink-0" style="background-color: {{ $design->primary->accent_color }}"></span>
                                <span class="truncate font-medium">{{ $design->primary->name }}</span>
                            </span>
                            <span class="mt-1 flex flex-wrap gap-1">
                                @foreach ($design->rows as $row)
                                    <span class="inline-flex items-center gap-1 rounded-full px-1.5 py-0.5 text-[10px] font-medium {{ $row->is_default ? 'bg-emerald-50 text-emerald-700' : 'bg-slate-100 text-slate-500' }}">
                                        @if ($row->is_default)<span class="h-1.5 w-1.5 rounded-full bg-emerald-500"></span>@endif
                                        {{ $typeLabels[$row->document_type] ?? $row->document_type }}
                                    </span>
                                @endforeach
                            </span>
                        </a>
                    </li>
                @endforeach
            </ul>
        @endif
    </div>

    <div class="lg:col-span-2 bg-white rounded-xl border border-slate-100 p-6 min-h-[24rem]">
        @if ($selected)
            @php($primaryTpl = $selected->primary)
            <div class="flex items-start justify-between mb-6">
                <div>
                    <h3 class="font-semibold text-slate-900">{{ $primaryTpl->name }}</h3>
                    <p class="text-xs text-slate-400 mt-0.5">
                        {{ __('Applies to') }}:
                        {{ collect($selected->document_types)->map(fn ($dt) => $typeLabels[$dt] ?? $dt)->join(', ') }}
                    </p>
                </div>
                <div class="flex items-center gap-2">
                    @if (collect($selected->rows)->every(fn ($r) => $r->is_default))
                        <span class="rounded-lg bg-emerald-50 text-emerald-700 px-3 py-1.5 text-xs font-semibold">✓ {{ __('Default') }}</span>
                    @else
                        <form method="POST" action="{{ route('app.invoice-templates.make-default', $primaryTpl) }}">
                            @csrf
                            <button type="submit" class="rounded-lg border border-slate-200 px-3 py-1.5 text-xs font-semibold text-slate-600 hover:border-slate-300">{{ __('Set as default everywhere it applies') }}</button>
                        </form>
                    @endif
                    <form method="POST" action="{{ route('app.invoice-templates.destroy', $primaryTpl) }}" onsubmit="return confirm('{{ __('Delete this template? It will be removed from every document type it applies to.') }}')">
                        @csrf @method('DELETE')
                        <button type="submit" class="rounded-lg border border-slate-200 px-3 py-1.5 text-xs font-semibold text-red-600 hover:border-red-300">{{ __('Delete') }}</button>
                    </form>
                </div>
            </div>

            <div class="grid md:grid-cols-2 gap-6">
                <form method="POST" action="{{ route('app.invoice-templates.update', $primaryTpl) }}" enctype="multipart/form-data" class="space-y-4" x-data="{ layout: '{{ $primaryTpl->layout }}' }">
                    @csrf @method('PUT')
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block text-xs font-medium text-slate-500">{{ __('Name') }}</label>
                            <input type="text" name="name" value="{{ $primaryTpl->name }}" required class="mt-1 w-full rounded-lg border border-slate-200 text-sm focus:border-brand-500 focus:ring-brand-500">
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-slate-500">{{ __('Name (Arabic)') }}</label>
                            <input type="text" name="name_ar" value="{{ $primaryTpl->name_ar }}" class="mt-1 w-full rounded-lg border border-slate-200 text-sm focus:border-brand-500 focus:ring-brand-500">
                        </div>
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-slate-500 mb-1.5">{{ __('Applies to') }}</label>
                        @include('user.invoice-templates.partials.document-types-checkboxes', ['documentTypes' => $documentTypes, 'typeLabels' => $typeLabels, 'checked' => $selected->document_types])
                        <p class="mt-1 text-xs text-slate-400">{{ __('Tick every document type this design should be used for. Unticking one hands that type back to whichever design was active before, or the built-in default.') }}</p>
                    </div>
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block text-xs font-medium text-slate-500">{{ __('Accent color') }}</label>
                            <input type="color" name="accent_color" value="{{ $primaryTpl->accent_color }}" class="mt-1 h-9 w-full rounded-lg border border-slate-200">
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-slate-500">{{ __('Layout') }}</label>
                            <select name="layout" x-model="layout" class="mt-1 w-full rounded-lg border border-slate-200 text-sm focus:border-brand-500 focus:ring-brand-500">
                                <option value="boxed" @selected($primaryTpl->layout === 'boxed')>{{ __('Boxed totals') }}</option>
                                <option value="bordered" @selected($primaryTpl->layout === 'bordered')>{{ __('Accent border') }}</option>
                                <option value="minimal" @selected($primaryTpl->layout === 'minimal')>{{ __('Minimal') }}</option>
                                <option value="bilingual_classic" @selected($primaryTpl->layout === 'bilingual_classic')>{{ __('Bilingual Classic') }}</option>
                                <option value="custom_letterhead" @selected($primaryTpl->layout === 'custom_letterhead')>{{ __('Custom Letterhead') }}</option>
                                <option value="quotation_offer" @selected($primaryTpl->layout === 'quotation_offer')>{{ __('Quotation Offer') }}</option>
                                <option value="sage_classic" @selected($primaryTpl->layout === 'sage_classic')>{{ __('Sage Classic') }}</option>
                                <option value="retail_classic" @selected($primaryTpl->layout === 'retail_classic')>{{ __('Retail Classic') }}</option>
                            </select>
                        </div>
                    </div>
                    <div class="grid grid-cols-3 gap-3">
                        <div>
                            <label class="block text-xs font-medium text-slate-500">{{ __('Font family') }}</label>
                            <select name="font_family" class="mt-1 w-full rounded-lg border border-slate-200 text-sm focus:border-brand-500 focus:ring-brand-500">
                                <option value="cairo" @selected(! $primaryTpl->font_family || $primaryTpl->font_family === 'cairo')>{{ $fontFamilyLabels['cairo'] }}</option>
                                @foreach (array_slice(\App\Models\InvoiceTemplate::FONT_FAMILIES, 1) as $ff)
                                    <option value="{{ $ff }}" @selected($primaryTpl->font_family === $ff)>{{ $fontFamilyLabels[$ff] ?? $ff }}</option>
                                @endforeach
                            </select>
                            <p class="mt-1 text-[11px] text-slate-400">{{ __('Applies on screen. Downloaded PDFs always use Cairo, to keep Arabic text rendering correctly.') }}</p>
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-slate-500">{{ __('Font size') }}</label>
                            <select name="font_size" class="mt-1 w-full rounded-lg border border-slate-200 text-sm focus:border-brand-500 focus:ring-brand-500">
                                @foreach (\App\Models\InvoiceTemplate::FONT_SIZES as $fs)
                                    <option value="{{ $fs }}" @selected(($primaryTpl->font_size ?: 'medium') === $fs)>{{ $fontSizeLabels[$fs] ?? $fs }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-slate-500">{{ __('Border') }}</label>
                            <select name="border_style" class="mt-1 w-full rounded-lg border border-slate-200 text-sm focus:border-brand-500 focus:ring-brand-500">
                                @foreach (\App\Models\InvoiceTemplate::BORDER_STYLES as $bs)
                                    <option value="{{ $bs }}" @selected(($primaryTpl->border_style ?: 'none') === $bs)>{{ $borderStyleLabels[$bs] ?? $bs }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block text-xs font-medium text-slate-500">{{ __('Table header color') }}</label>
                            <div class="mt-1 flex items-center gap-2">
                                <input type="color" name="table_header_color" value="{{ $primaryTpl->table_header_color ?: '#f1f5f9' }}" class="h-9 w-full rounded-lg border border-slate-200">
                            </div>
                            <label class="mt-1 flex items-center gap-1.5 text-xs text-slate-500">
                                <input type="checkbox" name="remove_table_header_color" value="1" @checked(! $primaryTpl->table_header_color) class="rounded border-slate-300 text-brand-600 focus:ring-brand-500">
                                {{ __('Use layout default') }}
                            </label>
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-slate-500">{{ __('Totals color') }}</label>
                            <div class="mt-1 flex items-center gap-2">
                                <input type="color" name="totals_color" value="{{ $primaryTpl->totalsColor() }}" class="h-9 w-full rounded-lg border border-slate-200">
                            </div>
                            <label class="mt-1 flex items-center gap-1.5 text-xs text-slate-500">
                                <input type="checkbox" name="remove_totals_color" value="1" @checked(! $primaryTpl->totals_color) class="rounded border-slate-300 text-brand-600 focus:ring-brand-500">
                                {{ __('Same as accent color') }}
                            </label>
                        </div>
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-slate-500">{{ __('Spacing') }}</label>
                        <select name="density" class="mt-1 w-full rounded-lg border border-slate-200 text-sm focus:border-brand-500 focus:ring-brand-500">
                            <option value="compact" @selected($primaryTpl->density === 'compact')>{{ __('Compact — fits more on one page') }}</option>
                            <option value="comfortable" @selected($primaryTpl->density === 'comfortable')>{{ __('Comfortable — more breathing room') }}</option>
                        </select>
                    </div>
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block text-xs font-medium text-slate-500">{{ __('Language') }}</label>
                            <select name="language_mode" class="mt-1 w-full rounded-lg border border-slate-200 text-sm focus:border-brand-500 focus:ring-brand-500">
                                <option value="bilingual" @selected($primaryTpl->language_mode === 'bilingual')>{{ __('Bilingual (English + Arabic)') }}</option>
                                <option value="english_only" @selected($primaryTpl->language_mode === 'english_only')>{{ __('English only') }}</option>
                                <option value="arabic_only" @selected($primaryTpl->language_mode === 'arabic_only')>{{ __('Arabic only') }}</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-slate-500">{{ __('Table direction') }}</label>
                            <select name="table_direction" class="mt-1 w-full rounded-lg border border-slate-200 text-sm focus:border-brand-500 focus:ring-brand-500">
                                <option value="ltr" @selected($primaryTpl->table_direction === 'ltr')>{{ __('Left to right') }}</option>
                                <option value="rtl" @selected($primaryTpl->table_direction === 'rtl')>{{ __('Right to left') }}</option>
                            </select>
                        </div>
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-slate-500">{{ __('PDF paper size') }}</label>
                        <select name="page_size" class="mt-1 w-full rounded-lg border border-slate-200 text-sm focus:border-brand-500 focus:ring-brand-500">
                            <option value="a4" @selected($primaryTpl->page_size === 'a4')>{{ __('A4') }}</option>
                            <option value="letter" @selected($primaryTpl->page_size === 'letter')>{{ __('US Letter') }}</option>
                        </select>
                    </div>
                    <label class="flex items-center gap-2 text-sm text-slate-700">
                        <input type="checkbox" name="show_logo" value="1" @checked($primaryTpl->show_logo) class="rounded border-slate-300 text-brand-600 focus:ring-brand-500">
                        {{ __('Show company logo') }}
                    </label>
                    <label class="flex items-center gap-2 text-sm text-slate-700">
                        <input type="checkbox" name="show_unit_labels" value="1" @checked($primaryTpl->show_unit_labels) class="rounded border-slate-300 text-brand-600 focus:ring-brand-500">
                        {{ __('Show unit labels next to quantity (e.g. "pcs", "kg")') }}
                    </label>
                    <label class="flex items-center gap-2 text-sm text-slate-700">
                        <input type="checkbox" name="show_party_vat_number" value="1" @checked($primaryTpl->show_party_vat_number) class="rounded border-slate-300 text-brand-600 focus:ring-brand-500">
                        {{ __('Show the client/supplier\'s VAT number') }}
                    </label>
                    <label class="flex items-center gap-2 text-sm text-slate-700">
                        <input type="checkbox" name="show_item_description" value="1" @checked($primaryTpl->show_item_description) class="rounded border-slate-300 text-brand-600 focus:ring-brand-500">
                        {{ __('Show each item\'s description below its name') }}
                    </label>
                    <label class="flex items-center gap-2 text-sm text-slate-700">
                        <input type="checkbox" name="show_vat_column" value="1" @checked($primaryTpl->show_vat_column) class="rounded border-slate-300 text-brand-600 focus:ring-brand-500">
                        {{ __('Show the per-line VAT column (totals still include VAT either way)') }}
                    </label>
                    <div x-data="{ signature: {{ $primaryTpl->show_signature ? 'true' : 'false' }} }">
                        <label class="flex items-center gap-2 text-sm text-slate-700">
                            <input type="checkbox" name="show_signature" value="1" x-model="signature" @checked($primaryTpl->show_signature) class="rounded border-slate-300 text-brand-600 focus:ring-brand-500">
                            {{ __('Show signature area') }}
                        </label>
                        <div x-show="signature" x-cloak class="grid grid-cols-2 gap-3 mt-2">
                            <div>
                                <label class="block text-xs font-medium text-slate-500">{{ __('Signature label (English)') }}</label>
                                <input type="text" name="signature_label_en" value="{{ $primaryTpl->signature_label_en }}" placeholder="{{ __('Authorized Signature') }}" class="mt-1 w-full rounded-lg border border-slate-200 text-sm focus:border-brand-500 focus:ring-brand-500">
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-slate-500">{{ __('Signature label (Arabic)') }}</label>
                                <input type="text" name="signature_label_ar" value="{{ $primaryTpl->signature_label_ar }}" dir="rtl" class="mt-1 w-full rounded-lg border border-slate-200 text-sm focus:border-brand-500 focus:ring-brand-500">
                            </div>
                        </div>
                    </div>
                    <div x-show="layout === 'custom_letterhead'" x-cloak>
                        <label class="block text-xs font-medium text-slate-500">{{ __('Letterhead image') }}</label>
                        @if ($primaryTpl->letterhead_path)
                            <img src="{{ Storage::url($primaryTpl->letterhead_path) }}" alt="{{ __('Letterhead') }}" class="mt-2 mb-2 h-16 rounded border border-slate-100 object-contain">
                        @endif
                        <input type="file" name="letterhead" accept="image/*" class="mt-1 w-full rounded-lg border border-slate-200 text-sm focus:border-brand-500 focus:ring-brand-500">
                        <p class="mt-1 text-xs text-slate-400">{{ __('Upload a wide banner image — it replaces the logo/company-name header entirely on this layout.') }}</p>
                    </div>
                    <div x-show="layout === 'quotation_offer'" x-data="{ greeting: {{ $primaryTpl->show_greeting ? 'true' : 'false' }} }" x-cloak>
                        <label class="flex items-center gap-2 text-sm text-slate-700">
                            <input type="checkbox" name="show_greeting" value="1" x-model="greeting" @checked($primaryTpl->show_greeting) class="rounded border-slate-300 text-brand-600 focus:ring-brand-500">
                            {{ __('Show the "Dear Sirs..." greeting above the item table') }}
                        </label>
                        <div x-show="greeting" x-cloak class="grid grid-cols-2 gap-3 mt-2">
                            <div>
                                <label class="block text-xs font-medium text-slate-500">{{ __('Custom greeting (English)') }}</label>
                                <textarea name="greeting_en" rows="2" placeholder="{{ __('Dear Sirs, We are pleased to submit the following quotation in accordance with the items below:') }}" class="mt-1 w-full rounded-lg border border-slate-200 text-sm focus:border-brand-500 focus:ring-brand-500">{{ $primaryTpl->greeting_en }}</textarea>
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-slate-500">{{ __('Custom greeting (Arabic)') }}</label>
                                <textarea name="greeting_ar" rows="2" dir="rtl" class="mt-1 w-full rounded-lg border border-slate-200 text-sm focus:border-brand-500 focus:ring-brand-500">{{ $primaryTpl->greeting_ar }}</textarea>
                            </div>
                        </div>
                        <p class="mt-1 text-xs text-slate-400">{{ __('Leave both blank to use the default wording. Language above still controls bilingual vs. single-language printing.') }}</p>
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-slate-500">{{ __('Footer image') }}</label>
                        @if ($primaryTpl->footer_path)
                            <img src="{{ Storage::url($primaryTpl->footer_path) }}" alt="{{ __('Footer') }}" class="mt-2 mb-2 h-16 rounded border border-slate-100 object-contain">
                            <label class="flex items-center gap-2 text-xs text-slate-500 mb-2">
                                <input type="checkbox" name="remove_footer" value="1" class="rounded border-slate-300 text-red-600 focus:ring-red-500">
                                {{ __('Remove this footer image') }}
                            </label>
                        @endif
                        <input type="file" name="footer" accept="image/*" class="mt-1 w-full rounded-lg border border-slate-200 text-sm focus:border-brand-500 focus:ring-brand-500">
                        <p class="mt-1 text-xs text-slate-400">{{ __('A wide banner printed at the bottom of every document and voucher on this template — a contact-details strip, a brand pattern, whatever your company prints there.') }}</p>
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-slate-500">{{ __('Background watermark') }}</label>
                        @if ($primaryTpl->watermark_path)
                            <img src="{{ Storage::url($primaryTpl->watermark_path) }}" alt="{{ __('Watermark') }}" class="mt-2 mb-2 h-16 rounded border border-slate-100 object-contain">
                            <label class="flex items-center gap-2 text-xs text-slate-500 mb-2">
                                <input type="checkbox" name="remove_watermark" value="1" class="rounded border-slate-300 text-red-600 focus:ring-red-500">
                                {{ __('Remove this watermark') }}
                            </label>
                        @endif
                        <input type="file" name="watermark" accept="image/*" class="mt-1 w-full rounded-lg border border-slate-200 text-sm focus:border-brand-500 focus:ring-brand-500">
                        <p class="mt-1 text-xs text-slate-400">{{ __('Shown faintly behind the whole document — a company seal, logo, or "DRAFT"-style graphic.') }}</p>
                        <div class="mt-2">
                            <label class="block text-xs font-medium text-slate-500">{{ __('Watermark opacity') }}</label>
                            <div class="mt-1 flex items-center gap-3">
                                <input type="range" name="watermark_opacity" min="1" max="100" value="{{ $primaryTpl->watermark_opacity }}" class="w-full" oninput="this.nextElementSibling.textContent = this.value + '%'">
                                <span class="text-xs text-slate-500 w-10 text-end">{{ $primaryTpl->watermark_opacity }}%</span>
                            </div>
                        </div>
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-slate-500">{{ __('Footer note (English)') }}</label>
                        <textarea name="notes_en" rows="2" class="mt-1 w-full rounded-lg border border-slate-200 text-sm focus:border-brand-500 focus:ring-brand-500">{{ $primaryTpl->notes_en }}</textarea>
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-slate-500">{{ __('Footer note (Arabic)') }}</label>
                        <textarea name="notes_ar" rows="2" dir="rtl" class="mt-1 w-full rounded-lg border border-slate-200 text-sm focus:border-brand-500 focus:ring-brand-500">{{ $primaryTpl->notes_ar }}</textarea>
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-slate-500">{{ __('Terms & Conditions (English)') }}</label>
                        <textarea name="terms_en" rows="3" class="mt-1 w-full rounded-lg border border-slate-200 text-sm focus:border-brand-500 focus:ring-brand-500">{{ $primaryTpl->terms_en }}</textarea>
                        <p class="mt-1 text-xs text-slate-400">{{ __('Shown as its own labeled section, separate from the footer note above — payment terms, warranty, late-fee policy, etc.') }}</p>
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-slate-500">{{ __('Terms & Conditions (Arabic)') }}</label>
                        <textarea name="terms_ar" rows="3" dir="rtl" class="mt-1 w-full rounded-lg border border-slate-200 text-sm focus:border-brand-500 focus:ring-brand-500">{{ $primaryTpl->terms_ar }}</textarea>
                    </div>
                    <button type="submit" class="rounded-lg bg-brand-600 px-4 py-2 text-sm font-semibold text-white hover:bg-brand-700">{{ __('Save template') }}</button>
                </form>

                <div>
                    <p class="text-xs font-medium text-slate-500 mb-2">{{ __('Preview') }}</p>
                    @include('user.invoice-templates.partials.preview', ['tpl' => $primaryTpl])
                </div>
            </div>
        @else
            <div class="flex items-center justify-center h-full py-24 text-sm text-slate-400">{{ __('Select a template from the list, or create a new one.') }}</div>
        @endif
    </div>
</div>

<dialog id="starter-modal" class="rounded-2xl border border-slate-100 p-0 w-full max-w-5xl backdrop:bg-slate-900/40">
    <div class="p-6">
        <div class="flex items-start justify-between mb-4">
            <div>
                <h3 class="text-lg font-bold text-slate-900">{{ __('Starter templates') }} <span class="text-sm font-normal text-slate-400">{{ count($presets) }}</span></h3>
                <p class="text-sm text-slate-500">{{ __('Start with a polished layout, then make it yours. Applies everywhere by default — tick which document types it should use below after creating it.') }}</p>
            </div>
            <button type="button" onclick="document.getElementById('starter-modal').close()" class="text-slate-400 hover:text-slate-600">✕</button>
        </div>
        <div class="grid sm:grid-cols-3 gap-5">
            @foreach ($presets as $key => $preset)
                <div class="border border-slate-100 rounded-xl overflow-hidden">
                    @include('user.invoice-templates.partials.preview', ['tpl' => (object) $preset, 'compact' => true])
                    <div class="p-3 flex items-center justify-between border-t border-slate-100">
                        <div>
                            <p class="text-sm font-semibold text-slate-900">{{ $preset['name'] }}</p>
                            <p class="text-[10px] text-slate-400">{{ $preset['size'] }}</p>
                        </div>
                        <form method="POST" action="{{ route('app.invoice-templates.use') }}">
                            @csrf
                            <input type="hidden" name="preset" value="{{ $key }}">
                            <input type="hidden" name="document_types[]" value="all">
                            <button type="submit" class="rounded-lg bg-slate-900 px-3 py-1.5 text-xs font-semibold text-white hover:bg-slate-800">{{ __('Use template') }}</button>
                        </form>
                    </div>
                </div>
            @endforeach
        </div>
    </div>
</dialog>

<dialog id="new-template-modal" class="rounded-2xl border border-slate-100 p-0 w-full max-w-md backdrop:bg-slate-900/40">
    <form method="POST" action="{{ route('app.invoice-templates.store') }}" class="p-6 space-y-4">
        @csrf
        <div class="flex items-start justify-between">
            <h3 class="text-lg font-bold text-slate-900">{{ __('New template') }}</h3>
            <button type="button" onclick="document.getElementById('new-template-modal').close()" class="text-slate-400 hover:text-slate-600">✕</button>
        </div>
        <div>
            <label class="block text-xs font-medium text-slate-500">{{ __('Name') }}</label>
            <input type="text" name="name" required class="mt-1 w-full rounded-lg border border-slate-200 text-sm focus:border-brand-500 focus:ring-brand-500">
        </div>
        <div>
            <label class="block text-xs font-medium text-slate-500 mb-1.5">{{ __('Applies to') }}</label>
            @include('user.invoice-templates.partials.document-types-checkboxes', ['documentTypes' => $documentTypes, 'typeLabels' => $typeLabels, 'checked' => []])
        </div>
        <button type="submit" class="w-full rounded-lg bg-brand-600 px-4 py-2 text-sm font-semibold text-white hover:bg-brand-700">{{ __('Create template') }}</button>
    </form>
</dialog>
@endsection
