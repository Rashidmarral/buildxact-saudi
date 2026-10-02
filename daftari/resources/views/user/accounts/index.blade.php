@extends('layouts.app')

@section('title', __('Chart of Accounts'))

@php
    $typeLabels = [
        'asset' => __('Assets'),
        'liability' => __('Liabilities'),
        'equity' => __('Equity'),
        'revenue' => __('Revenue'),
        'expense' => __('Expenses'),
    ];
@endphp

@section('content')
@include('user.accounting.partials.tabs')

<div class="flex items-center justify-between mb-6">
    <div>
        <h2 class="text-lg font-semibold text-slate-900">{{ __('Chart of Accounts') }}</h2>
        <p class="text-sm text-slate-500 mt-1">{{ __('Manage ledger accounts and required accounting mappings.') }}</p>
    </div>
    <button type="button" onclick="document.getElementById('add-account-modal').showModal()" class="rounded-lg bg-brand-600 px-4 py-2 text-sm font-semibold text-white hover:bg-brand-700">+ {{ __('Add Account') }}</button>
</div>

<form method="GET" class="bg-white rounded-xl border border-slate-100 p-4 mb-6 flex flex-wrap items-center gap-3">
    <input type="text" name="search" value="{{ request('search') }}" placeholder="{{ __('Search by code or name') }}" class="flex-1 min-w-[12rem] rounded-lg border border-slate-200 text-sm focus:border-brand-500 focus:ring-brand-500">
    <select name="type" class="rounded-lg border border-slate-200 text-sm focus:border-brand-500 focus:ring-brand-500">
        <option value="">{{ __('All types') }}</option>
        @foreach ($types as $t)
            <option value="{{ $t }}" @selected(request('type') === $t)>{{ $typeLabels[$t] }}</option>
        @endforeach
    </select>
    <label class="flex items-center gap-2 text-sm text-slate-600">
        <input type="checkbox" name="show_inactive" value="1" @checked(request('show_inactive')) class="rounded border-slate-300 text-brand-600 focus:ring-brand-500">
        {{ __('Show inactive') }}
    </label>
    <button type="submit" class="rounded-lg border border-slate-200 px-4 py-2 text-sm font-semibold text-slate-600 hover:border-slate-300">{{ __('Refresh') }}</button>
</form>

<div class="bg-white rounded-xl border border-slate-100 divide-y divide-slate-50">
    @forelse ($accounts as $account)
        <div class="flex items-center justify-between px-5 py-3">
            <div>
                <div class="flex items-center gap-2">
                    <span class="font-semibold text-slate-900">{{ $account->code }} {{ $account->name }}</span>
                    @if ($account->is_system)<span class="rounded-full bg-slate-100 px-2 py-0.5 text-[10px] font-semibold text-slate-500">{{ __('System Account') }}</span>@endif
                    @unless ($account->is_active)<span class="rounded-full bg-red-50 px-2 py-0.5 text-[10px] font-semibold text-red-500">{{ __('Inactive') }}</span>@endunless
                </div>
                <p class="text-xs text-slate-400 mt-0.5">{{ $typeLabels[$account->type] }} — {{ $account->normal_balance === 'debit' ? __('Debit') : __('Credit') }}</p>
            </div>
            <div class="flex items-center gap-3 text-xs">
                <button type="button"
                    onclick="openEditAccount({{ $account->id }}, '{{ addslashes($account->code) }}', '{{ addslashes($account->name) }}', '{{ addslashes($account->name_ar) }}', '{{ $account->type }}', '{{ $account->normal_balance }}', {{ $account->is_system ? 'true' : 'false' }})"
                    class="text-brand-700 hover:underline">{{ __('Edit') }}</button>
                @if ($account->is_active)
                    <form method="POST" action="{{ route('app.accounts.deactivate', $account) }}">
                        @csrf
                        <button type="submit" class="text-slate-500 hover:underline">{{ __('Deactivate') }}</button>
                    </form>
                @else
                    <form method="POST" action="{{ route('app.accounts.activate', $account) }}">
                        @csrf
                        <button type="submit" class="text-emerald-600 hover:underline">{{ __('Activate') }}</button>
                    </form>
                @endif
                @unless ($account->is_system)
                    <form method="POST" action="{{ route('app.accounts.destroy', $account) }}" onsubmit="return confirm('{{ __('Delete this account?') }}')">
                        @csrf @method('DELETE')
                        <button type="submit" class="text-red-600 hover:underline">{{ __('Delete') }}</button>
                    </form>
                @endunless
            </div>
        </div>
    @empty
        <p class="px-5 py-10 text-center text-sm text-slate-400">{{ __('No accounts match your filters.') }}</p>
    @endforelse
