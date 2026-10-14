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
                <label class="block text-sm font-medium text-slate-700">{{ __('Payment terms (days)') }}</label>
                <input type="number" min="0" max="365" name="payment_terms_days" value="{{ old('payment_terms_days', $client->payment_terms_days) }}" placeholder="{{ __('Use company default') }}" class="mt-1 w-full rounded-lg border border-slate-200 focus:border-brand-500 focus:ring-brand-500">
                <p class="text-xs text-slate-400 mt-1">{{ __('Overrides the company default when suggesting a due date on new invoices for this client.') }}</p>
            </div>

            <div>
                <label class="block text-sm font-medium text-slate-700">{{ __('Notes') }}</label>
                <textarea name="notes" rows="3" class="mt-1 w-full rounded-lg border border-slate-200 focus:border-brand-500 focus:ring-brand-500">{{ old('notes', $client->notes) }}</textarea>
            </div>

            <div class="grid sm:grid-cols-2 gap-5">
                <div>
                    <label class="block text-sm font-medium text-slate-700">{{ __('Lead source') }}</label>
                    <input type="text" name="lead_source" list="lead-source-options" value="{{ old('lead_source', $client->lead_source) }}" placeholder="{{ __('e.g. Referral, Website, Cold call') }}" class="mt-1 w-full rounded-lg border border-slate-200 focus:border-brand-500 focus:ring-brand-500">
                    <datalist id="lead-source-options">
                        <option value="{{ __('Referral') }}">
                        <option value="{{ __('Website') }}">
                        <option value="{{ __('Cold call') }}">
                        <option value="{{ __('Social media') }}">
                        <option value="{{ __('Trade show') }}">
                    </datalist>
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700">{{ __('Next follow-up date') }}</label>
                    <input type="date" name="next_follow_up_date" value="{{ old('next_follow_up_date', optional($client->next_follow_up_date)->format('Y-m-d')) }}" class="mt-1 w-full rounded-lg border border-slate-200 focus:border-brand-500 focus:ring-brand-500">
                </div>
            </div>

            @if ($client->exists)
                <div class="flex items-center justify-between rounded-lg border border-slate-200 px-4 py-3">
                    <p class="text-sm text-slate-500">
                        {{ __('Last contacted') }}:
                        <span class="font-medium text-slate-700">{{ $client->last_contacted_at ? $client->last_contacted_at->format('Y-m-d H:i') : __('Never') }}</span>
                    </p>
                    <button type="submit" form="log-contact-form" class="rounded-lg border border-slate-200 px-3 py-1.5 text-xs font-semibold text-slate-600 hover:border-brand-300">{{ __('Log contact now') }}</button>
                </div>
            @endif
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

