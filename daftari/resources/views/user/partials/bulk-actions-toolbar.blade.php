{{--
    Expects: $formId (unique per page, e.g. "bulk-invoices"), $exportRoute,
    optional $destroyRoute + $destroyLabel + $destroyConfirm. Must be placed
    inside an element carrying x-data="bulkSelect()".

    The hidden form lives outside the toolbar/table markup (referenced by
    the buttons below via the HTML5 form="" attribute) so pages whose rows
    already have their own per-row <form> (Clients, Items — inline Delete)
    never end up with a <form> nested inside another <form>, which browsers
    silently break.
--}}
<form id="{{ $formId }}" method="POST">
    @csrf
    <template x-for="id in selected" :key="id">
        <input type="hidden" name="ids[]" :value="id">
    </template>
</form>

<div x-show="selected.length > 0" x-cloak class="mb-3 flex flex-wrap items-center gap-3 rounded-lg border border-brand-200 bg-brand-50 px-4 py-2 text-sm">
    <span class="font-medium text-brand-800" x-text="selected.length + ' {{ __('selected') }}'"></span>
    <button type="submit" form="{{ $formId }}" formaction="{{ $exportRoute }}" class="rounded-lg border border-slate-200 bg-white px-3 py-1.5 text-xs font-semibold text-slate-600 hover:border-slate-300">{{ __('Export selected (CSV)') }}</button>
    @isset($destroyRoute)
        <button type="submit" form="{{ $formId }}" formaction="{{ $destroyRoute }}" onclick="return confirm('{{ $destroyConfirm }}')" class="rounded-lg border border-red-200 bg-white px-3 py-1.5 text-xs font-semibold text-red-600 hover:bg-red-50">{{ $destroyLabel }}</button>
    @endisset
</div>
