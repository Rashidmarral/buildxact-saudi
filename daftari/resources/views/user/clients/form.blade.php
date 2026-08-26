@extends('layouts.app')

@section('title', $client->exists ? __('Edit Client') : __('New Client'))

@section('content')
<form method="POST" action="{{ $client->exists ? route('app.clients.update', $client) : route('app.clients.store') }}" class="space-y-6">
    @csrf
    @if ($client->exists) @method('PUT') @endif

    <div class="grid lg:grid-cols-2 gap-6">
        <div class="bg-white rounded-xl border border-slate-100 p-6 space-y-5">
            <div class="flex items-center gap-6">
                <label class="flex items-center gap-2 text-sm font-medium text-slate-700">
                    <input type="radio" name="type" value="individual" @checked(old('type', $client->type) === 'individual') class="text-brand-600 focus:ring-brand-500">
                    {{ __('Individual') }}
                </label>
                <label class="flex items-center gap-2 text-sm font-medium text-slate-700">
                    <input type="radio" name="type" value="company" @checked(old('type', $client->type ?? 'company') === 'company') class="text-brand-600 focus:ring-brand-500">
                    {{ __('Company') }}
                </label>
            </div>

            <div>
                <label class="block text-sm font-medium text-slate-700">{{ __('Client code') }}</label>
                <input type="text" name="client_code" value="{{ old('client_code', $client->client_code) }}" required class="mt-1 w-full rounded-lg border border-slate-200 focus:border-brand-500 focus:ring-brand-500">
            </div>

            <div>
                <label class="block text-sm font-medium text-slate-700">{{ __('Name') }}</label>
                <input type="text" name="name" value="{{ old('name', $client->name) }}" required class="mt-1 w-full rounded-lg border border-slate-200 focus:border-brand-500 focus:ring-brand-500">
            </div>
            <div>
                <label class="block text-sm font-medium text-slate-700">{{ __('Name (Arabic)') }}</label>
                <input type="text" name="name_ar" value="{{ old('name_ar', $client->name_ar) }}" class="mt-1 w-full rounded-lg border border-slate-200 focus:border-brand-500 focus:ring-brand-500">
            </div>

            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1">{{ __('Is this client registered for VAT?') }}</label>
                <div class="flex items-center gap-6">
                    <label class="flex items-center gap-2 text-sm text-slate-600">
                        <input type="radio" name="is_vat_registered" value="1" @checked(old('is_vat_registered', $client->is_vat_registered ?? true)) class="text-brand-600 focus:ring-brand-500">
                        {{ __('Yes, VAT registered') }}
                    </label>
                    <label class="flex items-center gap-2 text-sm text-slate-600">
                        <input type="radio" name="is_vat_registered" value="0" @checked(! old('is_vat_registered', $client->is_vat_registered ?? true)) class="text-brand-600 focus:ring-brand-500">
                        {{ __('No, not VAT registered') }}
                    </label>
                </div>
            </div>
            <div>
                <label class="block text-sm font-medium text-slate-700">{{ __('VAT number') }}</label>
                <input type="text" name="vat_number" value="{{ old('vat_number', $client->vat_number) }}" class="mt-1 w-full rounded-lg border border-slate-200 focus:border-brand-500 focus:ring-brand-500">
            </div>
            <div>
                <label class="block text-sm font-medium text-slate-700">{{ __('CR number') }}</label>
                <input type="text" name="cr_number" value="{{ old('cr_number', $client->cr_number) }}" class="mt-1 w-full rounded-lg border border-slate-200 focus:border-brand-500 focus:ring-brand-500">
            </div>

            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-slate-700">{{ __('Additional ID type') }}</label>
                    <select name="additional_id_type" class="mt-1 w-full rounded-lg border border-slate-200 focus:border-brand-500 focus:ring-brand-500">
                        <option value="">{{ __('None') }}</option>
                        <option value="national_id" @selected(old('additional_id_type', $client->additional_id_type) === 'national_id')>{{ __('National ID') }}</option>
                        <option value="iqama" @selected(old('additional_id_type', $client->additional_id_type) === 'iqama')>{{ __('Iqama') }}</option>
                        <option value="passport" @selected(old('additional_id_type', $client->additional_id_type) === 'passport')>{{ __('Passport') }}</option>
                        <option value="gcc_id" @selected(old('additional_id_type', $client->additional_id_type) === 'gcc_id')>{{ __('GCC ID') }}</option>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700">{{ __('Additional ID number') }}</label>
                    <input type="text" name="additional_id_number" value="{{ old('additional_id_number', $client->additional_id_number) }}" class="mt-1 w-full rounded-lg border border-slate-200 focus:border-brand-500 focus:ring-brand-500">
                </div>
            </div>

            <div>
                <label class="block text-sm font-medium text-slate-700">{{ __('Initial balance') }}</label>
                <input type="number" step="0.01" name="initial_balance" value="{{ old('initial_balance', $client->initial_balance ?? 0) }}" class="mt-1 w-full rounded-lg border border-slate-200 focus:border-brand-500 focus:ring-brand-500">
            </div>

            <div>
                <label class="block text-sm font-medium text-slate-700">{{ __('Notes') }}</label>
                <textarea name="notes" rows="3" class="mt-1 w-full rounded-lg border border-slate-200 focus:border-brand-500 focus:ring-brand-500">{{ old('notes', $client->notes) }}</textarea>
            </div>
        </div>

        <div class="bg-white rounded-xl border border-slate-100 p-6 space-y-5">
            <div>
                <label class="block text-sm font-medium text-slate-700">{{ __('Contact name') }}</label>
                <input type="text" name="contact_name" value="{{ old('contact_name', $client->contact_name) }}" class="mt-1 w-full rounded-lg border border-slate-200 focus:border-brand-500 focus:ring-brand-500">
            </div>

            <div>
                <p class="block text-xs font-semibold uppercase text-slate-500 mb-2">{{ __('This contact includes') }}</p>
                <div class="flex items-center gap-6 text-sm text-slate-700">
                    <label class="flex items-center gap-2"><input type="checkbox" id="client-include-phone" checked class="rounded border-slate-300 text-brand-600 focus:ring-brand-500">{{ __('Phone') }}</label>
                    <label class="flex items-center gap-2"><input type="checkbox" id="client-include-email" checked class="rounded border-slate-300 text-brand-600 focus:ring-brand-500">{{ __('Email address') }}</label>
                    <label class="flex items-center gap-2"><input type="checkbox" id="client-include-address" checked class="rounded border-slate-300 text-brand-600 focus:ring-brand-500">{{ __('Address') }}</label>
                </div>
            </div>

            <div id="client-section-phone" class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-slate-700">{{ __('Phone') }}</label>
                    <input type="text" name="phone" value="{{ old('phone', $client->phone) }}" class="mt-1 w-full rounded-lg border border-slate-200 focus:border-brand-500 focus:ring-brand-500">
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700">{{ __('Mobile') }}</label>
                    <input type="text" name="mobile" value="{{ old('mobile', $client->mobile) }}" class="mt-1 w-full rounded-lg border border-slate-200 focus:border-brand-500 focus:ring-brand-500">
                </div>
            </div>
            <div id="client-section-email">
                <label class="block text-sm font-medium text-slate-700">{{ __('Email address') }}</label>
                <input type="email" name="email" value="{{ old('email', $client->email) }}" class="mt-1 w-full rounded-lg border border-slate-200 focus:border-brand-500 focus:ring-brand-500">
            </div>

            <div id="client-section-address">
                <h3 class="font-semibold text-slate-900 pt-2">{{ __('Address') }}</h3>
                <div class="mt-3">
                    <label class="block text-sm font-medium text-slate-700">{{ __('Street name') }}</label>
                    <input type="text" name="street_name" value="{{ old('street_name', $client->street_name) }}" class="mt-1 w-full rounded-lg border border-slate-200 focus:border-brand-500 focus:ring-brand-500">
                </div>
                <div class="mt-3">
                    <label class="block text-sm font-medium text-slate-700">{{ __('Building number') }}</label>
                    <input type="text" name="building_number" value="{{ old('building_number', $client->building_number) }}" class="mt-1 w-full rounded-lg border border-slate-200 focus:border-brand-500 focus:ring-brand-500">
                </div>
                <div class="grid grid-cols-2 gap-4 mt-3">
                    <div>
                        <label class="block text-sm font-medium text-slate-700">{{ __('District') }}</label>
                        <input type="text" name="district" value="{{ old('district', $client->district) }}" class="mt-1 w-full rounded-lg border border-slate-200 focus:border-brand-500 focus:ring-brand-500">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-700">{{ __('City') }}</label>
                        <input type="text" name="city" value="{{ old('city', $client->city) }}" class="mt-1 w-full rounded-lg border border-slate-200 focus:border-brand-500 focus:ring-brand-500">
                    </div>
                </div>
                <div class="grid grid-cols-2 gap-4 mt-3">
                    <div>
                        <label class="block text-sm font-medium text-slate-700">{{ __('State') }}</label>
                        <input type="text" name="state" value="{{ old('state', $client->state) }}" class="mt-1 w-full rounded-lg border border-slate-200 focus:border-brand-500 focus:ring-brand-500">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-700">{{ __('Postal code') }}</label>
                        <input type="text" name="postal_code" value="{{ old('postal_code', $client->postal_code) }}" class="mt-1 w-full rounded-lg border border-slate-200 focus:border-brand-500 focus:ring-brand-500">
                    </div>
                </div>
                <div class="mt-3">
                    <label class="block text-sm font-medium text-slate-700">{{ __('Country') }}</label>
                    <input type="text" name="country" value="{{ old('country', $client->country ?: ($client->exists ? '' : \App\Support\Countries::name(\App\Models\Setting::get('general_default_country')))) }}" class="mt-1 w-full rounded-lg border border-slate-200 focus:border-brand-500 focus:ring-brand-500">
                </div>
            </div>
        </div>
    </div>

    <div class="bg-white rounded-xl border border-slate-100 p-6">
        <h3 class="font-semibold text-slate-900 mb-4">{{ __('Additional contacts') }}</h3>
        <div id="contacts-body" class="space-y-3"></div>
        <button type="button" id="add-contact" class="mt-3 text-sm font-semibold text-brand-700 hover:underline">{{ __('+ Add new contact') }}</button>
    </div>

    @include('partials.custom-fields')

    <div class="flex gap-3">
        <button type="submit" class="rounded-lg bg-brand-600 px-6 py-2.5 font-semibold text-white hover:bg-brand-700">{{ __('Save') }}</button>
        <a href="{{ route('app.clients.index') }}" class="rounded-lg border border-slate-200 px-6 py-2.5 font-semibold text-slate-600 hover:border-slate-300">{{ __('Cancel') }}</a>
    </div>
