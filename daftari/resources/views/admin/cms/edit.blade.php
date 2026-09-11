@extends('layouts.admin')

@section('title', __('Edit section'))

@section('content')
<div class="mb-6 flex items-center justify-between">
    <div>
        <a href="{{ route('admin.cms.pages.show', $section->page) }}" class="text-sm text-slate-500 hover:text-slate-700">&larr; {{ \App\Models\CmsPage::where('slug', $section->page)->first()?->name() ?? ucfirst($section->page) }}</a>
        <h1 class="mt-1 text-xl font-bold text-slate-900">{{ str_replace('_', ' ', ucfirst($section->type)) }} {{ __('section') }}</h1>
    </div>
    @if ($section->page !== 'global' && \App\Models\CmsPage::where('slug', $section->page)->first()?->publicUrl())
        <a href="{{ \App\Models\CmsPage::where('slug', $section->page)->first()->publicUrl() }}" target="_blank" class="inline-flex items-center gap-1.5 rounded-lg border border-slate-200 px-3 py-1.5 text-sm font-semibold text-slate-600 hover:bg-slate-50">
            @include('partials.icon', ['name' => 'globe', 'class' => 'h-4 w-4'])
            {{ __('View live page') }}
        </a>
    @endif
</div>

<form method="POST" action="{{ route('admin.cms.sections.update', $section) }}" enctype="multipart/form-data" class="space-y-6" x-data="{ locale: 'en' }">
    @csrf @method('PUT')

    <div class="rounded-xl border border-slate-100 bg-white p-6">
        <label class="flex items-center gap-2 text-sm">
            <input type="checkbox" name="is_active" value="1" @checked($section->is_active) class="rounded border-slate-300 text-brand-600 focus:ring-brand-500">
            {{ __('Visible on the public site') }}
        </label>
    </div>

    <div class="sticky top-16 z-10 flex gap-1 rounded-xl border border-slate-100 bg-white p-1.5 shadow-card">
        <button type="button" @click="locale = 'en'" :class="locale === 'en' ? 'bg-brand-600 text-white shadow-soft' : 'text-slate-500 hover:bg-slate-50'" class="flex-1 rounded-lg px-4 py-2 text-sm font-semibold transition-colors">{{ __('English content') }}</button>
        <button type="button" @click="locale = 'ar'" :class="locale === 'ar' ? 'bg-brand-600 text-white shadow-soft' : 'text-slate-500 hover:bg-slate-50'" class="flex-1 rounded-lg px-4 py-2 text-sm font-semibold transition-colors">{{ __('Arabic content') }}</button>
    </div>

    @if (in_array($section->type, ['hero', 'site_header']))
        <div class="rounded-xl border border-slate-100 bg-white p-6">
            <label class="block text-xs font-semibold uppercase tracking-wide text-slate-400">{{ __('Badge (small pill above the title, optional)') }}</label>
            <div class="mt-1.5">
                <input x-show="locale === 'en'" type="text" name="badge_en" value="{{ old('badge_en', $section->badge_en) }}" placeholder="{{ __('English') }}" class="w-full rounded-lg border border-slate-200 text-sm focus:border-brand-500 focus:ring-brand-500">
                <input x-show="locale === 'ar'" type="text" name="badge_ar" value="{{ old('badge_ar', $section->badge_ar) }}" dir="rtl" placeholder="{{ __('Arabic') }}" class="w-full rounded-lg border border-slate-200 text-sm focus:border-brand-500 focus:ring-brand-500">
            </div>
        </div>
    @endif

    <div class="rounded-xl border border-slate-100 bg-white p-6">
        @unless ($section->type === 'site_footer')
            <label class="block text-xs font-semibold uppercase tracking-wide text-slate-400">{{ __('Title') }}</label>
            <div class="mt-1.5">
                <input x-show="locale === 'en'" type="text" name="title_en" value="{{ old('title_en', $section->title_en) }}" placeholder="{{ __('English') }}" class="w-full rounded-lg border border-slate-200 text-sm focus:border-brand-500 focus:ring-brand-500">
                <input x-show="locale === 'ar'" type="text" name="title_ar" value="{{ old('title_ar', $section->title_ar) }}" dir="rtl" placeholder="{{ __('Arabic') }}" class="w-full rounded-lg border border-slate-200 text-sm focus:border-brand-500 focus:ring-brand-500">
            </div>
        @endunless

        <label class="mt-4 block text-xs font-semibold uppercase tracking-wide text-slate-400">
            {{ $section->type === 'site_footer' ? __('Footer tagline') : __('Subtitle') }}
        </label>
        <div class="mt-1.5">
            <textarea x-show="locale === 'en'" name="subtitle_en" rows="2" placeholder="{{ __('English') }}" class="w-full rounded-lg border border-slate-200 text-sm focus:border-brand-500 focus:ring-brand-500">{{ old('subtitle_en', $section->subtitle_en) }}</textarea>
            <textarea x-show="locale === 'ar'" name="subtitle_ar" rows="2" dir="rtl" placeholder="{{ __('Arabic') }}" class="w-full rounded-lg border border-slate-200 text-sm focus:border-brand-500 focus:ring-brand-500">{{ old('subtitle_ar', $section->subtitle_ar) }}</textarea>
        </div>

        @if (in_array($section->type, ['text']))
            <label class="mt-4 block text-xs font-semibold uppercase tracking-wide text-slate-400">{{ __('Body') }}</label>
            <div class="mt-1.5">
                <div x-show="locale === 'en'">
                    @include('partials.rich-text-editor', ['name' => 'body_en', 'value' => old('body_en', $section->body_en), 'placeholder' => __('Write the body content…')])
                </div>
                <div x-show="locale === 'ar'">
                    @include('partials.rich-text-editor', ['name' => 'body_ar', 'value' => old('body_ar', $section->body_ar), 'dir' => 'rtl', 'placeholder' => __('اكتب المحتوى…')])
                </div>
            </div>
        @endif
    </div>

    @if (in_array($section->type, ['hero', 'cta', 'site_header']))
        <div class="rounded-xl border border-slate-100 bg-white p-6">
            <label class="block text-xs font-semibold uppercase tracking-wide text-slate-400">{{ __('Button (optional)') }}</label>
            <div class="mt-1.5 grid gap-4 md:grid-cols-2">
                <input x-show="locale === 'en'" type="text" name="link_text_en" value="{{ old('link_text_en', $section->link_text_en) }}" placeholder="{{ __('Button text (English)') }}" class="rounded-lg border border-slate-200 text-sm focus:border-brand-500 focus:ring-brand-500">
                <input x-show="locale === 'ar'" type="text" name="link_text_ar" value="{{ old('link_text_ar', $section->link_text_ar) }}" dir="rtl" placeholder="{{ __('Button text (Arabic)') }}" class="rounded-lg border border-slate-200 text-sm focus:border-brand-500 focus:ring-brand-500">
                <input type="text" name="link_url" value="{{ old('link_url', $section->link_url) }}" placeholder="{{ __('Link (leave blank for the default sign-up link)') }}" class="rounded-lg border border-slate-200 text-sm focus:border-brand-500 focus:ring-brand-500 md:col-span-2">
            </div>
        </div>
    @endif

    @if (in_array($section->type, \App\Models\CmsSection::IMAGE_TYPES))
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

            <label class="mt-4 block text-xs font-semibold uppercase tracking-wide text-slate-400">{{ __('Image position') }}</label>
            <div class="mt-1.5 flex gap-2">
                @foreach (['left' => __('Left of content'), 'right' => __('Right of content')] as $value => $label)
                    <label class="flex-1 cursor-pointer rounded-lg border px-3 py-2 text-center text-sm {{ old('image_position', $section->image_position) === $value ? 'border-brand-500 bg-brand-50 text-brand-700 font-semibold' : 'border-slate-200 text-slate-600' }}">
                        <input type="radio" name="image_position" value="{{ $value }}" @checked(old('image_position', $section->image_position) === $value) class="sr-only">
                        {{ $label }}
                    </label>
                @endforeach
            </div>
        </div>
    @endif

    @if ($section->hasItems())
        <div class="rounded-xl border border-slate-100 bg-white p-6">
            <div class="flex items-center justify-between">
                <div>
                    <label class="block text-xs font-semibold uppercase tracking-wide text-slate-400">{{ __('Items') }}</label>
                    <p class="mt-0.5 text-xs text-slate-400">{{ __('Drag the handle to reorder.') }}</p>
                </div>
                <button type="button" id="add-item" class="text-sm font-semibold text-brand-700 hover:underline">{{ __('+ Add item') }}</button>
            </div>
            <div id="items-rows" class="mt-3 space-y-3"></div>
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
        'image_path' => $item->image_path,
        'image_url' => $item->image_path ? \Illuminate\Support\Facades\Storage::url($item->image_path) : null,
    ])->values();
