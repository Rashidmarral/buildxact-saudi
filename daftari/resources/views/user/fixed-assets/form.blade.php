@extends('layouts.app')

@section('title', __('New Fixed Asset'))

@section('content')
<div class="max-w-2xl">
    <h1 class="text-xl font-bold text-slate-900 mb-1">{{ __('New Fixed Asset') }}</h1>
    <p class="text-sm text-slate-500 mb-6">{{ __('Registering an asset posts its acquisition cost to your ledger and starts straight-line monthly depreciation.') }}</p>

    <form method="POST" action="{{ route('app.fixed-assets.store') }}" class="bg-white rounded-xl border border-slate-100 p-6 space-y-4">
        @csrf

        <div class="grid sm:grid-cols-2 gap-4">
            <div>
                <label class="block text-xs font-semibold uppercase text-slate-500">{{ __('Asset name') }}</label>
                <input type="text" name="name" value="{{ old('name', $asset->name) }}" required class="mt-1 w-full rounded-lg border border-slate-200 focus:border-brand-500 focus:ring-brand-500">
                @error('name')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
            </div>
            <div>
                <label class="block text-xs font-semibold uppercase text-slate-500">{{ __('Category') }}</label>
                <input type="text" name="category" value="{{ old('category', $asset->category) }}" placeholder="{{ __('e.g. Vehicles, Equipment, Furniture') }}" class="mt-1 w-full rounded-lg border border-slate-200 focus:border-brand-500 focus:ring-brand-500">
            </div>
        </div>

        <div class="grid sm:grid-cols-2 gap-4">
            <div>
                <label class="block text-xs font-semibold uppercase text-slate-500">{{ __('Acquisition date') }}</label>
                <input type="date" name="acquisition_date" value="{{ old('acquisition_date', $asset->acquisition_date) }}" required class="mt-1 w-full rounded-lg border border-slate-200 focus:border-brand-500 focus:ring-brand-500">
                @error('acquisition_date')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
            </div>
            <div>
                <label class="block text-xs font-semibold uppercase text-slate-500">{{ __('Acquisition cost') }}</label>
                <input type="number" step="0.01" min="0.01" name="acquisition_cost" value="{{ old('acquisition_cost') }}" required class="mt-1 w-full rounded-lg border border-slate-200 focus:border-brand-500 focus:ring-brand-500">
                @error('acquisition_cost')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
            </div>
        </div>

        <div class="grid sm:grid-cols-2 gap-4">
            <div>
                <label class="block text-xs font-semibold uppercase text-slate-500">{{ __('Salvage value') }}</label>
                <input type="number" step="0.01" min="0" name="salvage_value" value="{{ old('salvage_value', 0) }}" class="mt-1 w-full rounded-lg border border-slate-200 focus:border-brand-500 focus:ring-brand-500">
                <p class="text-xs text-slate-400 mt-1">{{ __('Estimated resale value at end of useful life.') }}</p>
            </div>
            <div>
                <label class="block text-xs font-semibold uppercase text-slate-500">{{ __('Useful life (years)') }}</label>
                <input type="number" step="1" min="1" max="100" name="useful_life_years" value="{{ old('useful_life_years', $asset->useful_life_years) }}" required class="mt-1 w-full rounded-lg border border-slate-200 focus:border-brand-500 focus:ring-brand-500">
                @error('useful_life_years')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
            </div>
        </div>

        <div class="grid sm:grid-cols-2 gap-4">
            <div>
                <label class="block text-xs font-semibold uppercase text-slate-500">{{ __('Financial account') }}</label>
                <select name="bank_account_id" class="mt-1 w-full rounded-lg border border-slate-200 focus:border-brand-500 focus:ring-brand-500">
                    <option value="" @selected(! old('bank_account_id'))>{{ __('Unpaid (record as payable)') }}</option>
                    @foreach ($bankAccounts as $account)
                        <option value="{{ $account->id }}" @selected(old('bank_account_id') == $account->id)>{{ $account->name }}</option>
                    @endforeach
                </select>
                <p class="text-xs text-slate-400 mt-1">{{ __('Leave unpaid to settle later with a payment voucher.') }}</p>
            </div>
            <div>
                <label class="block text-xs font-semibold uppercase text-slate-500">{{ __('Fixed asset GL account') }}</label>
                <select name="account_id" class="mt-1 w-full rounded-lg border border-slate-200 focus:border-brand-500 focus:ring-brand-500">
                    <option value="">{{ __('Use default Fixed Assets account') }}</option>
                    @foreach ($glAccounts as $account)
                        <option value="{{ $account->id }}" @selected(old('account_id') == $account->id)>{{ $account->code }} — {{ $account->name }}</option>
                    @endforeach
                </select>
            </div>
        </div>

        <div>
            <label class="block text-xs font-semibold uppercase text-slate-500">{{ __('Notes') }}</label>
            <textarea name="notes" rows="2" class="mt-1 w-full rounded-lg border border-slate-200 focus:border-brand-500 focus:ring-brand-500">{{ old('notes') }}</textarea>
        </div>

        <button type="submit" class="rounded-lg bg-brand-600 px-6 py-2.5 text-sm font-semibold text-white hover:bg-brand-700">{{ __('Register asset') }}</button>
    </form>
</div>
@endsection
