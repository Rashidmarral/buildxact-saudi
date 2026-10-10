@extends('layouts.app')

@section('title', __('Custom fields'))

@section('content')
<div class="flex items-center justify-between mb-6">
    <div>
        <h1 class="text-2xl font-bold text-slate-900">{{ __('Custom fields') }}</h1>
        <p class="text-sm text-slate-500 mt-1">{{ __('Add your own fields to clients, suppliers, and items — they show up on every create/edit form.') }}</p>
    </div>
    <a href="{{ route('app.settings.index') }}" class="rounded-lg border border-slate-200 px-4 py-2 text-sm font-semibold text-slate-600 hover:border-slate-300">{{ __('Back to settings') }}</a>
</div>

<div x-data="{ tab: 'client' }">
    <div class="flex gap-1 border-b border-slate-200 mb-6">
        @foreach ($entityTypes as $key => $label)
            <button type="button" @click="tab = '{{ $key }}'"
                :class="tab === '{{ $key }}' ? 'border-brand-600 text-brand-700' : 'border-transparent text-slate-500 hover:text-slate-700'"
                class="px-4 py-2.5 text-sm font-semibold border-b-2 -mb-px transition-colors">{{ __($label) }}</button>
        @endforeach
    </div>

    @foreach ($entityTypes as $entityKey => $entityLabel)
        <div x-show="tab === '{{ $entityKey }}'" x-cloak>
            <div class="flex items-center justify-end mb-4">
                <button type="button"
                    onclick="document.getElementById('add-field-entity-type').value = '{{ $entityKey }}'; document.getElementById('add-field-options-wrap').classList.add('hidden'); document.getElementById('add-field-modal').showModal();"
                    class="rounded-lg bg-brand-600 px-4 py-2 text-sm font-semibold text-white hover:bg-brand-700">
                    {{ __('+ Add field') }}
                </button>
            </div>

            <div class="bg-white rounded-xl border border-slate-100">
                @php($definitions = $definitionsByEntity->get($entityKey, collect()))
                @if ($definitions->isEmpty())
                    <p class="px-6 py-8 text-sm text-slate-500">{{ __('No custom fields yet for :entity.', ['entity' => __($entityLabel)]) }}</p>
                @else
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="text-left text-slate-500 border-b border-slate-100">
                                <th class="px-6 py-3 font-medium">{{ __('Label') }}</th>
                                <th class="px-6 py-3 font-medium">{{ __('Type') }}</th>
                                <th class="px-6 py-3 font-medium">{{ __('Required') }}</th>
                                <th class="px-6 py-3 font-medium">{{ __('Active') }}</th>
                                <th class="px-6 py-3"></th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($definitions as $definition)
                                <tr class="border-b border-slate-50 last:border-0 hover:bg-slate-50">
                                    <td class="px-6 py-3">
                                        <span class="font-medium text-slate-800">{{ $definition->label }}</span>
                                        <p class="text-xs text-slate-400">{{ $definition->key }}</p>
                                    </td>
                                    <td class="px-6 py-3 text-slate-500">{{ __($fieldTypes[$definition->field_type] ?? $definition->field_type) }}</td>
                                    <td class="px-6 py-3 text-slate-500">{{ $definition->is_required ? __('Yes') : __('No') }}</td>
                                    <td class="px-6 py-3">
                                        @if ($definition->is_active)
                                            <span class="inline-block rounded-full bg-emerald-50 text-emerald-700 text-xs font-medium px-2.5 py-1">{{ __('Active') }}</span>
                                        @else
                                            <span class="inline-block rounded-full bg-slate-100 text-slate-500 text-xs font-medium px-2.5 py-1">{{ __('Inactive') }}</span>
                                        @endif
                                    </td>
                                    <td class="px-6 py-3 text-right space-x-3 rtl:space-x-reverse">
                                        <button type="button" class="text-brand-700 hover:underline"
                                            data-edit-field
                                            data-update-url="{{ route('app.settings.custom-fields.update', $definition) }}"
                                            data-label="{{ $definition->label }}"
                                            data-field-type="{{ $definition->field_type }}"
                                            data-options="{{ implode("\n", $definition->optionsList()) }}"
                                            data-is-required="{{ $definition->is_required ? '1' : '0' }}"
                                            data-is-active="{{ $definition->is_active ? '1' : '0' }}"
                                        >{{ __('Edit') }}</button>
                                        <form method="POST" action="{{ route('app.settings.custom-fields.destroy', $definition) }}" class="inline" onsubmit="return confirm('{{ __('Delete this custom field? Any saved values for it will also be deleted.') }}')">
                                            @csrf @method('DELETE')
                                            <button type="submit" class="text-red-600 hover:underline">{{ __('Delete') }}</button>
                                        </form>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                @endif
            </div>
        </div>
    @endforeach
</div>

