@extends('layouts.admin')

@section('title', __('Edit section'))

@section('content')
<div class="mb-6 flex items-center justify-between">
    <div>
        <a href="{{ route('admin.cms.pages.show', $section->page) }}" class="text-sm text-slate-500 hover:text-slate-700">&larr; {{ __(ucfirst($section->page)) }}</a>
        <h1 class="mt-1 text-xl font-bold text-slate-900">{{ str_replace('_', ' ', ucfirst($section->type)) }} {{ __('section') }}</h1>
    </div>
</div>

<form method="POST" action="{{ route('admin.cms.sections.update', $section) }}" enctype="multipart/form-data" class="space-y-6">
    @csrf @method('PUT')

    <div class="rounded-xl border border-slate-100 bg-white p-6">
        <label class="flex items-center gap-2 text-sm">
            <input type="checkbox" name="is_active" value="1" @checked($section->is_active) class="rounded border-slate-300 text-brand-600 focus:ring-brand-500">
            {{ __('Visible on the public site') }}
        </label>
    </div>

    @if (in_array($section->type, ['hero']))
        <div class="rounded-xl border border-slate-100 bg-white p-6">
            <label class="block text-xs font-semibold uppercase tracking-wide text-slate-400">{{ __('Badge (small pill above the title, optional)') }}</label>
            <div class="mt-1.5 grid gap-4 md:grid-cols-2">
                <input type="text" name="badge_en" value="{{ old('badge_en', $section->badge_en) }}" placeholder="{{ __('English') }}" class="rounded-lg border border-slate-200 text-sm focus:border-brand-500 focus:ring-brand-500">
                <input type="text" name="badge_ar" value="{{ old('badge_ar', $section->badge_ar) }}" dir="rtl" placeholder="{{ __('Arabic') }}" class="rounded-lg border border-slate-200 text-sm focus:border-brand-500 focus:ring-brand-500">
            </div>
        </div>
    @endif

    <div class="rounded-xl border border-slate-100 bg-white p-6">
        <label class="block text-xs font-semibold uppercase tracking-wide text-slate-400">{{ __('Title') }}</label>
        <div class="mt-1.5 grid gap-4 md:grid-cols-2">
            <input type="text" name="title_en" value="{{ old('title_en', $section->title_en) }}" placeholder="{{ __('English') }}" class="rounded-lg border border-slate-200 text-sm focus:border-brand-500 focus:ring-brand-500">
            <input type="text" name="title_ar" value="{{ old('title_ar', $section->title_ar) }}" dir="rtl" placeholder="{{ __('Arabic') }}" class="rounded-lg border border-slate-200 text-sm focus:border-brand-500 focus:ring-brand-500">
        </div>

        <label class="mt-4 block text-xs font-semibold uppercase tracking-wide text-slate-400">{{ __('Subtitle') }}</label>
        <div class="mt-1.5 grid gap-4 md:grid-cols-2">
            <textarea name="subtitle_en" rows="2" placeholder="{{ __('English') }}" class="rounded-lg border border-slate-200 text-sm focus:border-brand-500 focus:ring-brand-500">{{ old('subtitle_en', $section->subtitle_en) }}</textarea>
            <textarea name="subtitle_ar" rows="2" dir="rtl" placeholder="{{ __('Arabic') }}" class="rounded-lg border border-slate-200 text-sm focus:border-brand-500 focus:ring-brand-500">{{ old('subtitle_ar', $section->subtitle_ar) }}</textarea>
        </div>

        @if (in_array($section->type, ['text']))
            <label class="mt-4 block text-xs font-semibold uppercase tracking-wide text-slate-400">{{ __('Body') }}</label>
            <div class="mt-1.5 grid gap-4 md:grid-cols-2">
                <textarea name="body_en" rows="6" placeholder="{{ __('English') }}" class="rounded-lg border border-slate-200 text-sm focus:border-brand-500 focus:ring-brand-500">{{ old('body_en', $section->body_en) }}</textarea>
                <textarea name="body_ar" rows="6" dir="rtl" placeholder="{{ __('Arabic') }}" class="rounded-lg border border-slate-200 text-sm focus:border-brand-500 focus:ring-brand-500">{{ old('body_ar', $section->body_ar) }}</textarea>
            </div>
            <p class="mt-2 text-xs text-slate-400">{{ __('Plain text, one idea per line — line breaks are preserved when shown on the page.') }}</p>
        @endif
    </div>

    @if (in_array($section->type, ['hero', 'cta']))
        <div class="rounded-xl border border-slate-100 bg-white p-6">
            <label class="block text-xs font-semibold uppercase tracking-wide text-slate-400">{{ __('Button (optional)') }}</label>
            <div class="mt-1.5 grid gap-4 md:grid-cols-3">
                <input type="text" name="link_text_en" value="{{ old('link_text_en', $section->link_text_en) }}" placeholder="{{ __('Button text (English)') }}" class="rounded-lg border border-slate-200 text-sm focus:border-brand-500 focus:ring-brand-500">
                <input type="text" name="link_text_ar" value="{{ old('link_text_ar', $section->link_text_ar) }}" dir="rtl" placeholder="{{ __('Button text (Arabic)') }}" class="rounded-lg border border-slate-200 text-sm focus:border-brand-500 focus:ring-brand-500">
                <input type="text" name="link_url" value="{{ old('link_url', $section->link_url) }}" placeholder="{{ __('Link (leave blank for the default sign-up link)') }}" class="rounded-lg border border-slate-200 text-sm focus:border-brand-500 focus:ring-brand-500">
            </div>
        </div>
    @endif

    @if (in_array($section->type, ['hero']))
        <div class="rounded-xl border border-slate-100 bg-white p-6">
            <label class="block text-xs font-semibold uppercase tracking-wide text-slate-400">{{ __('Image (optional)') }}</label>
            @if ($section->image_path)
                <div class="mt-2 flex items-center gap-3">
                    <img src="{{ \Illuminate\Support\Facades\Storage::url($section->image_path) }}" class="h-16 w-16 rounded-lg border border-slate-100 object-cover">
                    <label class="flex items-center gap-2 text-sm text-red-600">
                        <input type="checkbox" name="remove_image" value="1" class="rounded border-slate-300">
                        {{ __('Remove image') }}
                    </label>
                </div>
            @endif
            <input type="file" name="image" accept="image/*" class="mt-2 block w-full text-sm">
        </div>
    @endif

    @if ($section->hasItems())
        <div class="rounded-xl border border-slate-100 bg-white p-6">
            <div class="flex items-center justify-between">
                <label class="block text-xs font-semibold uppercase tracking-wide text-slate-400">{{ __('Items') }}</label>
                <button type="button" id="add-item" class="text-sm font-semibold text-brand-700 hover:underline">{{ __('+ Add item') }}</button>
            </div>
            <div id="items-rows" class="mt-3 space-y-4"></div>
            <p id="items-empty" class="mt-3 text-sm text-slate-400 hidden">{{ __('No items yet — add one above.') }}</p>
        </div>
    @endif

    <div class="flex justify-end gap-3">
        <a href="{{ route('admin.cms.pages.show', $section->page) }}" class="rounded-lg border border-slate-200 px-4 py-2 text-sm font-semibold text-slate-600 hover:bg-slate-50">{{ __('Cancel') }}</a>
        <button type="submit" class="rounded-lg bg-brand-600 px-5 py-2 text-sm font-semibold text-white hover:bg-brand-700">{{ __('Save section') }}</button>
    </div>
