@extends('layouts.app')

@section('title', $letter->exists ? __('Edit Letter') : __('New Letter'))

@section('content')
<div class="max-w-4xl">
    <h1 class="text-xl font-bold text-slate-900 mb-1">{{ $letter->exists ? __('Edit Letter') : __('New Letter') }}</h1>
    <p class="text-sm text-slate-500 mb-6">{{ __('Every paragraph below is a starting point — add, edit, or remove any of them before downloading.') }}</p>

    @unless ($letter->exists)
        <div class="flex flex-wrap gap-2 mb-6">
            @foreach ($kinds as $kind)
                <a href="{{ route('app.machinery.letters.create', array_merge(request()->except('document_type'), ['document_type' => $kind])) }}"
                   class="rounded-full px-3 py-1.5 text-xs font-semibold {{ $letter->document_type === $kind ? 'bg-brand-600 text-white' : 'bg-white border border-slate-200 text-slate-600 hover:border-slate-300' }}">
                    {{ \App\Support\LetterPresets::label($kind) }}
                </a>
            @endforeach
        </div>
    @endunless

    <form method="POST" action="{{ $letter->exists ? route('app.machinery.letters.update', $letter) : route('app.machinery.letters.store') }}" class="space-y-5">
        @csrf
        @if ($letter->exists) @method('PUT') @endif
        <input type="hidden" name="document_type" value="{{ old('document_type', $letter->document_type) }}">

        <div class="bg-white rounded-xl border border-slate-100 p-6 space-y-4">
            <h3 class="font-semibold text-slate-900">{{ __('Header') }}</h3>
            <div class="grid sm:grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-semibold uppercase text-slate-500">{{ __('Title (English)') }}</label>
                    <input type="text" name="title" value="{{ old('title', $letter->title) }}" required class="mt-1 w-full rounded-lg border border-slate-200 focus:border-brand-500 focus:ring-brand-500">
                </div>
                <div>
                    <label class="block text-xs font-semibold uppercase text-slate-500">{{ __('Title (Arabic)') }}</label>
                    <input type="text" name="title_ar" dir="rtl" value="{{ old('title_ar', $letter->title_ar) }}" class="mt-1 w-full rounded-lg border border-slate-200 focus:border-brand-500 focus:ring-brand-500">
                </div>
                <div>
                    <label class="block text-xs font-semibold uppercase text-slate-500">{{ __('Date') }}</label>
                    <input type="date" name="letter_date" value="{{ old('letter_date', optional($letter->letter_date)->toDateString() ?? now()->toDateString()) }}" required class="mt-1 w-full rounded-lg border border-slate-200 focus:border-brand-500 focus:ring-brand-500">
                </div>
            </div>

            <div class="grid sm:grid-cols-3 gap-4">
                <div>
                    <label class="block text-xs font-semibold uppercase text-slate-500">{{ __('Machine (optional)') }}</label>
                    <select name="machinery_asset_id" class="mt-1 w-full rounded-lg border border-slate-200 focus:border-brand-500 focus:ring-brand-500">
                        <option value="">{{ __('None') }}</option>
                        @foreach ($machinery as $asset)
                            <option value="{{ $asset->id }}" @selected(old('machinery_asset_id', $letter->machinery_asset_id) == $asset->id)>{{ $asset->name }} ({{ $asset->asset_code }})</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-semibold uppercase text-slate-500">{{ __('Project (optional)') }}</label>
                    <select name="project_id" class="mt-1 w-full rounded-lg border border-slate-200 focus:border-brand-500 focus:ring-brand-500">
                        <option value="">{{ __('None') }}</option>
                        @foreach ($projects as $project)
                            <option value="{{ $project->id }}" @selected(old('project_id', $letter->project_id) == $project->id)>{{ $project->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-semibold uppercase text-slate-500">{{ __('Language') }}</label>
                    <select name="language_mode" class="mt-1 w-full rounded-lg border border-slate-200 focus:border-brand-500 focus:ring-brand-500">
                        <option value="bilingual" @selected(old('language_mode', $letter->language_mode) === 'bilingual')>{{ __('Bilingual (AR/EN)') }}</option>
                        <option value="english_only" @selected(old('language_mode', $letter->language_mode) === 'english_only')>{{ __('English only') }}</option>
                        <option value="arabic_only" @selected(old('language_mode', $letter->language_mode) === 'arabic_only')>{{ __('Arabic only') }}</option>
                    </select>
                </div>
            </div>
        </div>

        <div class="bg-white rounded-xl border border-slate-100 p-6 space-y-4">
            <h3 class="font-semibold text-slate-900">{{ __('Parties') }}</h3>
            <div class="grid sm:grid-cols-2 gap-4">
                <div class="space-y-3">
                    <div>
                        <label class="block text-xs font-semibold uppercase text-slate-500">{{ __('Our role') }}</label>
                        <input type="text" name="party_a_role" value="{{ old('party_a_role', $letter->party_a_role) }}" required class="mt-1 w-full rounded-lg border border-slate-200 focus:border-brand-500 focus:ring-brand-500">
                    </div>
                    <div>
                        <label class="block text-xs font-semibold uppercase text-slate-500">{{ __('Our name (optional — defaults to company name)') }}</label>
                        <input type="text" name="party_a_name" value="{{ old('party_a_name', $letter->party_a_name) }}" class="mt-1 w-full rounded-lg border border-slate-200 focus:border-brand-500 focus:ring-brand-500">
                    </div>
                </div>
                <div class="space-y-3">
                    <div>
                        <label class="block text-xs font-semibold uppercase text-slate-500">{{ __('Their role') }}</label>
                        <input type="text" name="party_b_role" value="{{ old('party_b_role', $letter->party_b_role) }}" required class="mt-1 w-full rounded-lg border border-slate-200 focus:border-brand-500 focus:ring-brand-500">
                    </div>
                    <div>
                        <label class="block text-xs font-semibold uppercase text-slate-500">{{ __('Their name') }}</label>
                        <input type="text" name="party_b_name" value="{{ old('party_b_name', $letter->party_b_name) }}" required class="mt-1 w-full rounded-lg border border-slate-200 focus:border-brand-500 focus:ring-brand-500">
                    </div>
                </div>
            </div>
            <div class="grid sm:grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-semibold uppercase text-slate-500">{{ __('Link to a customer on file (optional)') }}</label>
                    <select name="client_id" class="mt-1 w-full rounded-lg border border-slate-200 focus:border-brand-500 focus:ring-brand-500">
                        <option value="">{{ __('None') }}</option>
                        @foreach ($clients as $client)
                            <option value="{{ $client->id }}" @selected(old('client_id', $letter->client_id) == $client->id)>{{ $client->display_name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-semibold uppercase text-slate-500">{{ __('Link to a supplier on file (optional)') }}</label>
                    <select name="supplier_id" class="mt-1 w-full rounded-lg border border-slate-200 focus:border-brand-500 focus:ring-brand-500">
                        <option value="">{{ __('None') }}</option>
                        @foreach ($suppliers as $supplier)
                            <option value="{{ $supplier->id }}" @selected(old('supplier_id', $letter->supplier_id) == $supplier->id)>{{ $supplier->display_name }}</option>
                        @endforeach
                    </select>
                </div>
            </div>
            <div>
                <label class="block text-xs font-semibold uppercase text-slate-500">{{ __('Their address / CR / VAT details (optional)') }}</label>
                <textarea name="party_b_details" rows="2" class="mt-1 w-full rounded-lg border border-slate-200 focus:border-brand-500 focus:ring-brand-500">{{ old('party_b_details', $letter->party_b_details) }}</textarea>
            </div>
        </div>

        <div class="bg-white rounded-xl border border-slate-100 p-6">
            <div class="flex items-center justify-between mb-3">
                <h3 class="font-semibold text-slate-900">{{ __('Content') }}</h3>
                <button type="button" id="add-block" class="text-xs font-semibold text-brand-700 hover:underline">{{ __('+ Add paragraph') }}</button>
            </div>
            <div id="blocks" class="space-y-4"></div>
        </div>

        <button type="submit" class="rounded-lg bg-brand-600 px-6 py-2.5 text-sm font-semibold text-white hover:bg-brand-700">{{ $letter->exists ? __('Save changes') : __('Generate letter') }}</button>
    </form>
</div>

@php
    $initialBlocks = old('content', $letter->content ?: [['text_en' => '', 'text_ar' => '', 'is_heading' => false]]);
@endphp
<script>
(function () {
    const container = document.getElementById('blocks');
    const initial = @json($initialBlocks);

    // A monotonically increasing counter (never reused, unlike DOM child
    // count) so every block keeps one explicit index shared by all three
    // of its fields (text_en/text_ar/is_heading) — an unchecked "heading"
    // checkbox simply submits nothing for that index, which is fine, but
    // two blocks must never collide on the same index or PHP would merge
    // one block's fields into another's after a mid-list removal.
    let nextIndex = 0;

    function addBlock(block) {
        block = block || { text_en: '', text_ar: '', is_heading: false };
        const index = nextIndex++;
        const wrapper = document.createElement('div');
        wrapper.className = 'border border-slate-100 rounded-lg p-4';
        wrapper.innerHTML = `
            <div class="flex items-center justify-between mb-2">
                <label class="flex items-center gap-2 text-xs text-slate-500">
                    <input type="checkbox" name="content[${index}][is_heading]" value="1" ${block.is_heading ? 'checked' : ''}>
                    ${@json(__('Bold heading'))}
                </label>
                <button type="button" class="remove-block text-xs text-red-600 hover:underline">${@json(__('Remove'))}</button>
            </div>
            <div class="grid sm:grid-cols-2 gap-3">
                <div>
                    <label class="block text-xs font-semibold uppercase text-slate-400">${@json(__('English'))}</label>
                    <textarea name="content[${index}][text_en]" rows="3" class="mt-1 w-full rounded-lg border border-slate-200 focus:border-brand-500 focus:ring-brand-500"></textarea>
                </div>
                <div>
                    <label class="block text-xs font-semibold uppercase text-slate-400">${@json(__('Arabic'))}</label>
                    <textarea name="content[${index}][text_ar]" dir="rtl" rows="3" class="mt-1 w-full rounded-lg border border-slate-200 focus:border-brand-500 focus:ring-brand-500"></textarea>
                </div>
            </div>
        `;
        container.appendChild(wrapper);
        wrapper.querySelector('textarea[name$="[text_en]"]').value = block.text_en || '';
        wrapper.querySelector('textarea[name$="[text_ar]"]').value = block.text_ar || '';
        wrapper.querySelector('.remove-block').addEventListener('click', () => wrapper.remove());
    }

    initial.forEach(addBlock);
    document.getElementById('add-block').addEventListener('click', () => addBlock());
})();
</script>
@endsection