@if ($client->exists)
    <form id="log-contact-form" method="POST" action="{{ route('app.clients.log-contact', $client) }}" class="hidden">
        @csrf
    </form>

    @if ($client->credit_hold)
        <div class="mt-6 rounded-xl border border-red-200 bg-red-50 px-5 py-4 flex items-center justify-between gap-3 flex-wrap">
            <div>
                <p class="text-sm font-semibold text-red-800">{{ __('This client is on credit hold') }}</p>
                @if ($client->credit_hold_reason)
                    <p class="text-xs text-red-600 mt-0.5">{{ $client->credit_hold_reason }}</p>
                @endif
                <p class="text-xs text-red-500 mt-0.5">{{ __('New invoices for this client cannot be sent until the hold is released.') }}</p>
            </div>
            <form method="POST" action="{{ route('app.clients.credit-hold', $client) }}">
                @csrf
                <input type="hidden" name="credit_hold" value="0">
                <button type="submit" class="rounded-lg border border-red-300 bg-white px-4 py-2 text-sm font-semibold text-red-700 hover:bg-red-100">{{ __('Release hold') }}</button>
            </form>
        </div>
    @else
        <div class="mt-6 flex justify-end">
            <button type="button" onclick="document.getElementById('credit-hold-form').classList.toggle('hidden')" class="text-xs text-slate-400 hover:text-red-600">{{ __('Put on credit hold') }}</button>
        </div>
        <form id="credit-hold-form" method="POST" action="{{ route('app.clients.credit-hold', $client) }}" class="hidden mt-2 rounded-xl border border-slate-200 bg-slate-50 p-4 flex flex-wrap items-end gap-3">
            @csrf
            <input type="hidden" name="credit_hold" value="1">
            <div class="flex-1 min-w-[200px]">
                <label class="block text-xs font-semibold uppercase text-slate-500">{{ __('Reason (optional)') }}</label>
                <input type="text" name="credit_hold_reason" placeholder="{{ __('e.g. 90+ days overdue, disputed invoice') }}" class="mt-1 w-full rounded-lg border border-slate-200 text-sm focus:border-brand-500 focus:ring-brand-500">
            </div>
            <button type="submit" class="rounded-lg bg-red-600 px-4 py-2 text-sm font-semibold text-white hover:bg-red-700">{{ __('Put on credit hold') }}</button>
        </form>
    @endif

    <div class="bg-white rounded-xl border border-slate-100 p-6 mt-6">
        <h3 class="font-semibold text-slate-900 mb-1">{{ __('Collection notes') }}</h3>
        <p class="text-xs text-slate-500 mb-4">{{ __('A record of collection calls and follow-ups, so the next person chasing this client isn\'t starting from zero.') }}</p>

        @if ($client->collectionNotes->isNotEmpty())
            <ul class="divide-y divide-slate-50 mb-4">
                @foreach ($client->collectionNotes as $note)
                    <li class="py-3 text-sm">
                        <div class="flex items-start justify-between gap-3">
                            <div>
                                <p class="text-slate-700">{{ $note->note }}</p>
                                @if ($note->promise_to_pay_date)
                                    <p class="text-xs text-amber-700 mt-1">{{ __('Promised :amount by :date', ['amount' => $note->promise_to_pay_amount ? \App\Support\Money::format($note->promise_to_pay_amount) : __('payment'), 'date' => $note->promise_to_pay_date->format('Y-m-d')]) }}</p>
                                @endif
                                <p class="text-xs text-slate-400 mt-1">{{ $note->creator?->name ?: '—' }} · {{ $note->created_at->format('Y-m-d H:i') }}</p>
                            </div>
                            <form method="POST" action="{{ route('app.collection-notes.destroy', $note) }}" onsubmit="return confirm('{{ __('Remove this note?') }}')">
                                @csrf @method('DELETE')
                                <button type="submit" class="text-xs text-slate-400 hover:text-red-600">{{ __('Remove') }}</button>
                            </form>
                        </div>
                    </li>
                @endforeach
            </ul>
        @endif

        <form method="POST" action="{{ route('app.clients.collection-notes.store', $client) }}" class="border-t border-slate-100 pt-4 space-y-3">
            @csrf
            <div>
                <label class="block text-xs font-semibold uppercase text-slate-500">{{ __('Note') }}</label>
                <textarea name="note" rows="2" required placeholder="{{ __('e.g. Called the client, they confirmed receipt and will pay next week.') }}" class="mt-1 w-full rounded-lg border border-slate-200 text-sm focus:border-brand-500 focus:ring-brand-500"></textarea>
            </div>
            <div class="grid sm:grid-cols-2 gap-3">
                <div>
                    <label class="block text-xs font-semibold uppercase text-slate-500">{{ __('Promise to pay date (optional)') }}</label>
                    <input type="date" name="promise_to_pay_date" class="mt-1 w-full rounded-lg border border-slate-200 text-sm focus:border-brand-500 focus:ring-brand-500">
                </div>
                <div>
                    <label class="block text-xs font-semibold uppercase text-slate-500">{{ __('Promised amount (optional)') }}</label>
                    <input type="number" step="0.01" min="0.01" name="promise_to_pay_amount" class="mt-1 w-full rounded-lg border border-slate-200 text-sm focus:border-brand-500 focus:ring-brand-500">
                </div>
            </div>
            <button type="submit" class="rounded-lg bg-brand-600 px-4 py-2 text-sm font-semibold text-white hover:bg-brand-700">{{ __('Add note') }}</button>
        </form>
    </div>
@endif

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