</form>

@if ($section->hasItems())
@php
    $existingItemsForJs = $section->items->map(fn ($item) => [
        'icon' => $item->icon,
        'title_en' => $item->title_en,
        'title_ar' => $item->title_ar,
        'subtitle_en' => $item->subtitle_en,
        'subtitle_ar' => $item->subtitle_ar,
        'body_en' => $item->body_en,
        'body_ar' => $item->body_ar,
        'url' => $item->meta['url'] ?? '',
    ])->values();
@endphp
<script>
(function () {
    const EXISTING = {!! json_encode($existingItemsForJs) !!};

    const rowsWrap = document.getElementById('items-rows');
    const emptyHint = document.getElementById('items-empty');
    let rowIndex = 0;

    function refreshEmptyHint() {
        emptyHint.classList.toggle('hidden', rowsWrap.children.length > 0);
    }

    function addRow(data) {
        data = data || {};
        const i = rowIndex++;
        const row = document.createElement('div');
        row.className = 'rounded-lg border border-slate-100 p-4 space-y-2';
        row.innerHTML = `
            <div class="grid grid-cols-12 gap-2">
                <input type="text" name="items[${i}][icon]" value="${data.icon ?? ''}" placeholder="{{ __('Icon / emoji') }}" class="col-span-2 rounded-lg border border-slate-200 text-sm focus:border-brand-500 focus:ring-brand-500">
                <input type="text" name="items[${i}][title_en]" value="${data.title_en ?? ''}" placeholder="{{ __('Title (English)') }}" class="col-span-5 rounded-lg border border-slate-200 text-sm focus:border-brand-500 focus:ring-brand-500">
                <input type="text" name="items[${i}][title_ar]" value="${data.title_ar ?? ''}" dir="rtl" placeholder="{{ __('Title (Arabic)') }}" class="col-span-4 rounded-lg border border-slate-200 text-sm focus:border-brand-500 focus:ring-brand-500">
                <button type="button" class="col-span-1 text-red-500 hover:text-red-700" title="{{ __('Remove') }}">✕</button>
            </div>
            <div class="grid grid-cols-12 gap-2">
                <input type="text" name="items[${i}][subtitle_en]" value="${data.subtitle_en ?? ''}" placeholder="{{ __('Subtitle (English, optional)') }}" class="col-span-4 rounded-lg border border-slate-200 text-sm focus:border-brand-500 focus:ring-brand-500">
                <input type="text" name="items[${i}][subtitle_ar]" value="${data.subtitle_ar ?? ''}" dir="rtl" placeholder="{{ __('Subtitle (Arabic, optional)') }}" class="col-span-4 rounded-lg border border-slate-200 text-sm focus:border-brand-500 focus:ring-brand-500">
                <input type="text" name="items[${i}][url]" value="${data.url ?? ''}" placeholder="{{ __('Link (optional)') }}" class="col-span-4 rounded-lg border border-slate-200 text-sm focus:border-brand-500 focus:ring-brand-500">
            </div>
            <div class="grid grid-cols-2 gap-2">
                <textarea name="items[${i}][body_en]" rows="2" placeholder="{{ __('Body (English, optional)') }}" class="rounded-lg border border-slate-200 text-sm focus:border-brand-500 focus:ring-brand-500">${data.body_en ?? ''}</textarea>
                <textarea name="items[${i}][body_ar]" rows="2" dir="rtl" placeholder="{{ __('Body (Arabic, optional)') }}" class="rounded-lg border border-slate-200 text-sm focus:border-brand-500 focus:ring-brand-500">${data.body_ar ?? ''}</textarea>
            </div>
        `;
        row.querySelector('button').addEventListener('click', () => {
            row.remove();
            refreshEmptyHint();
        });
        rowsWrap.appendChild(row);
        refreshEmptyHint();
    }

    document.getElementById('add-item').addEventListener('click', () => addRow());

    EXISTING.forEach(addRow);
    refreshEmptyHint();
})();
</script>
@endif
@endsection
