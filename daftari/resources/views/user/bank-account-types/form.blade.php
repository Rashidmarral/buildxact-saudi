@extends('layouts.app')

@section('title', $type->exists ? __('Edit Account Type') : __('New Account Type'))

@section('content')
<form method="POST" action="{{ $type->exists ? route('app.bank-account-types.update', $type) : route('app.bank-account-types.store') }}" class="max-w-xl space-y-6">
    @csrf
    @if ($type->exists) @method('PUT') @endif

    <div class="bg-white rounded-xl border border-slate-100 p-6 space-y-5">
        <div>
            <label class="block text-xs font-semibold uppercase text-slate-500">{{ __('Type name') }}</label>
            <input type="text" name="name" value="{{ old('name', $type->name) }}" required placeholder="{{ __('e.g. Digital Wallet, POS Till') }}" class="mt-1 w-full rounded-lg border border-slate-200 focus:border-brand-500 focus:ring-brand-500">
        </div>

        <div>
            <label class="block text-xs font-semibold uppercase text-slate-500">{{ __('Behaves like') }}</label>
            <select name="nature" class="mt-1 w-full rounded-lg border border-slate-200 focus:border-brand-500 focus:ring-brand-500">
                <option value="bank" @selected(old('nature', $type->nature ?? 'bank') === 'bank')>{{ __('Bank') }}</option>
                <option value="cash" @selected(old('nature', $type->nature) === 'cash')>{{ __('Cash') }}</option>
            </select>
            <p class="text-xs text-slate-400 mt-1">{{ __('This decides which ledger accounts an account of this type uses behind the scenes — it\'s not shown anywhere except here.') }}</p>
        </div>
    </div>

    <div class="flex gap-3">
        <button type="submit" class="rounded-lg bg-brand-600 px-6 py-2.5 font-semibold text-white hover:bg-brand-700">{{ __('Save') }}</button>
        <a href="{{ route('app.bank-account-types.index') }}" class="rounded-lg border border-slate-200 px-6 py-2.5 font-semibold text-slate-600 hover:border-slate-300">{{ __('Cancel') }}</a>
    </div>
</form>
@endsection
