@extends('layouts.app')

@section('title', __('Payment Modes'))

@section('content')
<div class="flex flex-wrap items-center justify-between gap-3 mb-6">
    <div>
        <h2 class="text-lg font-semibold text-slate-900">{{ __('Payment Modes') }}</h2>
        <p class="text-sm text-slate-500 mt-1">{{ __('The tender types shown at POS, restaurant, and repair-shop checkout, and the GL account each one posts to. Add a new mode for a payment channel with its own settlement account.') }}</p>
    </div>
    <button type="button" onclick="document.getElementById('add-pm-modal').showModal()" class="rounded-lg bg-brand-600 px-4 py-2 text-sm font-semibold text-white hover:bg-brand-700">+ {{ __('Add Payment Mode') }}</button>
</div>

<div class="bg-white rounded-xl border border-slate-100 divide-y divide-slate-50">
    @if ($paymentModes->isEmpty())
        <p class="px-5 py-10 text-center text-sm text-slate-400">{{ __('No payment modes yet.') }}</p>
    @else
        @foreach ($paymentModes as $mode)
            <div class="flex items-center justify-between px-5 py-3">
                <div>
                    <span class="font-semibold text-slate-900">{{ $mode->name }}</span>
                    <span class="text-xs text-slate-400">{{ $categories[$mode->category] ?? $mode->category }}</span>
                    <span class="text-xs text-slate-400">&rarr; {{ $mode->linkedAccount?->label() ?? __('(no account mapped)') }}</span>
                    @unless ($mode->is_active)<span class="ms-2 rounded-full bg-red-50 px-2 py-0.5 text-[10px] font-semibold text-red-500">{{ __('Inactive') }}</span>@endunless
                </div>
                <div class="flex items-center gap-3 text-xs">
                    <button type="button" onclick="openEditPm({{ $mode->id }}, '{{ addslashes($mode->name) }}', '{{ addslashes($mode->name_ar) }}', '{{ $mode->category }}', {{ $mode->linked_account_id ?? 'null' }}, {{ $mode->is_active ? 'true' : 'false' }})" class="text-brand-700 hover:underline">{{ __('Edit') }}</button>
                    <form method="POST" action="{{ route('app.settings.payment-modes.destroy', $mode) }}" onsubmit="return confirm('{{ __('Delete this payment mode?') }}')">
                        @csrf @method('DELETE')
                        <button type="submit" class="text-red-600 hover:underline">{{ __('Delete') }}</button>
                    </form>
                </div>
            </div>
        @endforeach
    @endif
</div>

<dialog id="add-pm-modal" class="rounded-2xl border border-slate-100 p-0 w-full max-w-md backdrop:bg-slate-900/40">
    <form method="POST" action="{{ route('app.settings.payment-modes.store') }}" class="p-6 space-y-4">
        @csrf
        <div class="flex items-start justify-between">
            <h3 class="text-lg font-bold text-slate-900">{{ __('Add Payment Mode') }}</h3>
            <button type="button" onclick="document.getElementById('add-pm-modal').close()" class="text-slate-400 hover:text-slate-600">✕</button>
        </div>
        <div class="grid grid-cols-2 gap-3">
            <div><label class="block text-xs font-medium text-slate-500">{{ __('Code') }}</label><input type="text" name="code" required placeholder="{{ __('e.g. stc_pay') }}" class="mt-1 w-full rounded-lg border border-slate-200 text-sm focus:border-brand-500 focus:ring-brand-500"></div>
            <div><label class="block text-xs font-medium text-slate-500">{{ __('Name') }}</label><input type="text" name="name" required class="mt-1 w-full rounded-lg border border-slate-200 text-sm focus:border-brand-500 focus:ring-brand-500"></div>
        </div>
        <div><label class="block text-xs font-medium text-slate-500">{{ __('Name (Arabic)') }}</label><input type="text" name="name_ar" class="mt-1 w-full rounded-lg border border-slate-200 text-sm focus:border-brand-500 focus:ring-brand-500"></div>
        <div class="grid grid-cols-2 gap-3">
            <div>
                <label class="block text-xs font-medium text-slate-500">{{ __('Category') }}</label>
                <select name="category" class="mt-1 w-full rounded-lg border border-slate-200 text-sm focus:border-brand-500 focus:ring-brand-500">
                    @foreach ($categories as $key => $label)<option value="{{ $key }}">{{ $label }}</option>@endforeach
                </select>
            </div>
            <div>
                <label class="block text-xs font-medium text-slate-500">{{ __('GL account') }}</label>
                <select name="linked_account_id" class="mt-1 w-full rounded-lg border border-slate-200 text-sm focus:border-brand-500 focus:ring-brand-500">
                    <option value="">{{ __('None') }}</option>
                    @foreach ($accountOptions as $account)<option value="{{ $account->id }}">{{ $account->label() }}</option>@endforeach
                </select>
            </div>
        </div>
        <button type="submit" class="w-full rounded-lg bg-brand-600 px-4 py-2 text-sm font-semibold text-white hover:bg-brand-700">{{ __('Add Payment Mode') }}</button>
    </form>