<dialog id="add-field-modal" class="rounded-2xl border border-slate-100 p-0 w-full max-w-md backdrop:bg-slate-900/40">
    <form method="POST" action="{{ route('app.settings.custom-fields.store') }}" class="p-6 space-y-4">
        @csrf
        <input type="hidden" name="entity_type" id="add-field-entity-type" value="client">
        <div class="flex items-start justify-between">
            <h3 class="text-lg font-bold text-slate-900">{{ __('Add field') }}</h3>
            <button type="button" onclick="document.getElementById('add-field-modal').close()" class="text-slate-400 hover:text-slate-600">✕</button>
        </div>
        <div>
            <label class="block text-xs font-medium text-slate-500">{{ __('Label') }}</label>
            <input type="text" name="label" required class="mt-1 w-full rounded-lg border border-slate-200 text-sm focus:border-brand-500 focus:ring-brand-500">
        </div>
        <div>
            <label class="block text-xs font-medium text-slate-500">{{ __('Field type') }}</label>
            <select name="field_type" id="add-field-type" onchange="document.getElementById('add-field-options-wrap').classList.toggle('hidden', this.value !== 'select')" class="mt-1 w-full rounded-lg border border-slate-200 text-sm focus:border-brand-500 focus:ring-brand-500">
                @foreach ($fieldTypes as $key => $label)
                    <option value="{{ $key }}">{{ __($label) }}</option>
                @endforeach
            </select>
        </div>
        <div id="add-field-options-wrap" class="hidden">
            <label class="block text-xs font-medium text-slate-500">{{ __('Options (one per line)') }}</label>
            <textarea name="options" rows="4" class="mt-1 w-full rounded-lg border border-slate-200 text-sm focus:border-brand-500 focus:ring-brand-500"></textarea>
        </div>
        <label class="flex items-center gap-2 text-sm text-slate-600">
            <input type="checkbox" name="is_required" value="1" class="rounded border-slate-300 text-brand-600 focus:ring-brand-500">
            {{ __('Required') }}
        </label>
        <div class="flex gap-3">
            <button type="button" onclick="document.getElementById('add-field-modal').close()" class="flex-1 rounded-lg border border-slate-200 px-4 py-2 text-sm font-semibold text-slate-600 hover:border-slate-300">{{ __('Cancel') }}</button>
            <button type="submit" class="flex-1 rounded-lg bg-brand-600 px-4 py-2 text-sm font-semibold text-white hover:bg-brand-700">{{ __('Save') }}</button>
        </div>
    </form>
</dialog>

<dialog id="edit-field-modal" class="rounded-2xl border border-slate-100 p-0 w-full max-w-md backdrop:bg-slate-900/40">
    <form method="POST" id="edit-field-form" class="p-6 space-y-4">
        @csrf
        @method('PUT')
        <div class="flex items-start justify-between">
            <h3 class="text-lg font-bold text-slate-900">{{ __('Edit field') }}</h3>
            <button type="button" onclick="document.getElementById('edit-field-modal').close()" class="text-slate-400 hover:text-slate-600">✕</button>
        </div>
        <div>
            <label class="block text-xs font-medium text-slate-500">{{ __('Label') }}</label>
            <input type="text" name="label" id="edit-field-label" required class="mt-1 w-full rounded-lg border border-slate-200 text-sm focus:border-brand-500 focus:ring-brand-500">
        </div>
        <div id="edit-field-options-wrap" class="hidden">
            <label class="block text-xs font-medium text-slate-500">{{ __('Options (one per line)') }}</label>
            <textarea name="options" id="edit-field-options" rows="4" class="mt-1 w-full rounded-lg border border-slate-200 text-sm focus:border-brand-500 focus:ring-brand-500"></textarea>
        </div>
        <label class="flex items-center gap-2 text-sm text-slate-600">
            <input type="checkbox" name="is_required" id="edit-field-required" value="1" class="rounded border-slate-300 text-brand-600 focus:ring-brand-500">
            {{ __('Required') }}
        </label>
        <label class="flex items-center gap-2 text-sm text-slate-600">
            <input type="checkbox" name="is_active" id="edit-field-active" value="1" class="rounded border-slate-300 text-brand-600 focus:ring-brand-500">
            {{ __('Active') }}
        </label>
        <div class="flex gap-3">
            <button type="button" onclick="document.getElementById('edit-field-modal').close()" class="flex-1 rounded-lg border border-slate-200 px-4 py-2 text-sm font-semibold text-slate-600 hover:border-slate-300">{{ __('Cancel') }}</button>
            <button type="submit" class="flex-1 rounded-lg bg-brand-600 px-4 py-2 text-sm font-semibold text-white hover:bg-brand-700">{{ __('Save') }}</button>
        </div>
    </form>
</dialog>

<script>
document.querySelectorAll('[data-edit-field]').forEach(btn => {
    btn.addEventListener('click', () => {
        document.getElementById('edit-field-form').action = btn.dataset.updateUrl;
        document.getElementById('edit-field-label').value = btn.dataset.label || '';
        document.getElementById('edit-field-options').value = btn.dataset.options || '';
        document.getElementById('edit-field-options-wrap').classList.toggle('hidden', btn.dataset.fieldType !== 'select');
        document.getElementById('edit-field-required').checked = btn.dataset.isRequired === '1';
        document.getElementById('edit-field-active').checked = btn.dataset.isActive === '1';
        document.getElementById('edit-field-modal').showModal();
    });
});
</script>
@endsection
