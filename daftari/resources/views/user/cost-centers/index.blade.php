@extends('layouts.app')

@section('title', __('Cost Centers'))

@php
    $typeLabels = [
        'department' => __('Department'),
        'project' => __('Project'),
        'branch' => __('Branch'),
    ];
    $roots = $costCenters->whereNull('parent_id');
    $renderTree = function ($nodes, $depth = 0) use (&$renderTree, $costCenters, $typeLabels) {
        foreach ($nodes as $node) {
            $children = $costCenters->where('parent_id', $node->id);
            echo '<div class="flex items-center justify-between px-5 py-3" style="padding-inline-start: '.(1.25 + $depth * 1.5).'rem">';
            echo '<div><span class="font-semibold text-slate-900">'.e($node->code.' '.$node->name).'</span>';
            echo ' <span class="text-xs text-slate-400">'.e($typeLabels[$node->type] ?? $node->type).'</span>';
            if (! $node->is_active) echo ' <span class="rounded-full bg-red-50 px-2 py-0.5 text-[10px] font-semibold text-red-500">'.e(__('Inactive')).'</span>';
            echo '</div>';
            echo '<div class="flex items-center gap-3 text-xs">';
            echo '<button type="button" onclick="openEditCc('.$node->id.', \''.e(addslashes($node->code)).'\', \''.e(addslashes($node->name)).'\', \''.e(addslashes($node->name_ar)).'\', \''.$node->type.'\', '.($node->parent_id ?? 'null').', '.($node->is_active ? 'true' : 'false').')" class="text-brand-700 hover:underline">'.e(__('Edit')).'</button>';
            echo '</div></div>';
            if ($children->isNotEmpty()) $renderTree($children, $depth + 1);
        }
    };
@endphp

@section('content')
@include('user.accounting.partials.tabs')

<div class="flex items-center justify-between mb-6">
    <div>
        <h2 class="text-lg font-semibold text-slate-900">{{ __('Cost Centers') }}</h2>
        <p class="text-sm text-slate-500 mt-1">{{ __('Manage cost centers and document allocations.') }}</p>
    </div>
    <button type="button" onclick="document.getElementById('add-cc-modal').showModal()" class="rounded-lg bg-brand-600 px-4 py-2 text-sm font-semibold text-white hover:bg-brand-700">+ {{ __('Add Cost Center') }}</button>
</div>

<div class="flex items-center gap-2 mb-4 text-sm">
    <a href="{{ route('app.cost-centers.index', ['view' => 'list']) }}" class="rounded-lg px-3 py-1.5 {{ $view === 'list' ? 'bg-slate-900 text-white' : 'text-slate-600 hover:bg-slate-100' }}">{{ __('List View') }}</a>
    <a href="{{ route('app.cost-centers.index', ['view' => 'tree']) }}" class="rounded-lg px-3 py-1.5 {{ $view === 'tree' ? 'bg-slate-900 text-white' : 'text-slate-600 hover:bg-slate-100' }}">{{ __('Tree View') }}</a>
</div>

<div class="bg-white rounded-xl border border-slate-100 divide-y divide-slate-50 mb-8">
    @if ($costCenters->isEmpty())
        <p class="px-5 py-10 text-center text-sm text-slate-400">{{ __('No cost centers yet.') }}</p>
    @elseif ($view === 'tree')
        @php($renderTree($roots))
    @else
        @foreach ($costCenters as $cc)
            <div class="flex items-center justify-between px-5 py-3">
                <div>
                    <span class="font-semibold text-slate-900">{{ $cc->code }} {{ $cc->name }}</span>
                    <span class="text-xs text-slate-400">{{ $typeLabels[$cc->type] ?? $cc->type }}</span>
                    @if ($cc->parent)<span class="text-xs text-slate-400">· {{ __('under') }} {{ $cc->parent->name }}</span>@endif
                    @unless ($cc->is_active)<span class="ms-2 rounded-full bg-red-50 px-2 py-0.5 text-[10px] font-semibold text-red-500">{{ __('Inactive') }}</span>@endunless
                </div>
                <div class="flex items-center gap-3 text-xs">
                    <button type="button" onclick="openEditCc({{ $cc->id }}, '{{ addslashes($cc->code) }}', '{{ addslashes($cc->name) }}', '{{ addslashes($cc->name_ar) }}', '{{ $cc->type }}', {{ $cc->parent_id ?? 'null' }}, {{ $cc->is_active ? 'true' : 'false' }})" class="text-brand-700 hover:underline">{{ __('Edit') }}</button>
                    <form method="POST" action="{{ route('app.cost-centers.destroy', $cc) }}" onsubmit="return confirm('{{ __('Delete this cost center?') }}')">
                        @csrf @method('DELETE')
                        <button type="submit" class="text-red-600 hover:underline">{{ __('Delete') }}</button>
                    </form>
                </div>
            </div>
        @endforeach
    @endif
