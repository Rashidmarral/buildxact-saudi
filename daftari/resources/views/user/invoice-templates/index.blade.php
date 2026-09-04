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
    ];
@endphp

@section('content')
<div class="mb-6">
    <h2 class="text-lg font-semibold text-slate-900">{{ __('Template Customization') }}</h2>
    <p class="text-sm text-slate-500 mt-1">{{ __('Customize the default layout and notes for each document type') }}</p>
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

        @if ($templates->isEmpty())
            <div class="rounded-lg border border-dashed border-slate-200 px-4 py-10 text-center">
                <p class="text-sm text-slate-500">{{ __('No templates yet.') }}</p>
                <p class="text-xs text-slate-400 mt-1">{{ __('Click "New template" to create one.') }}</p>
            </div>
        @else
            <ul class="space-y-1">
                @foreach ($templates as $tpl)
                    <li>
                        <a href="{{ route('app.invoice-templates.index', array_filter(['type' => $type, 'template' => $tpl->id])) }}"
                           class="flex items-center justify-between gap-2 rounded-lg px-3 py-2 text-sm {{ $selected && $selected->id === $tpl->id ? 'bg-brand-50 text-brand-800' : 'text-slate-600 hover:bg-slate-50' }}">
                            <span class="flex items-center gap-2 truncate">
                                <span class="inline-block h-3 w-3 rounded-full shrink-0" style="background-color: {{ $tpl->accent_color }}"></span>
                                <span class="truncate">{{ $tpl->name }}</span>
                            </span>
                            @if ($tpl->is_default)<span class="shrink-0 text-[10px] font-semibold text-emerald-600">{{ __('Default') }}</span>@endif
                        </a>
                    </li>
                @endforeach
            </ul>
        @endif
    </div>

    <div class="lg:col-span-2 bg-white rounded-xl border border-slate-100 p-6 min-h-[24rem]">
        @if ($selected)
            <div class="flex items-start justify-between mb-6">
                <div>
                    <h3 class="font-semibold text-slate-900">{{ $selected->name }}</h3>
                    <p class="text-xs text-slate-400 mt-0.5">{{ $typeLabels[$selected->document_type] ?? $selected->document_type }}</p>
                </div>
                <div class="flex items-center gap-2">
                    @unless ($selected->is_default)
                        <form method="POST" action="{{ route('app.invoice-templates.make-default', $selected) }}">
                            @csrf
                            <button type="submit" class="rounded-lg border border-slate-200 px-3 py-1.5 text-xs font-semibold text-slate-600 hover:border-slate-300">{{ __('Set as default') }}</button>
                        </form>
                    @else
                        <span class="rounded-lg bg-emerald-50 text-emerald-700 px-3 py-1.5 text-xs font-semibold">✓ {{ __('Default') }}</span>
                    @endunless
                    <form method="POST" action="{{ route('app.invoice-templates.destroy', $selected) }}" onsubmit="return confirm('{{ __('Delete this template?') }}')">
                        @csrf @method('DELETE')
                        <button type="submit" class="rounded-lg border border-slate-200 px-3 py-1.5 text-xs font-semibold text-red-600 hover:border-red-300">{{ __('Delete') }}</button>
                    </form>
                </div>
            </div>

            <div class="grid md:grid-cols-2 gap-6">
                <form method="POST" action="{{ route('app.invoice-templates.update', $selected) }}" enctype="multipart/form-data" class="space-y-4" x-data="{ layout: '{{ $selected->layout }}' }">
                    @csrf @method('PUT')
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block text-xs font-medium text-slate-500">{{ __('Name') }}</label>
                            <input type="text" name="name" value="{{ $selected->name }}" required class="mt-1 w-full rounded-lg border border-slate-200 text-sm focus:border-brand-500 focus:ring-brand-500">
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-slate-500">{{ __('Name (Arabic)') }}</label>
                            <input type="text" name="name_ar" value="{{ $selected->name_ar }}" class="mt-1 w-full rounded-lg border border-slate-200 text-sm focus:border-brand-500 focus:ring-brand-500">
                        </div>
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-slate-500">{{ __('Applies to') }}</label>
                        <select name="document_type" class="mt-1 w-full rounded-lg border border-slate-200 text-sm focus:border-brand-500 focus:ring-brand-500">
                            @foreach ($documentTypes as $dt)
                                <option value="{{ $dt }}" @selected($selected->document_type === $dt)>{{ $typeLabels[$dt] }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block text-xs font-medium text-slate-500">{{ __('Accent color') }}</label>
                            <input type="color" name="accent_color" value="{{ $selected->accent_color }}" class="mt-1 h-9 w-full rounded-lg border border-slate-200">
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-slate-500">{{ __('Layout') }}</label>
                            <select name="layout" x-model="layout" class="mt-1 w-full rounded-lg border border-slate-200 text-sm focus:border-brand-500 focus:ring-brand-500">
                                <option value="boxed" @selected($selected->layout === 'boxed')>{{ __('Boxed totals') }}</option>
                                <option value="bordered" @selected($selected->layout === 'bordered')>{{ __('Accent border') }}</option>
                                <option value="minimal" @selected($selected->layout === 'minimal')>{{ __('Minimal') }}</option>
                                <option value="bilingual_classic" @selected($selected->layout === 'bilingual_classic')>{{ __('Bilingual Classic') }}</option>
                                <option value="custom_letterhead" @selected($selected->layout === 'custom_letterhead')>{{ __('Custom Letterhead') }}</option>
                            </select>
                        </div>
                    </div>
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block text-xs font-medium text-slate-500">{{ __('Language') }}</label>
                            <select name="language_mode" class="mt-1 w-full rounded-lg border border-slate-200 text-sm focus:border-brand-500 focus:ring-brand-500">
                                <option value="bilingual" @selected($selected->language_mode === 'bilingual')>{{ __('Bilingual (English + Arabic)') }}</option>
                                <option value="english_only" @selected($selected->language_mode === 'english_only')>{{ __('English only') }}</option>
                                <option value="arabic_only" @selected($selected->language_mode === 'arabic_only')>{{ __('Arabic only') }}</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-slate-500">{{ __('Table direction') }}</label>
                            <select name="table_direction" class="mt-1 w-full rounded-lg border border-slate-200 text-sm focus:border-brand-500 focus:ring-brand-500">
                                <option value="ltr" @selected($selected->table_direction === 'ltr')>{{ __('Left to right') }}</option>
                                <option value="rtl" @selected($selected->table_direction === 'rtl')>{{ __('Right to left') }}</option>
                            </select>
                        </div>
                    </div>
                    <label class="flex items-center gap-2 text-sm text-slate-700">
                        <input type="checkbox" name="show_logo" value="1" @checked($selected->show_logo) class="rounded border-slate-300 text-brand-600 focus:ring-brand-500">
                        {{ __('Show company logo') }}
                    </label>
                    <div x-data="{ signature: {{ $selected->show_signature ? 'true' : 'false' }} }">
                        <label class="flex items-center gap-2 text-sm text-slate-700">
                            <input type="checkbox" name="show_signature" value="1" x-model="signature" @checked($selected->show_signature) class="rounded border-slate-300 text-brand-600 focus:ring-brand-500">
                            {{ __('Show signature area') }}
                        </label>
                        <div x-show="signature" x-cloak class="grid grid-cols-2 gap-3 mt-2">
                            <div>
                                <label class="block text-xs font-medium text-slate-500">{{ __('Signature label (English)') }}</label>
                                <input type="text" name="signature_label_en" value="{{ $selected->signature_label_en }}" placeholder="{{ __('Authorized Signature') }}" class="mt-1 w-full rounded-lg border border-slate-200 text-sm focus:border-brand-500 focus:ring-brand-500">
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-slate-500">{{ __('Signature label (Arabic)') }}</label>
                                <input type="text" name="signature_label_ar" value="{{ $selected->signature_label_ar }}" dir="rtl" class="mt-1 w-full rounded-lg border border-slate-200 text-sm focus:border-brand-500 focus:ring-brand-500">
                            </div>
                        </div>
                    </div>
                    <div x-show="layout === 'custom_letterhead'" x-cloak>
                        <label class="block text-xs font-medium text-slate-500">{{ __('Letterhead image') }}</label>
                        @if ($selected->letterhead_path)
                            <img src="{{ Storage::url($selected->letterhead_path) }}" alt="{{ __('Letterhead') }}" class="mt-2 mb-2 h-16 rounded border border-slate-100 object-contain">
                        @endif
                        <input type="file" name="letterhead" accept="image/*" class="mt-1 w-full rounded-lg border border-slate-200 text-sm focus:border-brand-500 focus:ring-brand-500">
                        <p class="mt-1 text-xs text-slate-400">{{ __('Upload a wide banner image — it replaces the logo/company-name header entirely on this layout.') }}</p>
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-slate-500">{{ __('Footer note (English)') }}</label>
                        <textarea name="notes_en" rows="2" class="mt-1 w-full rounded-lg border border-slate-200 text-sm focus:border-brand-500 focus:ring-brand-500">{{ $selected->notes_en }}</textarea>
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-slate-500">{{ __('Footer note (Arabic)') }}</label>
                        <textarea name="notes_ar" rows="2" dir="rtl" class="mt-1 w-full rounded-lg border border-slate-200 text-sm focus:border-brand-500 focus:ring-brand-500">{{ $selected->notes_ar }}</textarea>
                    </div>
                    <button type="submit" class="rounded-lg bg-brand-600 px-4 py-2 text-sm font-semibold text-white hover:bg-brand-700">{{ __('Save template') }}</button>
                </form>

                <div>
                    <p class="text-xs font-medium text-slate-500 mb-2">{{ __('Preview') }}</p>
                    @include('user.invoice-templates.partials.preview', ['tpl' => $selected])
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
                <p class="text-sm text-slate-500">{{ __('Start with a polished layout, then make it yours.') }}</p>
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
                            <input type="hidden" name="document_type" value="all">
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
            <label class="block text-xs font-medium text-slate-500">{{ __('Applies to') }}</label>
            <select name="document_type" class="mt-1 w-full rounded-lg border border-slate-200 text-sm focus:border-brand-500 focus:ring-brand-500">
                @foreach ($documentTypes as $dt)
                    <option value="{{ $dt }}">{{ $typeLabels[$dt] }}</option>
                @endforeach
            </select>
        </div>
        <button type="submit" class="w-full rounded-lg bg-brand-600 px-4 py-2 text-sm font-semibold text-white hover:bg-brand-700">{{ __('Create template') }}</button>
    </form>
</dialog>
@endsection