@endphp
<script>
(function () {
    const EXISTING = {!! json_encode($existingItemsForJs) !!};
    const LINK_PROMPT = {!! json_encode(__('Enter a link URL:')) !!};
    const SECTION_TYPE = {!! json_encode($section->type) !!};
    const REMOVE_LABEL = {!! json_encode(__('Remove image')) !!};
    const REMOVE_ITEM_LABEL = {!! json_encode(__('Remove')) !!};

    const rowsWrap = document.getElementById('items-rows');
    const emptyHint = document.getElementById('items-empty');

    // Rich-text markup mirrors resources/views/partials/rich-text-editor.blade.php
    // (that partial is Blade/server-rendered, so item rows built here in JS
    // can't reuse it directly) — same toolbar, same richTextEditor Alpine
    // component, which auto-initializes on nodes added after page load.
    function richTextHtml(name, value, dir) {
        return `
            <div x-data="richTextEditor(${JSON.stringify(value || '')}, ${JSON.stringify(LINK_PROMPT)})" class="overflow-hidden rounded-lg border border-slate-200 focus-within:border-brand-500 focus-within:ring-1 focus-within:ring-brand-500">
                <div class="flex items-center gap-0.5 border-b border-slate-100 bg-slate-50/60 px-1.5 py-1">
                    <button type="button" @click="exec('bold')" class="rte-btn"><span class="font-bold">B</span></button>
                    <button type="button" @click="exec('italic')" class="rte-btn"><span class="italic">I</span></button>
                    <span class="mx-0.5 h-4 w-px bg-slate-200"></span>
                    <button type="button" @click="exec('insertUnorderedList')" class="rte-btn">
                        <svg class="h-3.5 w-3.5" viewBox="0 0 20 20" fill="currentColor"><circle cx="3" cy="5" r="1.3"/><circle cx="3" cy="10" r="1.3"/><circle cx="3" cy="15" r="1.3"/><rect x="7" y="4.3" width="11" height="1.4" rx="0.7"/><rect x="7" y="9.3" width="11" height="1.4" rx="0.7"/><rect x="7" y="14.3" width="11" height="1.4" rx="0.7"/></svg>
                    </button>
                    <button type="button" @click="link()" class="rte-btn">
                        <svg class="h-3.5 w-3.5" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round"><path d="M8 12a3.5 3.5 0 003.5 3.5H14a3.5 3.5 0 000-7h-1"/><path d="M12 8a3.5 3.5 0 00-3.5-3.5H6a3.5 3.5 0 000 7h1"/></svg>
                    </button>
                </div>
                <div x-ref="editable" @input="sync()" contenteditable="true" dir="${dir}" class="rte-editable min-h-[70px] px-3 py-2 text-sm text-slate-700 focus:outline-none"></div>
                <textarea x-ref="textarea" name="${name}" class="hidden"></textarea>
            </div>
        `;
    }

    function refreshEmptyHint() {
        emptyHint.classList.toggle('hidden', rowsWrap.children.length > 0);
    }

    // Rewrites every items[N] name in the current DOM order, so drag
    // reordering (which only moves DOM nodes) is reflected in the
    // submitted array order — PHP rebuilds cms_section_items from that
    // array order on save. Safe to call after any add/remove/reorder.
    function renumberRows() {
        [...rowsWrap.children].forEach((row, i) => {
            row.querySelectorAll('[name]').forEach((el) => {
                el.name = el.name.replace(/^items\[[^\]]*\]/, `items[${i}]`);
            });
        });
        refreshEmptyHint();
    }

    function addRow(data) {
        data = data || {};
        const row = document.createElement('div');
        row.className = 'item-row rounded-lg border border-slate-100 p-4 space-y-2';
        row.innerHTML = `
            <div class="flex items-start gap-2">
                <button type="button" class="drag-handle mt-2 shrink-0 cursor-grab text-slate-300 hover:text-slate-500" title="{{ __('Drag to reorder') }}">
                    <svg class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor"><circle cx="7" cy="4" r="1.3"/><circle cx="13" cy="4" r="1.3"/><circle cx="7" cy="10" r="1.3"/><circle cx="13" cy="10" r="1.3"/><circle cx="7" cy="16" r="1.3"/><circle cx="13" cy="16" r="1.3"/></svg>
                </button>
                <div class="grid flex-1 grid-cols-12 gap-2">
                    <input type="text" name="items[__i__][icon]" value="${data.icon ?? ''}" placeholder="{{ __('Icon / emoji') }}" class="col-span-2 rounded-lg border border-slate-200 text-sm focus:border-brand-500 focus:ring-brand-500">
                    <input x-show="locale === 'en'" type="text" name="items[__i__][title_en]" value="${data.title_en ?? ''}" placeholder="{{ __('Title (English)') }}" class="col-span-9 rounded-lg border border-slate-200 text-sm focus:border-brand-500 focus:ring-brand-500">
                    <input x-show="locale === 'ar'" type="text" name="items[__i__][title_ar]" value="${data.title_ar ?? ''}" dir="rtl" placeholder="{{ __('Title (Arabic)') }}" class="col-span-9 rounded-lg border border-slate-200 text-sm focus:border-brand-500 focus:ring-brand-500">
                </div>
                <button type="button" class="remove-row mt-2 shrink-0 text-red-500 hover:text-red-700" title="${REMOVE_ITEM_LABEL}">✕</button>
            </div>
            <div class="ps-6 grid grid-cols-12 gap-2">
                <input x-show="locale === 'en'" type="text" name="items[__i__][subtitle_en]" value="${data.subtitle_en ?? ''}" placeholder="{{ __('Subtitle (English, optional)') }}" class="col-span-6 rounded-lg border border-slate-200 text-sm focus:border-brand-500 focus:ring-brand-500">
                <input x-show="locale === 'ar'" type="text" name="items[__i__][subtitle_ar]" value="${data.subtitle_ar ?? ''}" dir="rtl" placeholder="{{ __('Subtitle (Arabic, optional)') }}" class="col-span-6 rounded-lg border border-slate-200 text-sm focus:border-brand-500 focus:ring-brand-500">
                <input type="text" name="items[__i__][url]" value="${data.url ?? ''}" placeholder="{{ __('Link (optional)') }}" class="col-span-6 rounded-lg border border-slate-200 text-sm focus:border-brand-500 focus:ring-brand-500">
            </div>
            <div class="ps-6 body-slot"></div>
            <div class="ps-6 flex items-center gap-3">
                ${data.image_url ? `<img src="${data.image_url}" class="h-12 w-12 rounded-lg border border-slate-100 object-cover">` : ''}
                <input type="file" name="items[__i__][image]" accept="image/*" class="block flex-1 text-xs text-slate-500">
                <input type="hidden" name="items[__i__][existing_image]" value="${data.image_path ?? ''}">
                ${data.image_url ? `<label class="flex items-center gap-1.5 text-xs text-red-600 shrink-0"><input type="checkbox" name="items[__i__][remove_image]" value="1" class="rounded border-slate-300">${REMOVE_LABEL}</label>` : ''}
            </div>
        `;

        if (SECTION_TYPE !== 'contact_info') {
            const slot = row.querySelector('.body-slot');
            slot.innerHTML = `
                <div x-show="locale === 'en'">${richTextHtml('items[__i__][body_en]', data.body_en, 'ltr')}</div>
                <div x-show="locale === 'ar'">${richTextHtml('items[__i__][body_ar]', data.body_ar, 'rtl')}</div>
            `;
        } else {
            const slot = row.querySelector('.body-slot');
            slot.innerHTML = `
                <input x-show="locale === 'en'" type="text" name="items[__i__][body_en]" value="${data.body_en ?? ''}" placeholder="{{ __('Value (English) — e.g. an email or phone number') }}" class="w-full rounded-lg border border-slate-200 text-sm focus:border-brand-500 focus:ring-brand-500">
                <input x-show="locale === 'ar'" type="text" name="items[__i__][body_ar]" value="${data.body_ar ?? ''}" dir="rtl" placeholder="{{ __('Value (Arabic)') }}" class="w-full rounded-lg border border-slate-200 text-sm focus:border-brand-500 focus:ring-brand-500">
            `;
        }

        row.querySelector('.remove-row').addEventListener('click', () => {
            row.remove();
            renumberRows();
        });

        const handle = row.querySelector('.drag-handle');
        handle.addEventListener('mousedown', () => { row.draggable = true; });
        row.addEventListener('dragend', () => {
            row.draggable = false;
            row.classList.remove('opacity-40');
            renumberRows();
        });
        row.addEventListener('dragstart', (e) => {
            e.dataTransfer.effectAllowed = 'move';
            row.classList.add('opacity-40');
        });

        rowsWrap.appendChild(row);
        renumberRows();
    }

    function dragAfterElement(y) {
        const rows = [...rowsWrap.querySelectorAll('.item-row:not(.opacity-40)')];
        return rows.reduce((closest, child) => {
            const box = child.getBoundingClientRect();
            const offset = y - box.top - box.height / 2;
            if (offset < 0 && offset > closest.offset) {
                return { offset, element: child };
            }
            return closest;
        }, { offset: Number.NEGATIVE_INFINITY, element: null }).element;
    }

    rowsWrap.addEventListener('dragover', (e) => {
        e.preventDefault();
        const dragging = rowsWrap.querySelector('.opacity-40');
        if (!dragging) return;
        const after = dragAfterElement(e.clientY);
        if (after == null) {
            rowsWrap.appendChild(dragging);
        } else {
            rowsWrap.insertBefore(dragging, after);
        }
    });

    document.getElementById('add-item').addEventListener('click', () => addRow());

    EXISTING.forEach(addRow);
    refreshEmptyHint();
})();
</script>
@endif
@endsection