</div>

<div class="bg-white rounded-xl border border-slate-100 p-6 mt-8">
    <h3 class="font-semibold text-slate-900">{{ __('Account Mappings') }}</h3>
    <p class="text-sm text-slate-500 mt-1 mb-4">{{ __('Map required accounting keys to active GL accounts.') }}</p>
    <div class="divide-y divide-slate-50">
        @foreach ($mappingCatalog as $key => $meta)
            @php($current = $mappings[$key]->account_id ?? null)
            @php($compatibleAccounts = $meta['allowed_type'] ? $activeAccounts->where('type', $meta['allowed_type']) : $activeAccounts)
            <form method="POST" action="{{ route('app.accounts.mappings.update') }}" class="flex items-center justify-between gap-4 py-3">
                @csrf
                <input type="hidden" name="key" value="{{ $key }}">
                <div>
                    <p class="text-sm font-semibold text-slate-800">{{ __($meta['label']) }}</p>
                    <p class="text-xs text-slate-400">{{ $meta['default_code'] }} — {{ $key }}</p>
                    @if ($meta['allowed_type'])
                        <span class="inline-block mt-1 rounded-full bg-slate-100 px-2 py-0.5 text-[10px] font-semibold text-slate-500">{{ $typeLabels[$meta['allowed_type']] }}</span>
                    @endif
                </div>
                <div class="flex items-center gap-2">
                    <select name="account_id" class="rounded-lg border border-slate-200 text-sm focus:border-brand-500 focus:ring-brand-500">
                        {{-- Only accounts of the role's own type are listed —
                             the server independently rejects a mismatched
                             type/inactive account regardless (a crafted POST
                             could otherwise bypass this <select>). --}}
                        @foreach ($compatibleAccounts as $acc)
                            <option value="{{ $acc->id }}" @selected($current === $acc->id)>{{ $acc->code }} - {{ $acc->name }}</option>
                        @endforeach
                    </select>
                    <button type="submit" class="rounded-lg border border-slate-200 px-3 py-1.5 text-xs font-semibold text-slate-600 hover:border-slate-300">{{ __('Save') }}</button>
                </div>
            </form>
        @endforeach
    </div>
    <p class="mt-4 text-xs text-slate-400">{{ __('System accounts and required mappings cannot be deactivated once used.') }}</p>
</div>

<dialog id="add-account-modal" class="rounded-2xl border border-slate-100 p-0 w-full max-w-md backdrop:bg-slate-900/40">
    <form method="POST" action="{{ route('app.accounts.store') }}" class="p-6 space-y-4">
        @csrf
        <div class="flex items-start justify-between">
            <h3 class="text-lg font-bold text-slate-900">{{ __('Add Account') }}</h3>
            <button type="button" onclick="document.getElementById('add-account-modal').close()" class="text-slate-400 hover:text-slate-600">✕</button>
        </div>
        <div class="grid grid-cols-2 gap-3">
            <div>
                <label class="block text-xs font-medium text-slate-500">{{ __('Code') }}</label>
                <input type="text" name="code" required class="mt-1 w-full rounded-lg border border-slate-200 text-sm focus:border-brand-500 focus:ring-brand-500">
            </div>
            <div>
                <label class="block text-xs font-medium text-slate-500">{{ __('Type') }}</label>
                <select name="type" class="mt-1 w-full rounded-lg border border-slate-200 text-sm focus:border-brand-500 focus:ring-brand-500">
                    @foreach ($types as $t)<option value="{{ $t }}">{{ $typeLabels[$t] }}</option>@endforeach
                </select>
            </div>
        </div>
        <div>
            <label class="block text-xs font-medium text-slate-500">{{ __('Name') }}</label>
            <input type="text" name="name" required class="mt-1 w-full rounded-lg border border-slate-200 text-sm focus:border-brand-500 focus:ring-brand-500">
        </div>
        <div>
            <label class="block text-xs font-medium text-slate-500">{{ __('Name (Arabic)') }}</label>
            <input type="text" name="name_ar" class="mt-1 w-full rounded-lg border border-slate-200 text-sm focus:border-brand-500 focus:ring-brand-500">
        </div>
        <div>
            <label class="block text-xs font-medium text-slate-500">{{ __('Normal balance') }}</label>
            <select name="normal_balance" class="mt-1 w-full rounded-lg border border-slate-200 text-sm focus:border-brand-500 focus:ring-brand-500">
                <option value="debit">{{ __('Debit') }}</option>
                <option value="credit">{{ __('Credit') }}</option>
            </select>
        </div>
        <button type="submit" class="w-full rounded-lg bg-brand-600 px-4 py-2 text-sm font-semibold text-white hover:bg-brand-700">{{ __('Add Account') }}</button>
    </form>