</div>

<div class="bg-white rounded-xl border border-slate-100 p-6">
    <h3 class="font-semibold text-slate-900 mb-1">{{ __('Cost Center Links') }}</h3>
    <p class="text-sm text-slate-500 mb-4">{{ __('Link entities to cost centers with an allocation percentage.') }}</p>

    <form method="POST" action="{{ route('app.cost-centers.links.store') }}" class="flex flex-wrap items-end gap-3 mb-6">
        @csrf
        <div>
            <label class="block text-xs font-medium text-slate-500">{{ __('Entity type') }}</label>
            <select name="entity_type" class="mt-1 rounded-lg border border-slate-200 text-sm focus:border-brand-500 focus:ring-brand-500">
                <option value="invoice">{{ __('Invoice') }}</option>
            </select>
        </div>
        <div>
            <label class="block text-xs font-medium text-slate-500">{{ __('Document') }}</label>
            <select name="entity_id" class="mt-1 rounded-lg border border-slate-200 text-sm focus:border-brand-500 focus:ring-brand-500 min-w-[10rem]">
                @foreach ($invoices as $invoice)
                    <option value="{{ $invoice->id }}">{{ $invoice->invoice_number }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label class="block text-xs font-medium text-slate-500">{{ __('Cost center') }}</label>
            <select name="cost_center_id" class="mt-1 rounded-lg border border-slate-200 text-sm focus:border-brand-500 focus:ring-brand-500 min-w-[12rem]">
                @foreach ($costCenters->where('is_active', true) as $cc)
                    <option value="{{ $cc->id }}">{{ $cc->label() }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label class="block text-xs font-medium text-slate-500">{{ __('Allocation %') }}</label>
            <input type="number" step="0.01" min="0.01" max="100" name="allocation_percent" value="100" class="mt-1 w-24 rounded-lg border border-slate-200 text-sm focus:border-brand-500 focus:ring-brand-500">
        </div>
        <button type="submit" class="rounded-lg bg-brand-600 px-4 py-2 text-sm font-semibold text-white hover:bg-brand-700">{{ __('Save') }}</button>
    </form>

    @if ($links->isEmpty())
        <p class="text-sm text-slate-400">{{ __('No cost center links found.') }}</p>
    @else
        <div class="divide-y divide-slate-50">
            @foreach ($links as $link)
                <div class="flex items-center justify-between py-2 text-sm">
                    <div>
                        <span class="font-medium text-slate-800">{{ ucfirst($link->entity_type) }} {{ $link->entity_label }}</span>
                        <span class="text-slate-400"> &rarr; {{ $link->costCenter->label() }} ({{ number_format($link->allocation_percent, 2) }}%)</span>
                    </div>
                    <form method="POST" action="{{ route('app.cost-centers.links.destroy', $link) }}">
                        @csrf @method('DELETE')
                        <button type="submit" class="text-red-600 hover:underline text-xs">{{ __('Remove') }}</button>
                    </form>
                </div>
            @endforeach
        </div>
    @endif
</div>

<dialog id="add-cc-modal" class="rounded-2xl border border-slate-100 p-0 w-full max-w-md backdrop:bg-slate-900/40">
    <form method="POST" action="{{ route('app.cost-centers.store') }}" class="p-6 space-y-4">
        @csrf
        <div class="flex items-start justify-between">
            <h3 class="text-lg font-bold text-slate-900">{{ __('Add Cost Center') }}</h3>
            <button type="button" onclick="document.getElementById('add-cc-modal').close()" class="text-slate-400 hover:text-slate-600">✕</button>
        </div>
        <div class="grid grid-cols-2 gap-3">
            <div><label class="block text-xs font-medium text-slate-500">{{ __('Cost center code') }}</label><input type="text" name="code" required class="mt-1 w-full rounded-lg border border-slate-200 text-sm focus:border-brand-500 focus:ring-brand-500"></div>
            <div><label class="block text-xs font-medium text-slate-500">{{ __('Cost center name') }}</label><input type="text" name="name" required class="mt-1 w-full rounded-lg border border-slate-200 text-sm focus:border-brand-500 focus:ring-brand-500"></div>
        </div>
        <div><label class="block text-xs font-medium text-slate-500">{{ __('Name (Arabic)') }}</label><input type="text" name="name_ar" class="mt-1 w-full rounded-lg border border-slate-200 text-sm focus:border-brand-500 focus:ring-brand-500"></div>
        <div class="grid grid-cols-2 gap-3">
            <div>
                <label class="block text-xs font-medium text-slate-500">{{ __('Cost center type') }}</label>
                <select name="type" class="mt-1 w-full rounded-lg border border-slate-200 text-sm focus:border-brand-500 focus:ring-brand-500">
                    @foreach ($types as $t)<option value="{{ $t }}">{{ $typeLabels[$t] }}</option>@endforeach
                </select>
            </div>
            <div>
                <label class="block text-xs font-medium text-slate-500">{{ __('Parent cost center') }}</label>
                <select name="parent_id" class="mt-1 w-full rounded-lg border border-slate-200 text-sm focus:border-brand-500 focus:ring-brand-500">
                    <option value="">{{ __('None') }}</option>
                    @foreach ($costCenters as $cc)<option value="{{ $cc->id }}">{{ $cc->label() }}</option>@endforeach
                </select>
            </div>
        </div>
        <button type="submit" class="w-full rounded-lg bg-brand-600 px-4 py-2 text-sm font-semibold text-white hover:bg-brand-700">{{ __('Add Cost Center') }}</button>
    </form>
</dialog>

<dialog id="edit-cc-modal" class="rounded-2xl border border-slate-100 p-0 w-full max-w-md backdrop:bg-slate-900/40">
    <form method="POST" id="edit-cc-form" class="p-6 space-y-4">
        @csrf @method('PUT')
        <div class="flex items-start justify-between">
            <h3 class="text-lg font-bold text-slate-900">{{ __('Edit Cost Center') }}</h3>
            <button type="button" onclick="document.getElementById('edit-cc-modal').close()" class="text-slate-400 hover:text-slate-600">✕</button>
        </div>
        <div class="grid grid-cols-2 gap-3">
            <div><label class="block text-xs font-medium text-slate-500">{{ __('Cost center code') }}</label><input type="text" name="code" id="edit-cc-code" required class="mt-1 w-full rounded-lg border border-slate-200 text-sm focus:border-brand-500 focus:ring-brand-500"></div>
            <div><label class="block text-xs font-medium text-slate-500">{{ __('Cost center name') }}</label><input type="text" name="name" id="edit-cc-name" required class="mt-1 w-full rounded-lg border border-slate-200 text-sm focus:border-brand-500 focus:ring-brand-500"></div>
        </div>
        <div><label class="block text-xs font-medium text-slate-500">{{ __('Name (Arabic)') }}</label><input type="text" name="name_ar" id="edit-cc-name-ar" class="mt-1 w-full rounded-lg border border-slate-200 text-sm focus:border-brand-500 focus:ring-brand-500"></div>
        <div class="grid grid-cols-2 gap-3">
            <div>
                <label class="block text-xs font-medium text-slate-500">{{ __('Cost center type') }}</label>
                <select name="type" id="edit-cc-type" class="mt-1 w-full rounded-lg border border-slate-200 text-sm focus:border-brand-500 focus:ring-brand-500">
                    @foreach ($types as $t)<option value="{{ $t }}">{{ $typeLabels[$t] }}</option>@endforeach
                </select>
            </div>
            <div>
                <label class="block text-xs font-medium text-slate-500">{{ __('Parent cost center') }}</label>
                <select name="parent_id" id="edit-cc-parent" class="mt-1 w-full rounded-lg border border-slate-200 text-sm focus:border-brand-500 focus:ring-brand-500">
                    <option value="">{{ __('None') }}</option>
                    @foreach ($costCenters as $cc)<option value="{{ $cc->id }}">{{ $cc->label() }}</option>@endforeach
                </select>
            </div>
        </div>
        <label class="flex items-center gap-2 text-sm text-slate-700">
            <input type="checkbox" name="is_active" value="1" id="edit-cc-active" class="rounded border-slate-300 text-brand-600 focus:ring-brand-500">
            {{ __('Active') }}
        </label>
        <button type="submit" class="w-full rounded-lg bg-brand-600 px-4 py-2 text-sm font-semibold text-white hover:bg-brand-700">{{ __('Save changes') }}</button>
    </form>
</dialog>

<script>
function openEditCc(id, code, name, nameAr, type, parentId, isActive) {
    document.getElementById('edit-cc-form').action = '{{ url('app/accounting/cost-centers') }}/' + id;
    document.getElementById('edit-cc-code').value = code;
    document.getElementById('edit-cc-name').value = name;
    document.getElementById('edit-cc-name-ar').value = nameAr;
    document.getElementById('edit-cc-type').value = type;
    document.getElementById('edit-cc-parent').value = parentId ?? '';
    document.getElementById('edit-cc-active').checked = isActive;
    document.getElementById('edit-cc-modal').showModal();
}
</script>
@endsection