</form>

<script>
(function () {
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

    bindToggle('client-include-phone', 'client-section-phone');
    bindToggle('client-include-email', 'client-section-email');
    bindToggle('client-include-address', 'client-section-address');
})();

(function () {
    const body = document.getElementById('contacts-body');
    let index = 0;

    function addRow(name = '', phone = '', email = '') {
        const i = index++;
        const row = document.createElement('div');
        row.className = 'grid grid-cols-12 gap-3 items-center';
        row.innerHTML = `
            <input type="text" name="contacts[${i}][name]" placeholder="${@json(__('Name'))}" value="${name}" class="col-span-4 rounded-lg border border-slate-200 text-sm focus:border-brand-500 focus:ring-brand-500">
            <input type="text" name="contacts[${i}][phone]" placeholder="${@json(__('Phone'))}" value="${phone}" class="col-span-3 rounded-lg border border-slate-200 text-sm focus:border-brand-500 focus:ring-brand-500">
            <input type="email" name="contacts[${i}][email]" placeholder="${@json(__('Email'))}" value="${email}" class="col-span-4 rounded-lg border border-slate-200 text-sm focus:border-brand-500 focus:ring-brand-500">
            <button type="button" class="col-span-1 text-slate-400 hover:text-red-600" data-remove>&times;</button>
        `;
        row.querySelector('[data-remove]').addEventListener('click', () => row.remove());
        body.appendChild(row);
    }

    document.getElementById('add-contact').addEventListener('click', () => addRow());

    const existing = @json($client->exists ? $client->contacts()->get(['name', 'phone', 'email']) : []);
    existing.forEach(c => addRow(c.name || '', c.phone || '', c.email || ''));
})();
</script>
@endsection
