@extends('layouts.app')

@section('title', $supplier->exists ? __('Edit Supplier') : __('New Supplier'))

@section('content')
<form method="POST" action="{{ $supplier->exists ? route('app.suppliers.update', $supplier) : route('app.suppliers.store') }}" class="space-y-6">
    @csrf
    @if ($supplier->exists) @method('PUT') @endif

    <div class="grid lg:grid-cols-2 gap-6">
        <div class="bg-white rounded-xl border border-slate-100 p-6 space-y-5">
            <div>
                <label class="block text-xs font-semibold uppercase text-slate-500 mb-2">{{ __('Type') }}</label>
                <div class="flex items-center gap-6">
                    <label class="flex items-center gap-2 text-sm font-medium text-slate-700">
                        <input type="radio" name="type" value="individual" @checked(old('type', $supplier->type) === 'individual') class="text-brand-600 focus:ring-brand-500">
                        {{ __('Individual') }}
                    </label>
                    <label class="flex items-center gap-2 text-sm font-medium text-slate-700">
                        <input type="radio" id="type-company" name="type" value="company" @checked(old('type', $supplier->type ?? 'company') === 'company') class="text-brand-600 focus:ring-brand-500">
                        {{ __('Company') }}
                    </label>
                </div>
            </div>

            <div>
                <label class="block text-xs font-semibold uppercase text-slate-500">{{ __('Code') }}</label>
                <input type="text" name="supplier_code" value="{{ old('supplier_code', $supplier->supplier_code) }}" placeholder="{{ __('Auto-generated if left blank') }}" class="mt-1 w-full rounded-lg border border-slate-200 focus:border-brand-500 focus:ring-brand-500">
            </div>

            <div>
                <label id="name-label" class="block text-xs font-semibold uppercase text-slate-500">{{ __('Company name') }}</label>
                <input type="text" name="name" value="{{ old('name', $supplier->name) }}" required class="mt-1 w-full rounded-lg border border-slate-200 focus:border-brand-500 focus:ring-brand-500">
            </div>
            <div>
                <label class="block text-xs font-semibold uppercase text-slate-500">{{ __('Name (Arabic)') }}</label>
                <input type="text" name="name_ar" value="{{ old('name_ar', $supplier->name_ar) }}" class="mt-1 w-full rounded-lg border border-slate-200 focus:border-brand-500 focus:ring-brand-500">
            </div>

            <div>
                <label class="block text-xs font-semibold uppercase text-slate-500">{{ __('Tax ID') }}</label>
                <input type="text" name="vat_number" value="{{ old('vat_number', $supplier->vat_number) }}" class="mt-1 w-full rounded-lg border border-slate-200 focus:border-brand-500 focus:ring-brand-500">
            </div>

            <div>
                <label class="block text-xs font-semibold uppercase text-slate-500">{{ __('Company registration number') }}</label>
                <input type="text" name="cr_number" value="{{ old('cr_number', $supplier->cr_number) }}" class="mt-1 w-full rounded-lg border border-slate-200 focus:border-brand-500 focus:ring-brand-500">
            </div>

            <div>
                <label class="block text-xs font-semibold uppercase text-slate-500">{{ __('Initial balance') }}</label>
                <input type="number" step="0.01" name="initial_balance" value="{{ old('initial_balance', $supplier->initial_balance ?? 0) }}" class="mt-1 w-full rounded-lg border border-slate-200 focus:border-brand-500 focus:ring-brand-500">
            </div>

            <div>
                <label class="flex items-center gap-2 text-sm font-medium text-slate-700">
                    <input type="checkbox" name="is_non_resident" value="1" @checked(old('is_non_resident', ! ($supplier->is_resident ?? true))) class="rounded border-slate-300 text-brand-600 focus:ring-brand-500">
                    {{ __('Non-resident (subject to Saudi withholding tax)') }}
                </label>
                <p class="text-xs text-slate-400 mt-1">{{ __('Bills from this supplier can withhold tax before payment, using the categories set under Business Rules.') }}</p>
            </div>

            <div>
                <label class="block text-xs font-semibold uppercase text-slate-500">{{ __('Notes') }}</label>
                <textarea name="notes" rows="3" placeholder="{{ __('Optional notes') }}" class="mt-1 w-full rounded-lg border border-slate-200 focus:border-brand-500 focus:ring-brand-500">{{ old('notes', $supplier->notes) }}</textarea>
            </div>
        </div>

        <div class="bg-white rounded-xl border border-slate-100 p-6 space-y-5">
            <h3 class="font-semibold text-slate-900">{{ __('Contact details') }}</h3>

            <div>
                <label class="block text-xs font-semibold uppercase text-slate-500">{{ __('Contact name') }}</label>
                <input type="text" name="contact_name" value="{{ old('contact_name', $supplier->contact_name) }}" class="mt-1 w-full rounded-lg border border-slate-200 focus:border-brand-500 focus:ring-brand-500">
            </div>

            <div>
                <p class="block text-xs font-semibold uppercase text-slate-500 mb-2">{{ __('This contact includes') }}</p>
                <div class="flex items-center gap-6 text-sm text-slate-700">
                    <label class="flex items-center gap-2"><input type="checkbox" id="include-phone" checked class="rounded border-slate-300 text-brand-600 focus:ring-brand-500">{{ __('Phone') }}</label>
                    <label class="flex items-center gap-2"><input type="checkbox" id="include-email" checked class="rounded border-slate-300 text-brand-600 focus:ring-brand-500">{{ __('Email address') }}</label>
                    <label class="flex items-center gap-2"><input type="checkbox" id="include-address" checked class="rounded border-slate-300 text-brand-600 focus:ring-brand-500">{{ __('Address') }}</label>
                </div>
            </div>

            <div id="section-phone" class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-semibold uppercase text-slate-500">{{ __('Phone') }}</label>
                    <input type="text" name="phone" value="{{ old('phone', $supplier->phone) }}" class="mt-1 w-full rounded-lg border border-slate-200 focus:border-brand-500 focus:ring-brand-500">
                </div>
                <div>
                    <label class="block text-xs font-semibold uppercase text-slate-500">{{ __('Mobile') }}</label>
                    <input type="text" name="mobile" value="{{ old('mobile', $supplier->mobile) }}" class="mt-1 w-full rounded-lg border border-slate-200 focus:border-brand-500 focus:ring-brand-500">
                </div>
            </div>

            <div id="section-email">
                <label class="block text-xs font-semibold uppercase text-slate-500">{{ __('Email address') }}</label>
                <input type="email" name="email" value="{{ old('email', $supplier->email) }}" class="mt-1 w-full rounded-lg border border-slate-200 focus:border-brand-500 focus:ring-brand-500">
            </div>

            <div id="section-address" class="space-y-4">
                <div>
                    <label class="block text-xs font-semibold uppercase text-slate-500">{{ __('Address line 1') }}</label>
                    <input type="text" name="address_line_1" value="{{ old('address_line_1', $supplier->address_line_1) }}" class="mt-1 w-full rounded-lg border border-slate-200 focus:border-brand-500 focus:ring-brand-500">
                </div>
                <div>
                    <label class="block text-xs font-semibold uppercase text-slate-500">{{ __('Address line 2') }}</label>
                    <input type="text" name="address_line_2" value="{{ old('address_line_2', $supplier->address_line_2) }}" class="mt-1 w-full rounded-lg border border-slate-200 focus:border-brand-500 focus:ring-brand-500">
                </div>
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs font-semibold uppercase text-slate-500">{{ __('District') }}</label>
                        <input type="text" name="district" value="{{ old('district', $supplier->district) }}" class="mt-1 w-full rounded-lg border border-slate-200 focus:border-brand-500 focus:ring-brand-500">
                    </div>
                    <div>
                        <label class="block text-xs font-semibold uppercase text-slate-500">{{ __('City') }}</label>
                        <input type="text" name="city" value="{{ old('city', $supplier->city) }}" class="mt-1 w-full rounded-lg border border-slate-200 focus:border-brand-500 focus:ring-brand-500">
                    </div>
                </div>
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs font-semibold uppercase text-slate-500">{{ __('Postal code') }}</label>
                        <input type="text" name="postal_code" value="{{ old('postal_code', $supplier->postal_code) }}" class="mt-1 w-full rounded-lg border border-slate-200 focus:border-brand-500 focus:ring-brand-500">
                    </div>
                    <div>
                        <label class="block text-xs font-semibold uppercase text-slate-500">{{ __('State') }}</label>
                        <input type="text" name="state" value="{{ old('state', $supplier->state) }}" class="mt-1 w-full rounded-lg border border-slate-200 focus:border-brand-500 focus:ring-brand-500">
                    </div>
                </div>
                <div>
                    <label class="block text-xs font-semibold uppercase text-slate-500">{{ __('Country') }}</label>
                    <input type="text" name="country" value="{{ old('country', $supplier->country ?: ($supplier->exists ? '' : \App\Support\Countries::name(\App\Models\Setting::get('general_default_country')))) }}" class="mt-1 w-full rounded-lg border border-slate-200 focus:border-brand-500 focus:ring-brand-500">
                </div>
            </div>
        </div>
    </div>

    @include('partials.custom-fields')

    <div class="flex gap-3">
        <button type="submit" class="rounded-lg bg-brand-600 px-6 py-2.5 font-semibold text-white hover:bg-brand-700">{{ __('Save') }}</button>
        <a href="{{ route('app.suppliers.index') }}" class="rounded-lg border border-slate-200 px-6 py-2.5 font-semibold text-slate-600 hover:border-slate-300">{{ __('Cancel') }}</a>
    </div>
</form>

<script>
(function () {
    const nameLabel = document.getElementById('name-label');
    const companyText = @json(__('Company name'));
    const individualText = @json(__('Individual name'));

    document.querySelectorAll('input[name="type"]').forEach(radio => {
        radio.addEventListener('change', () => {
            nameLabel.textContent = radio.value === 'individual' && radio.checked ? individualText : companyText;
        });
    });

    function bindToggle(checkboxId, sectionId) {
        const checkbox = document.getElementById(checkboxId);
        const section = document.getElementById(sectionId);

        function sync() {
            section.classList.toggle('hidden', ! checkbox.checked);
            section.querySelectorAll('input').forEach(input => {
                if (! checkbox.checked) {
                    input.dataset.hiddenValue = input.value;
                    input.value = '';
                } else if (input.dataset.hiddenValue) {
                    input.value = input.dataset.hiddenValue;
                }
            });
        }

        checkbox.addEventListener('change', sync);
        sync();
    }

    bindToggle('include-phone', 'section-phone');
    bindToggle('include-email', 'section-email');
    bindToggle('include-address', 'section-address');
})();
</script>
@endsection
