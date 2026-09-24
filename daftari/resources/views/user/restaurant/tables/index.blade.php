@extends('layouts.app')

@section('title', __('Restaurant Tables'))

@section('content')
<div class="flex items-center justify-between mb-6">
    <div>
        <h1 class="text-2xl font-bold text-slate-900">{{ __('Restaurant Tables') }}</h1>
        <p class="text-sm text-slate-500 mt-1">{{ __('Manage your dining area — new dine-in orders can only be started on an available table.') }}</p>
    </div>
    <div class="flex gap-2">
        <a href="{{ route('app.restaurant.orders.index') }}" class="rounded-lg border border-slate-200 bg-white px-4 py-2 text-sm font-semibold text-slate-600 hover:border-slate-300">{{ __('Orders') }}</a>
        <button type="button" onclick="document.getElementById('add-table-modal').showModal()" class="rounded-lg bg-brand-600 px-4 py-2 text-sm font-semibold text-white hover:bg-brand-700">{{ __('+ Add table') }}</button>
    </div>
</div>

@if ($tables->isEmpty())
    <p class="bg-white rounded-xl border border-slate-100 px-6 py-8 text-sm text-slate-500">{{ __('No tables yet.') }}</p>
@else
    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
        @foreach ($tables as $table)
            @php($statusStyle = ['available' => 'border-emerald-200 bg-emerald-50', 'occupied' => 'border-amber-200 bg-amber-50', 'reserved' => 'border-slate-300 bg-slate-100'][$table->status] ?? 'border-slate-200 bg-white')
            <div class="rounded-xl border p-4 {{ $statusStyle }}">
                <div class="flex items-start justify-between">
                    <div>
                        <p class="font-semibold text-slate-900">{{ $table->name }}</p>
                        <p class="text-xs text-slate-500">{{ $table->area ?: __('No area') }} · {{ __(':count seats', ['count' => $table->seats]) }}</p>
                    </div>
                    <span class="rounded-full bg-white/70 px-2.5 py-0.5 text-xs font-semibold text-slate-700">
                        @if ($table->status === 'available') {{ __('Available') }}
                        @elseif ($table->status === 'occupied') {{ __('Occupied') }}
                        @else {{ __('Reserved') }}
                        @endif
                    </span>
                </div>
                <div class="mt-3 flex items-center justify-between text-xs">
                    <button type="button" class="text-brand-700 hover:underline"
                        data-edit-table
                        data-update-url="{{ route('app.restaurant.tables.update', $table) }}"
                        data-name="{{ $table->name }}"
                        data-area="{{ $table->area }}"
                        data-seats="{{ $table->seats }}"
                    >{{ __('Edit') }}</button>
                    <form method="POST" action="{{ route('app.restaurant.tables.destroy', $table) }}" onsubmit="return confirm('{{ __('Delete this table?') }}')">
                        @csrf @method('DELETE')
                        <button type="submit" class="text-red-600 hover:underline">{{ __('Delete') }}</button>
                    </form>
                </div>
            </div>
        @endforeach
    </div>
@endif

<dialog id="add-table-modal" class="rounded-2xl border border-slate-100 p-0 w-full max-w-md backdrop:bg-slate-900/40">
    <form method="POST" action="{{ route('app.restaurant.tables.store') }}" class="p-6 space-y-4">
        @csrf
        <div class="flex items-start justify-between">
            <h3 class="text-lg font-bold text-slate-900">{{ __('Add table') }}</h3>
            <button type="button" onclick="document.getElementById('add-table-modal').close()" class="text-slate-400 hover:text-slate-600">✕</button>
        </div>
        <div>
            <label class="block text-xs font-medium text-slate-500">{{ __('Name') }}</label>
            <input type="text" name="name" placeholder="{{ __('e.g. T1') }}" required class="mt-1 w-full rounded-lg border border-slate-200 text-sm focus:border-brand-500 focus:ring-brand-500">
        </div>
        <div>
            <label class="block text-xs font-medium text-slate-500">{{ __('Area') }}</label>
            <input type="text" name="area" placeholder="{{ __('e.g. Main Hall') }}" class="mt-1 w-full rounded-lg border border-slate-200 text-sm focus:border-brand-500 focus:ring-brand-500">
        </div>
        <div>
            <label class="block text-xs font-medium text-slate-500">{{ __('Seats') }}</label>
            <input type="number" name="seats" min="1" value="2" required class="mt-1 w-full rounded-lg border border-slate-200 text-sm focus:border-brand-500 focus:ring-brand-500">
        </div>
        <div class="flex gap-3">
            <button type="button" onclick="document.getElementById('add-table-modal').close()" class="flex-1 rounded-lg border border-slate-200 px-4 py-2 text-sm font-semibold text-slate-600 hover:border-slate-300">{{ __('Cancel') }}</button>
            <button type="submit" class="flex-1 rounded-lg bg-brand-600 px-4 py-2 text-sm font-semibold text-white hover:bg-brand-700">{{ __('Save') }}</button>
        </div>
    </form>
</dialog>

<dialog id="edit-table-modal" class="rounded-2xl border border-slate-100 p-0 w-full max-w-md backdrop:bg-slate-900/40">
    <form method="POST" id="edit-table-form" class="p-6 space-y-4">
        @csrf @method('PUT')
        <div class="flex items-start justify-between">
            <h3 class="text-lg font-bold text-slate-900">{{ __('Edit table') }}</h3>
            <button type="button" onclick="document.getElementById('edit-table-modal').close()" class="text-slate-400 hover:text-slate-600">✕</button>
        </div>
        <div>
            <label class="block text-xs font-medium text-slate-500">{{ __('Name') }}</label>
            <input type="text" name="name" id="edit-table-name" required class="mt-1 w-full rounded-lg border border-slate-200 text-sm focus:border-brand-500 focus:ring-brand-500">
        </div>
        <div>
            <label class="block text-xs font-medium text-slate-500">{{ __('Area') }}</label>
            <input type="text" name="area" id="edit-table-area" class="mt-1 w-full rounded-lg border border-slate-200 text-sm focus:border-brand-500 focus:ring-brand-500">
        </div>
        <div>
            <label class="block text-xs font-medium text-slate-500">{{ __('Seats') }}</label>
            <input type="number" name="seats" id="edit-table-seats" min="1" required class="mt-1 w-full rounded-lg border border-slate-200 text-sm focus:border-brand-500 focus:ring-brand-500">
        </div>
        <div class="flex gap-3">
            <button type="button" onclick="document.getElementById('edit-table-modal').close()" class="flex-1 rounded-lg border border-slate-200 px-4 py-2 text-sm font-semibold text-slate-600 hover:border-slate-300">{{ __('Cancel') }}</button>
            <button type="submit" class="flex-1 rounded-lg bg-brand-600 px-4 py-2 text-sm font-semibold text-white hover:bg-brand-700">{{ __('Save') }}</button>
        </div>
    </form>
</dialog>

<script>
document.querySelectorAll('[data-edit-table]').forEach(btn => {
    btn.addEventListener('click', () => {
        document.getElementById('edit-table-form').action = btn.dataset.updateUrl;
        document.getElementById('edit-table-name').value = btn.dataset.name || '';
        document.getElementById('edit-table-area').value = btn.dataset.area || '';
        document.getElementById('edit-table-seats').value = btn.dataset.seats || 2;
        document.getElementById('edit-table-modal').showModal();
    });
});
</script>
@endsection
