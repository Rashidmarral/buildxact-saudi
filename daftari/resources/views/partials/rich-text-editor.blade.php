{{--
    A minimal rich-text field: bold/italic/link/bullet-list/numbered-list
    over a contenteditable div, mirrored into a hidden textarea so it POSTs
    like any other form field. See resources/js/app.js's `richTextEditor`
    Alpine component for the wiring and App\Support\RichText for the
    server-side sanitization this always goes through on save.

    Required: $name (textarea name attribute), $value (initial HTML)
    Optional: $dir ('ltr'|'rtl', default 'ltr'), $placeholder
--}}
@php
    $dir = $dir ?? 'ltr';
@endphp
<div x-data="richTextEditor(@js($value ?? ''), @js(__('Enter a link URL:')))" class="overflow-hidden rounded-lg border border-slate-200 focus-within:border-brand-500 focus-within:ring-1 focus-within:ring-brand-500">
    <div class="flex items-center gap-0.5 border-b border-slate-100 bg-slate-50/60 px-1.5 py-1">
        <button type="button" @click="exec('bold')" class="rte-btn" title="{{ __('Bold') }}"><span class="font-bold">B</span></button>
        <button type="button" @click="exec('italic')" class="rte-btn" title="{{ __('Italic') }}"><span class="italic">I</span></button>
        <span class="mx-0.5 h-4 w-px bg-slate-200"></span>
        <button type="button" @click="exec('insertUnorderedList')" class="rte-btn" title="{{ __('Bullet list') }}">
            <svg class="h-3.5 w-3.5" viewBox="0 0 20 20" fill="currentColor"><circle cx="3" cy="5" r="1.3"/><circle cx="3" cy="10" r="1.3"/><circle cx="3" cy="15" r="1.3"/><rect x="7" y="4.3" width="11" height="1.4" rx="0.7"/><rect x="7" y="9.3" width="11" height="1.4" rx="0.7"/><rect x="7" y="14.3" width="11" height="1.4" rx="0.7"/></svg>
        </button>
        <button type="button" @click="exec('insertOrderedList')" class="rte-btn" title="{{ __('Numbered list') }}">
            <svg class="h-3.5 w-3.5" viewBox="0 0 20 20" fill="currentColor"><text x="0" y="6.5" font-size="5" font-weight="700">1</text><text x="0" y="11.5" font-size="5" font-weight="700">2</text><text x="0" y="16.5" font-size="5" font-weight="700">3</text><rect x="7" y="4.3" width="11" height="1.4" rx="0.7"/><rect x="7" y="9.3" width="11" height="1.4" rx="0.7"/><rect x="7" y="14.3" width="11" height="1.4" rx="0.7"/></svg>
        </button>
        <span class="mx-0.5 h-4 w-px bg-slate-200"></span>
        <button type="button" @click="link()" class="rte-btn" title="{{ __('Insert link') }}">
            <svg class="h-3.5 w-3.5" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round"><path d="M8 12a3.5 3.5 0 003.5 3.5H14a3.5 3.5 0 000-7h-1"/><path d="M12 8a3.5 3.5 0 00-3.5-3.5H6a3.5 3.5 0 000 7h1"/></svg>
        </button>
    </div>
    <div
        x-ref="editable"
        @input="sync()"
        contenteditable="true"
        dir="{{ $dir }}"
        data-placeholder="{{ $placeholder ?? '' }}"
        class="rte-editable min-h-[100px] px-3 py-2 text-sm text-slate-700 focus:outline-none"
    ></div>
    <textarea x-ref="textarea" name="{{ $name }}" class="hidden">{{ $value }}</textarea>
</div>
