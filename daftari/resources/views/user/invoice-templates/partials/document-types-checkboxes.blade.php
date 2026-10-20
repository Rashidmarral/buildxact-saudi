{{--
    Shared "Applies to" multi-select — pick a design once, then tick
    every document type it should be the active template for. Unticking
    a type and saving removes this design from it (the company-wide
    "all" default, or the built-in look, takes back over immediately).
    Params: $documentTypes (array of type keys), $typeLabels (key =>
    label), $checked (array of currently-selected type keys, default []).
--}}
@php($checked = $checked ?? [])
<div class="grid grid-cols-2 gap-x-3 gap-y-1.5 rounded-lg border border-slate-200 p-3">
    @foreach ($documentTypes as $dt)
        <label class="flex items-center gap-2 text-sm text-slate-700">
            <input type="checkbox" name="document_types[]" value="{{ $dt }}" @checked(in_array($dt, $checked, true)) class="rounded border-slate-300 text-brand-600 focus:ring-brand-500">
            {{ $typeLabels[$dt] ?? $dt }}
        </label>
    @endforeach
</div>
