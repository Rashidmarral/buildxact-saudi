@extends('layouts.app')

@section('title', $asset->exists ? __('Edit Machine') : __('New Machine'))

@section('content')
<div class="max-w-3xl">
    <h1 class="text-xl font-bold text-slate-900 mb-1">{{ $asset->exists ? __('Edit Machine') : __('New Machine') }}</h1>
    <p class="text-sm text-slate-500 mb-6">
        @if ($asset->exists)
            {{ __('Machine details. Cost, depreciation, and disposal are managed from Accounting > Fixed Assets.') }}
        @else
            {{ __('Registering a machine also creates its Fixed Asset record and posts the acquisition cost to your ledger.') }}
        @endif
    </p>

    <form method="POST" action="{{ $asset->exists ? route('app.machinery.assets.update', $asset) : route('app.machinery.assets.store') }}" class="bg-white rounded-xl border border-slate-100 p-6 space-y-5">
        @csrf
        @if ($asset->exists) @method('PUT') @endif

        <div class="grid sm:grid-cols-2 gap-4">
            <div>
                <label class="block text-xs font-semibold uppercase text-slate-500">{{ __('Name (English)') }}</label>
                <input type="text" name="name" value="{{ old('name', $asset->name) }}" required class="mt-1 w-full rounded-lg border border-slate-200 focus:border-brand-500 focus:ring-brand-500">
                @error('name')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
            </div>
            <div>
                <label class="block text-xs font-semibold uppercase text-slate-500">{{ __('Name (Arabic)') }}</label>
                <input type="text" name="name_ar" value="{{ old('name_ar', $asset->name_ar) }}" class="mt-1 w-full rounded-lg border border-slate-200 focus:border-brand-500 focus:ring-brand-500">
            </div>
        </div>

        <div class="grid sm:grid-cols-3 gap-4">
            <div>
                <label class="block text-xs font-semibold uppercase text-slate-500">{{ __('Category') }}</label>
                <input type="text" name="category" value="{{ old('category', $asset->category) }}" placeholder="{{ __('e.g. Asphalt Paver, Roller') }}" class="mt-1 w-full rounded-lg border border-slate-200 focus:border-brand-500 focus:ring-brand-500">
            </div>
            <div>
                <label class="block text-xs font-semibold uppercase text-slate-500">{{ __('Make') }}</label>
                <input type="text" name="make" value="{{ old('make', $asset->make) }}" class="mt-1 w-full rounded-lg border border-slate-200 focus:border-brand-500 focus:ring-brand-500">
            </div>
            <div>
                <label class="block text-xs font-semibold uppercase text-slate-500">{{ __('Model') }}</label>
                <input type="text" name="model" value="{{ old('model', $asset->model) }}" class="mt-1 w-full rounded-lg border border-slate-200 focus:border-brand-500 focus:ring-brand-500">
            </div>
        </div>

        <div class="grid sm:grid-cols-3 gap-4">
            <div>
                <label class="block text-xs font-semibold uppercase text-slate-500">{{ __('Serial number') }}</label>
                <input type="text" name="serial_number" value="{{ old('serial_number', $asset->serial_number) }}" class="mt-1 w-full rounded-lg border border-slate-200 focus:border-brand-500 focus:ring-brand-500">
            </div>
            <div>
                <label class="block text-xs font-semibold uppercase text-slate-500">{{ __('Plate / chassis number') }}</label>
                <input type="text" name="plate_or_chassis_number" value="{{ old('plate_or_chassis_number', $asset->plate_or_chassis_number) }}" class="mt-1 w-full rounded-lg border border-slate-200 focus:border-brand-500 focus:ring-brand-500">
                <p class="text-xs text-slate-400 mt-1">{{ __('Istimara-relevant.') }}</p>
            </div>
            <div>
                <label class="block text-xs font-semibold uppercase text-slate-500">{{ __('Year of manufacture') }}</label>
                <input type="number" name="year_of_manufacture" value="{{ old('year_of_manufacture', $asset->year_of_manufacture) }}" min="1950" max="{{ now()->year + 1 }}" class="mt-1 w-full rounded-lg border border-slate-200 focus:border-brand-500 focus:ring-brand-500">
            </div>
        </div>

        <div class="grid sm:grid-cols-3 gap-4">
            <div>
                <label class="block text-xs font-semibold uppercase text-slate-500">{{ __('Default rental rate') }}</label>
                <input type="number" step="0.01" min="0" name="default_rental_rate" value="{{ old('default_rental_rate', $asset->default_rental_rate) }}" class="mt-1 w-full rounded-lg border border-slate-200 focus:border-brand-500 focus:ring-brand-500">
            </div>
            <div>
                <label class="block text-xs font-semibold uppercase text-slate-500">{{ __('Rate type') }}</label>
                <select name="rental_rate_type" class="mt-1 w-full rounded-lg border border-slate-200 focus:border-brand-500 focus:ring-brand-500">
                    <option value="">{{ __('—') }}</option>
                    @foreach (['daily' => __('Daily'), 'weekly' => __('Weekly'), 'monthly' => __('Monthly')] as $value => $label)
                        <option value="{{ $value }}" @selected(old('rental_rate_type', $asset->rental_rate_type) === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="block text-xs font-semibold uppercase text-slate-500">{{ __('Default operator') }}</label>
                <select name="operator_employee_id" class="mt-1 w-full rounded-lg border border-slate-200 focus:border-brand-500 focus:ring-brand-500">
                    <option value="">{{ __('None') }}</option>
                    @foreach ($employees as $employee)
                        <option value="{{ $employee->id }}" @selected(old('operator_employee_id', $asset->operator_employee_id) == $employee->id)>{{ $employee->full_name }}</option>
                    @endforeach
                </select>
            </div>
        </div>

        <div class="grid sm:grid-cols-2 gap-4">
            <div>
                <label class="block text-xs font-semibold uppercase text-slate-500">{{ __('Registration (Istimara) expiry') }}</label>
                <input type="date" name="registration_expiry_date" value="{{ old('registration_expiry_date', optional($asset->registration_expiry_date)->toDateString()) }}" class="mt-1 w-full rounded-lg border border-slate-200 focus:border-brand-500 focus:ring-brand-500">
            </div>
            <div>
                <label class="block text-xs font-semibold uppercase text-slate-500">{{ __('Insurance expiry') }}</label>
                <input type="date" name="insurance_expiry_date" value="{{ old('insurance_expiry_date', optional($asset->insurance_expiry_date)->toDateString()) }}" class="mt-1 w-full rounded-lg border border-slate-200 focus:border-brand-500 focus:ring-brand-500">
            </div>
        </div>

        @unless ($asset->exists)
            <div class="border-t border-slate-100 pt-5">
                <h3 class="font-semibold text-slate-900 mb-1">{{ __('Acquisition') }}</h3>
                <p class="text-xs text-slate-500 mb-4">{{ __('Posts the acquisition cost to your ledger and starts straight-line monthly depreciation, exactly like a Fixed Asset.') }}</p>

                <div class="grid sm:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs font-semibold uppercase text-slate-500">{{ __('Acquisition date') }}</label>
                        <input type="date" name="acquisition_date" value="{{ old('acquisition_date', now()->toDateString()) }}" required class="mt-1 w-full rounded-lg border border-slate-200 focus:border-brand-500 focus:ring-brand-500">
                        @error('acquisition_date')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label class="block text-xs font-semibold uppercase text-slate-500">{{ __('Acquisition cost') }}</label>
                        <input type="number" step="0.01" min="0.01" name="acquisition_cost" value="{{ old('acquisition_cost') }}" required class="mt-1 w-full rounded-lg border border-slate-200 focus:border-brand-500 focus:ring-brand-500">
                        @error('acquisition_cost')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                    </div>
                </div>

                <div class="grid sm:grid-cols-2 gap-4 mt-4">
                    <div>
                        <label class="block text-xs font-semibold uppercase text-slate-500">{{ __('Salvage value') }}</label>
                        <input type="number" step="0.01" min="0" name="salvage_value" value="{{ old('salvage_value', 0) }}" class="mt-1 w-full rounded-lg border border-slate-200 focus:border-brand-500 focus:ring-brand-500">
                    </div>
                    <div>
                        <label class="block text-xs font-semibold uppercase text-slate-500">{{ __('Useful life (years)') }}</label>
                        <input type="number" step="1" min="1" max="100" name="useful_life_years" value="{{ old('useful_life_years', 8) }}" required class="mt-1 w-full rounded-lg border border-slate-200 focus:border-brand-500 focus:ring-brand-500">
                        @error('useful_life_years')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                    </div>
                </div>

                <div class="grid sm:grid-cols-2 gap-4 mt-4">
                    <div>
                        <label class="block text-xs font-semibold uppercase text-slate-500">{{ __('Financial account') }}</label>
                        <select name="bank_account_id" class="mt-1 w-full rounded-lg border border-slate-200 focus:border-brand-500 focus:ring-brand-500">
                            <option value="">{{ __('Unpaid (record as payable)') }}</option>
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
            </div>
        @endunless

        <div>
            <label class="block text-xs font-semibold uppercase text-slate-500">{{ __('Notes') }}</label>
            <textarea name="notes" rows="2" class="mt-1 w-full rounded-lg border border-slate-200 focus:border-brand-500 focus:ring-brand-500">{{ old('notes', $asset->notes) }}</textarea>
        </div>

        <button type="submit" class="rounded-lg bg-brand-600 px-6 py-2.5 text-sm font-semibold text-white hover:bg-brand-700">{{ $asset->exists ? __('Save changes') : __('Register machine') }}</button>
    </form>
</div>
@endsection