</dialog>

<dialog id="edit-pm-modal" class="rounded-2xl border border-slate-100 p-0 w-full max-w-md backdrop:bg-slate-900/40">
    <form method="POST" id="edit-pm-form" class="p-6 space-y-4">
        @csrf @method('PUT')
        <div class="flex items-start justify-between">
            <h3 class="text-lg font-bold text-slate-900">{{ __('Edit Payment Mode') }}</h3>
            <button type="button" onclick="document.getElementById('edit-pm-modal').close()" class="text-slate-400 hover:text-slate-600">✕</button>
        </div>
        <div><label class="block text-xs font-medium text-slate-500">{{ __('Name') }}</label><input type="text" name="name" id="edit-pm-name" required class="mt-1 w-full rounded-lg border border-slate-200 text-sm focus:border-brand-500 focus:ring-brand-500"></div>
        <div><label class="block text-xs font-medium text-slate-500">{{ __('Name (Arabic)') }}</label><input type="text" name="name_ar" id="edit-pm-name-ar" class="mt-1 w-full rounded-lg border border-slate-200 text-sm focus:border-brand-500 focus:ring-brand-500"></div>
        <div class="grid grid-cols-2 gap-3">
            <div>
                <label class="block text-xs font-medium text-slate-500">{{ __('Category') }}</label>
                <select name="category" id="edit-pm-category" class="mt-1 w-full rounded-lg border border-slate-200 text-sm focus:border-brand-500 focus:ring-brand-500">
                    @foreach ($categories as $key => $label)<option value="{{ $key }}">{{ $label }}</option>@endforeach
                </select>
            </div>
            <div>
                <label class="block text-xs font-medium text-slate-500">{{ __('GL account') }}</label>
                <select name="linked_account_id" id="edit-pm-account" class="mt-1 w-full rounded-lg border border-slate-200 text-sm focus:border-brand-500 focus:ring-brand-500">
                    <option value="">{{ __('None') }}</option>
                    @foreach ($accountOptions as $account)<option value="{{ $account->id }}">{{ $account->label() }}</option>@endforeach
                </select>
            </div>
        </div>
        <label class="flex items-center gap-2 text-sm text-slate-700">
            <input type="checkbox" name="is_active" value="1" id="edit-pm-active" class="rounded border-slate-300 text-brand-600 focus:ring-brand-500">
            {{ __('Active') }}
        </label>
        <button type="submit" class="w-full rounded-lg bg-brand-600 px-4 py-2 text-sm font-semibold text-white hover:bg-brand-700">{{ __('Save changes') }}</button>
    </form>
</dialog>

<script>
function openEditPm(id, name, nameAr, category, accountId, isActive) {
    document.getElementById('edit-pm-form').action = '{{ url('app/settings/payment-modes') }}/' + id;
    document.getElementById('edit-pm-name').value = name;
    document.getElementById('edit-pm-name-ar').value = nameAr;
    document.getElementById('edit-pm-category').value = category;
    document.getElementById('edit-pm-account').value = accountId ?? '';
    document.getElementById('edit-pm-active').checked = isActive;
    document.getElementById('edit-pm-modal').showModal();
}
</script>
@endsection