</dialog>

<dialog id="edit-account-modal" class="rounded-2xl border border-slate-100 p-0 w-full max-w-md backdrop:bg-slate-900/40">
    <form method="POST" id="edit-account-form" class="p-6 space-y-4">
        @csrf @method('PUT')
        <div class="flex items-start justify-between">
            <h3 class="text-lg font-bold text-slate-900">{{ __('Edit Account') }}</h3>
            <button type="button" onclick="document.getElementById('edit-account-modal').close()" class="text-slate-400 hover:text-slate-600">✕</button>
        </div>
        <p id="edit-account-system-note" class="text-xs text-amber-600 hidden">{{ __('System accounts keep their code, type, and normal balance fixed — only the name is editable.') }}</p>
        <div class="grid grid-cols-2 gap-3">
            <div>
                <label class="block text-xs font-medium text-slate-500">{{ __('Code') }}</label>
                <input type="text" name="code" id="edit-account-code" required readonly class="mt-1 w-full rounded-lg border border-slate-200 text-sm focus:border-brand-500 focus:ring-brand-500 read-only:bg-slate-50 read-only:text-slate-400">
            </div>
            <div>
                <label class="block text-xs font-medium text-slate-500">{{ __('Type') }}</label>
                <select id="edit-account-type" class="mt-1 w-full rounded-lg border border-slate-200 text-sm focus:border-brand-500 focus:ring-brand-500" onchange="document.getElementById('edit-account-type-hidden').value = this.value">
                    @foreach ($types as $t)<option value="{{ $t }}">{{ $typeLabels[$t] }}</option>@endforeach
                </select>
                <input type="hidden" name="type" id="edit-account-type-hidden">
            </div>
        </div>
        <div>
            <label class="block text-xs font-medium text-slate-500">{{ __('Name') }}</label>
            <input type="text" name="name" id="edit-account-name" required class="mt-1 w-full rounded-lg border border-slate-200 text-sm focus:border-brand-500 focus:ring-brand-500">
        </div>
        <div>
            <label class="block text-xs font-medium text-slate-500">{{ __('Name (Arabic)') }}</label>
            <input type="text" name="name_ar" id="edit-account-name-ar" class="mt-1 w-full rounded-lg border border-slate-200 text-sm focus:border-brand-500 focus:ring-brand-500">
        </div>
        <div>
            <label class="block text-xs font-medium text-slate-500">{{ __('Normal balance') }}</label>
            <select id="edit-account-balance" class="mt-1 w-full rounded-lg border border-slate-200 text-sm focus:border-brand-500 focus:ring-brand-500" onchange="document.getElementById('edit-account-balance-hidden').value = this.value">
                <option value="debit">{{ __('Debit') }}</option>
                <option value="credit">{{ __('Credit') }}</option>
            </select>
            <input type="hidden" name="normal_balance" id="edit-account-balance-hidden">
        </div>
        <button type="submit" class="w-full rounded-lg bg-brand-600 px-4 py-2 text-sm font-semibold text-white hover:bg-brand-700">{{ __('Save changes') }}</button>
    </form>
</dialog>

<script>
function openEditAccount(id, code, name, nameAr, type, balance, isSystem) {
    const form = document.getElementById('edit-account-form');
    form.action = '{{ url('app/accounting/accounts') }}/' + id;
    document.getElementById('edit-account-code').value = code;
    document.getElementById('edit-account-name').value = name;
    document.getElementById('edit-account-name-ar').value = nameAr;
    document.getElementById('edit-account-type').value = type;
    document.getElementById('edit-account-type').disabled = isSystem;
    document.getElementById('edit-account-type-hidden').value = type;
    document.getElementById('edit-account-balance').value = balance;
    document.getElementById('edit-account-balance').disabled = isSystem;
    document.getElementById('edit-account-balance-hidden').value = balance;
    document.getElementById('edit-account-system-note').classList.toggle('hidden', !isSystem);
    document.getElementById('edit-account-modal').showModal();
}
</script>
@endsection
