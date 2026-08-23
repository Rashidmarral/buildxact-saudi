@extends('layouts.app')

@section('title', __('Settings'))

@section('content')
<form method="POST" action="{{ route('app.settings.update') }}" enctype="multipart/form-data" class="max-w-2xl bg-white rounded-xl border border-slate-100 p-6 space-y-5">
    @csrf
    @method('PUT')

    <h3 class="font-semibold text-slate-900">{{ __('Company details') }}</h3>
    <div>
        <label class="block text-sm font-medium text-slate-700">{{ __('Company logo') }}</label>
        <div class="mt-1 flex items-center gap-4">
            @if ($company->logo_path)
                <img src="{{ Storage::url($company->logo_path) }}" alt="{{ __('Company logo') }}" class="h-14 w-14 rounded-lg object-cover border border-slate-200">
                <a href="{{ Storage::url($company->logo_path) }}" target="_blank" class="text-xs font-semibold text-brand-700 hover:underline">{{ __('View') }}</a>
            @else
                <div class="h-14 w-14 rounded-lg border border-dashed border-slate-200 flex items-center justify-center text-slate-300 text-xs">{{ __('No logo') }}</div>
            @endif
            <input type="file" name="logo" accept="image/*" class="text-sm text-slate-600 file:mr-3 file:rounded-lg file:border-0 file:bg-slate-100 file:px-3 file:py-2 file:text-sm file:font-semibold file:text-slate-600 hover:file:bg-slate-200">
        </div>
        <p class="mt-1 text-xs text-slate-400">{{ __('Used on invoices, quotations, and other documents when the active template shows the logo.') }}</p>
    </div>
    <div>
        <label class="block text-sm font-medium text-slate-700">{{ __('Company stamp') }}</label>
        <div class="mt-1 flex items-center gap-4">
            @if ($company->stamp_path)
                <img src="{{ Storage::url($company->stamp_path) }}" alt="{{ __('Company stamp') }}" class="h-14 w-14 rounded-lg object-cover border border-slate-200">
                <a href="{{ Storage::url($company->stamp_path) }}" target="_blank" class="text-xs font-semibold text-brand-700 hover:underline">{{ __('View') }}</a>
            @else
                <div class="h-14 w-14 rounded-lg border border-dashed border-slate-200 flex items-center justify-center text-slate-300 text-xs">{{ __('No stamp') }}</div>
            @endif
            @if ($company->hasFeature('stamps'))
                <input type="file" name="stamp" accept="image/*" class="text-sm text-slate-600 file:mr-3 file:rounded-lg file:border-0 file:bg-slate-100 file:px-3 file:py-2 file:text-sm file:font-semibold file:text-slate-600 hover:file:bg-slate-200">
            @else
                <span class="rounded-lg bg-amber-50 border border-amber-200 px-3 py-1.5 text-xs font-medium text-amber-700">{{ __('Not included in your current plan') }}</span>
            @endif
        </div>
        <p class="mt-1 text-xs text-slate-400">{{ __('Shown on bills and purchase orders when uploaded.') }}</p>
    </div>
    <div class="grid sm:grid-cols-2 gap-5">
        <div>
            <label class="block text-sm font-medium text-slate-700">{{ __('Company name') }}</label>
            <input type="text" name="name" value="{{ old('name', $company->name) }}" required class="mt-1 w-full rounded-lg border border-slate-200 focus:border-brand-500 focus:ring-brand-500">
        </div>
        <div>
            <label class="block text-sm font-medium text-slate-700">{{ __('Company name (Arabic)') }}</label>
            <input type="text" name="name_ar" value="{{ old('name_ar', $company->name_ar) }}" class="mt-1 w-full rounded-lg border border-slate-200 focus:border-brand-500 focus:ring-brand-500">
        </div>
        <div>
            <label class="block text-sm font-medium text-slate-700">{{ __('VAT number') }}</label>
            <input type="text" name="vat_number" value="{{ old('vat_number', $company->vat_number) }}" class="mt-1 w-full rounded-lg border border-slate-200 focus:border-brand-500 focus:ring-brand-500">
        </div>
        <div>
            <label class="block text-sm font-medium text-slate-700">{{ __('CR number') }}</label>
            <input type="text" name="cr_number" value="{{ old('cr_number', $company->cr_number) }}" class="mt-1 w-full rounded-lg border border-slate-200 focus:border-brand-500 focus:ring-brand-500">
        </div>
        <div>
            <label class="block text-sm font-medium text-slate-700">{{ __('Alternative seller ID type') }}</label>
            <select name="alternative_seller_id_type" class="mt-1 w-full rounded-lg border border-slate-200 focus:border-brand-500 focus:ring-brand-500">
                <option value="">{{ __('None') }}</option>
                <option value="commercial_registration" @selected(old('alternative_seller_id_type', $company->alternative_seller_id_type) === 'commercial_registration')>{{ __('Commercial registration') }}</option>
                <option value="momra_license" @selected(old('alternative_seller_id_type', $company->alternative_seller_id_type) === 'momra_license')>{{ __('MOMRA license') }}</option>
                <option value="mhrsd_license" @selected(old('alternative_seller_id_type', $company->alternative_seller_id_type) === 'mhrsd_license')>{{ __('MHRSD license') }}</option>
                <option value="passport_number" @selected(old('alternative_seller_id_type', $company->alternative_seller_id_type) === 'passport_number')>{{ __('Passport number') }}</option>
                <option value="gcc_id" @selected(old('alternative_seller_id_type', $company->alternative_seller_id_type) === 'gcc_id')>{{ __('GCC ID') }}</option>
                <option value="other_id" @selected(old('alternative_seller_id_type', $company->alternative_seller_id_type) === 'other_id')>{{ __('Other ID') }}</option>
            </select>
            <p class="text-xs text-slate-400 mt-1">{{ __('Used only when no CR number is set.') }}</p>
        </div>
        <div>
            <label class="block text-sm font-medium text-slate-700">{{ __('Alternative seller ID') }}</label>
            <input type="text" name="alternative_seller_id" value="{{ old('alternative_seller_id', $company->alternative_seller_id) }}" class="mt-1 w-full rounded-lg border border-slate-200 focus:border-brand-500 focus:ring-brand-500">
        </div>
        <div>
            <label class="block text-sm font-medium text-slate-700">{{ __('Email') }}</label>
            <input type="email" name="email" value="{{ old('email', $company->email) }}" class="mt-1 w-full rounded-lg border border-slate-200 focus:border-brand-500 focus:ring-brand-500">
        </div>
        <div>
            <label class="block text-sm font-medium text-slate-700">{{ __('Phone') }}</label>
            <input type="text" name="phone" value="{{ old('phone', $company->phone) }}" class="mt-1 w-full rounded-lg border border-slate-200 focus:border-brand-500 focus:ring-brand-500">
        </div>
        <div>
            <label class="block text-sm font-medium text-slate-700">{{ __('City') }}</label>
            <input type="text" name="city" value="{{ old('city', $company->city) }}" class="mt-1 w-full rounded-lg border border-slate-200 focus:border-brand-500 focus:ring-brand-500">
        </div>
        <div>
            <label class="block text-sm font-medium text-slate-700">{{ __('Invoice prefix') }}</label>
            <input type="text" name="invoice_prefix" value="{{ old('invoice_prefix', $company->invoice_prefix) }}" required class="mt-1 w-full rounded-lg border border-slate-200 focus:border-brand-500 focus:ring-brand-500">
        </div>
        <div>
            <label class="block text-sm font-medium text-slate-700">{{ __('Primary customer type') }}</label>
            <select name="primary_customer_type" class="mt-1 w-full rounded-lg border border-slate-200 focus:border-brand-500 focus:ring-brand-500">
                <option value="mixed" @selected(old('primary_customer_type', $company->primary_customer_type ?? 'mixed') === 'mixed')>{{ __('Mixed (both)') }}</option>
                <option value="b2b" @selected(old('primary_customer_type', $company->primary_customer_type) === 'b2b')>{{ __('Businesses (B2B)') }}</option>
                <option value="b2c" @selected(old('primary_customer_type', $company->primary_customer_type) === 'b2c')>{{ __('Individuals (B2C)') }}</option>
            </select>
        </div>
        <div class="sm:col-span-2">
            <label class="block text-sm font-medium text-slate-700">{{ __('Address') }}</label>
            <input type="text" name="address" value="{{ old('address', $company->address) }}" class="mt-1 w-full rounded-lg border border-slate-200 focus:border-brand-500 focus:ring-brand-500">
            <p class="text-xs text-slate-400 mt-1">{{ __('Free-text address shown on printed documents. Fill in the National Address fields below for ZATCA compliance.') }}</p>
        </div>
        <div>
            <label class="block text-sm font-medium text-slate-700">{{ __('Default branch') }}</label>
            <select name="default_branch_id" class="mt-1 w-full rounded-lg border border-slate-200 focus:border-brand-500 focus:ring-brand-500">
                <option value="">{{ __('None — use company details') }}</option>
                @foreach ($branches as $branch)
                    <option value="{{ $branch->id }}" @selected(old('default_branch_id', $company->default_branch_id) == $branch->id)>{{ $branch->name }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label class="block text-sm font-medium text-slate-700">{{ __('Default bank account for invoices') }}</label>
            <select name="default_bank_account_id" class="mt-1 w-full rounded-lg border border-slate-200 focus:border-brand-500 focus:ring-brand-500">
                <option value="">{{ __('None') }}</option>
                @foreach ($bankAccounts as $account)
                    <option value="{{ $account->id }}" @selected(old('default_bank_account_id', $company->default_bank_account_id) == $account->id)>{{ $account->name }}</option>
                @endforeach
            </select>
            <p class="text-xs text-slate-400 mt-1">{{ __('Shown on invoices so customers know where to pay.') }}</p>
        </div>
    </div>

    <h3 class="font-semibold text-slate-900 pt-2">{{ __('National Address (required for ZATCA)') }}</h3>
    <p class="text-xs text-slate-500 -mt-3">{{ __('The structured Saudi National Address ZATCA requires on every tax invoice — separate from the free-text address above.') }}</p>
    <div class="grid sm:grid-cols-2 gap-5">
        <div>
            <label class="block text-sm font-medium text-slate-700">{{ __('Building number') }}</label>
            <input type="text" name="building_number" maxlength="20" value="{{ old('building_number', $company->building_number) }}" class="mt-1 w-full rounded-lg border border-slate-200 focus:border-brand-500 focus:ring-brand-500">
        </div>
        <div>
            <label class="block text-sm font-medium text-slate-700">{{ __('Street name') }}</label>
            <input type="text" name="street_name" value="{{ old('street_name', $company->street_name) }}" class="mt-1 w-full rounded-lg border border-slate-200 focus:border-brand-500 focus:ring-brand-500">
        </div>
        <div>
            <label class="block text-sm font-medium text-slate-700">{{ __('District') }}</label>
            <input type="text" name="district" value="{{ old('district', $company->district) }}" class="mt-1 w-full rounded-lg border border-slate-200 focus:border-brand-500 focus:ring-brand-500">
        </div>
        <div>
            <label class="block text-sm font-medium text-slate-700">{{ __('Postal code') }}</label>
            <input type="text" name="postal_code" maxlength="20" value="{{ old('postal_code', $company->postal_code) }}" class="mt-1 w-full rounded-lg border border-slate-200 focus:border-brand-500 focus:ring-brand-500">
        </div>
        <div>
            <label class="block text-sm font-medium text-slate-700">{{ __('Additional number') }}</label>
            <input type="text" name="additional_number" maxlength="20" value="{{ old('additional_number', $company->additional_number) }}" class="mt-1 w-full rounded-lg border border-slate-200 focus:border-brand-500 focus:ring-brand-500">
            <p class="text-xs text-slate-400 mt-1">{{ __('The 4-digit unique number completing your National Address.') }}</p>
        </div>
    </div>

    <h3 class="font-semibold text-slate-900 pt-2">{{ __('Number formatting') }}</h3>
    <div>
        <label class="block text-sm font-medium text-slate-700">{{ __('Negative numbers') }}</label>
        <select name="negative_number_format" id="negative_number_format" class="mt-1 w-full max-w-xs rounded-lg border border-slate-200 focus:border-brand-500 focus:ring-brand-500">
            <option value="minus" @selected(old('negative_number_format', $company->negative_number_format ?? 'minus') === 'minus')>{{ __('Show as -101,600') }}</option>
            <option value="parentheses" @selected(old('negative_number_format', $company->negative_number_format) === 'parentheses')>{{ __('Show as (101,600)') }}</option>
        </select>
        <p class="text-xs text-slate-400 mt-2">{{ __('Preview:') }} <span id="format-preview" class="font-mono font-medium text-slate-600"></span></p>
    </div>

    <button type="submit" class="rounded-lg bg-brand-600 px-6 py-2.5 font-semibold text-white hover:bg-brand-700">{{ __('Save settings') }}</button>
</form>

<div class="max-w-2xl mt-6 bg-white rounded-xl border border-slate-100 p-6">
    <h3 class="font-semibold text-slate-900">{{ __('Company documents') }}</h3>
    <p class="text-sm text-slate-500 mt-1">{{ __('Keep copies of your commercial registration, VAT certificate, and other compliance documents on file — useful for your own records and when applying for ZATCA onboarding.') }}</p>

    @if ($documents->isNotEmpty())
        <ul class="mt-4 divide-y divide-slate-50">
            @foreach ($documents as $document)
                <li class="flex items-center justify-between py-3 text-sm">
                    <div class="flex items-center gap-3">
                        @include('partials.icon', ['name' => 'clipboard', 'class' => 'h-5 w-5 text-slate-400 shrink-0'])
                        <div>
                            <p class="font-medium text-slate-800">{{ __(\App\Http\Controllers\User\SettingsController::DOCUMENT_TYPES[$document->document_type] ?? $document->document_type) }}</p>
                            <p class="text-xs text-slate-400">
                                {{ $document->original_name }} &middot; {{ $document->humanSize() }}
                                @if ($document->expiry_date)
                                    &middot;
                                    <span class="{{ $document->isExpired() ? 'text-red-600 font-semibold' : ($document->isExpiringSoon() ? 'text-amber-600 font-semibold' : '') }}">
                                        {{ __('Expires') }}: {{ $document->expiry_date->format('Y-m-d') }}
                                        @if ($document->isExpired())
                                            ({{ __('expired') }})
                                        @elseif ($document->isExpiringSoon())
                                            ({{ __('expiring soon') }})
                                        @endif
                                    </span>
                                @endif
                            </p>
                        </div>
                    </div>
                    <div class="flex items-center gap-3">
                        <a href="{{ Storage::url($document->path) }}" target="_blank" class="text-brand-700 hover:underline font-medium">{{ __('View') }}</a>
                        <form method="POST" action="{{ route('app.settings.documents.destroy', $document) }}" onsubmit="return confirm('{{ __('Remove this document?') }}')">
                            @csrf @method('DELETE')
                            <button type="submit" class="text-red-600 hover:underline font-medium">{{ __('Remove') }}</button>
                        </form>
                    </div>
                </li>
            @endforeach
        </ul>
    @else
        <p class="mt-4 text-sm text-slate-400">{{ __('No documents uploaded yet.') }}</p>
    @endif

    <form method="POST" action="{{ route('app.settings.documents.store') }}" enctype="multipart/form-data" class="mt-5 pt-5 border-t border-slate-100 grid sm:grid-cols-3 gap-3 items-end">
        @csrf
        <div>
            <label class="block text-xs font-medium text-slate-500">{{ __('Document type') }}</label>
            <select name="document_type" required class="mt-1 w-full rounded-lg border border-slate-200 text-sm focus:border-brand-500 focus:ring-brand-500">
                @foreach (\App\Http\Controllers\User\SettingsController::DOCUMENT_TYPES as $value => $label)
                    <option value="{{ $value }}">{{ __($label) }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label class="block text-xs font-medium text-slate-500">{{ __('Expiry date (optional)') }}</label>
            <input type="date" name="expiry_date" class="mt-1 w-full rounded-lg border border-slate-200 text-sm focus:border-brand-500 focus:ring-brand-500">
        </div>
        <div>
            <label class="block text-xs font-medium text-slate-500">{{ __('File (PDF or image)') }}</label>
            <input type="file" name="file" accept=".pdf,image/*" required class="mt-1 w-full text-sm text-slate-600 file:mr-3 file:rounded-lg file:border-0 file:bg-slate-100 file:px-3 file:py-1.5 file:text-sm file:font-semibold file:text-slate-600 hover:file:bg-slate-200">
        </div>
        <div class="sm:col-span-3">
            <button type="submit" class="rounded-lg border border-slate-200 px-4 py-2 text-sm font-semibold text-slate-600 hover:border-slate-300">{{ __('Upload document') }}</button>
        </div>
    </form>
</div>

<div class="max-w-2xl mt-6 bg-white rounded-xl border border-slate-100 p-6">
    <h3 class="font-semibold text-slate-900">{{ __('Change password') }}</h3>
    <p class="text-sm text-slate-500 mt-1">{{ __('Update the password you use to log in to your account.') }}</p>
    <form method="POST" action="{{ route('app.settings.password') }}" class="mt-4 space-y-4 max-w-md">
        @csrf
        @method('PUT')
        <div>
            <label class="block text-sm font-medium text-slate-700">{{ __('Current password') }}</label>
            <input type="password" name="current_password" required class="mt-1 w-full rounded-lg border border-slate-200 focus:border-brand-500 focus:ring-brand-500">
        </div>
        <div>
            <label class="block text-sm font-medium text-slate-700">{{ __('New password') }}</label>
            <input type="password" name="password" required class="mt-1 w-full rounded-lg border border-slate-200 focus:border-brand-500 focus:ring-brand-500">
        </div>
        <div>
            <label class="block text-sm font-medium text-slate-700">{{ __('Confirm new password') }}</label>
            <input type="password" name="password_confirmation" required class="mt-1 w-full rounded-lg border border-slate-200 focus:border-brand-500 focus:ring-brand-500">
        </div>
        <button type="submit" class="rounded-lg bg-brand-600 px-6 py-2.5 font-semibold text-white hover:bg-brand-700">{{ __('Update password') }}</button>
    </form>
</div>

<div class="max-w-2xl mt-6 bg-white rounded-xl border border-slate-100 p-6 flex items-center justify-between">
    <div>
        <h3 class="font-semibold text-slate-900">{{ __('Branches') }}</h3>
        <p class="text-sm text-slate-500">
            @if ($company->default_branch_id)
                {{ __('Default branch:') }} {{ $company->defaultBranch()?->name }}
            @else
                {{ __('No branches yet — your company details are used on new documents.') }}
            @endif
        </p>
    </div>
    <a href="{{ route('app.branches.index') }}" class="rounded-lg border border-slate-200 px-4 py-2 text-sm font-semibold text-slate-600 hover:border-slate-300">{{ __('Manage branches') }}</a>
</div>

<script>
(function () {
    const select = document.getElementById('negative_number_format');
    const preview = document.getElementById('format-preview');
    function update() {
        preview.textContent = select.value === 'parentheses' ? '(101,600)' : '-101,600';
    }
    select.addEventListener('change', update);
    update();
})();
</script>
@endsection
