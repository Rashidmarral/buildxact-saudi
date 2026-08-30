@extends('layouts.admin')

@section('title', $plan->exists ? __('Edit Plan') : __('New Plan'))

@section('content')
@php
    $featureLabels = [
        'has_recurring_invoices' => __('Recurring invoices'),
        'has_quotations' => __('Quotations & proforma invoices'),
        'has_stamps' => __('Company stamp on documents'),
        'has_financial_statements' => __('Financial statements (balance sheet & income statement)'),
        'has_vat_return_report' => __('VAT return report'),
        'has_cost_centers' => __('Cost centers'),
        'has_purchase_orders' => __('Purchase orders'),
        'has_debit_notes' => __('Debit notes (purchase returns)'),
        'has_roles_permissions' => __('Custom roles & permissions'),
        'has_zatca_phase2' => __('ZATCA Phase 2 integration (real-time clearance & reporting)'),
        'has_api' => __('API access'),
        'has_whatsapp' => __('WhatsApp notifications'),
    ];
@endphp
<form method="POST" action="{{ $plan->exists ? route('admin.plans.update', $plan) : route('admin.plans.store') }}" class="max-w-3xl space-y-6">
    @csrf
    @if ($plan->exists) @method('PUT') @endif

    <div class="bg-white rounded-xl border border-slate-100 p-6 space-y-5">
        <h2 class="font-semibold text-slate-900">{{ __('Plan details') }}</h2>
        <div class="grid sm:grid-cols-2 gap-5">
            <div>
                <label class="block text-sm font-medium text-slate-700">{{ __('Name') }}</label>
                <input type="text" name="name" value="{{ old('name', $plan->name) }}" required class="mt-1 w-full rounded-lg border border-slate-200 focus:border-brand-500 focus:ring-brand-500">
            </div>
            <div>
                <label class="block text-sm font-medium text-slate-700">{{ __('Name (Arabic)') }}</label>
                <input type="text" name="name_ar" value="{{ old('name_ar', $plan->name_ar) }}" class="mt-1 w-full rounded-lg border border-slate-200 focus:border-brand-500 focus:ring-brand-500">
            </div>
            <div class="sm:col-span-2">
                <label class="block text-sm font-medium text-slate-700">{{ __('Description') }}</label>
                <textarea name="description" rows="2" class="mt-1 w-full rounded-lg border border-slate-200 focus:border-brand-500 focus:ring-brand-500">{{ old('description', $plan->description) }}</textarea>
            </div>
            <div>
                <label class="block text-sm font-medium text-slate-700">{{ __('Currency') }}</label>
                <select name="currency" class="mt-1 w-full rounded-lg border border-slate-200 focus:border-brand-500 focus:ring-brand-500">
                    @foreach ($currencies as $code)
                        <option value="{{ $code }}" @selected(old('currency', $plan->currency ?? 'SAR') === $code)>{{ $code }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="block text-sm font-medium text-slate-700">{{ __('Trial period (days)') }}</label>
                <input type="number" min="1" max="365" name="trial_days" value="{{ old('trial_days', $plan->trial_days) }}" class="mt-1 w-full rounded-lg border border-slate-200 focus:border-brand-500 focus:ring-brand-500">
                <p class="text-xs text-slate-400 mt-1">{{ __('Leave blank to use the platform-wide trial length.') }}</p>
            </div>
            <div>
                <label class="block text-sm font-medium text-slate-700">{{ __('Monthly price (SAR)') }}</label>
                <input type="number" step="0.01" min="0" name="price_monthly" value="{{ old('price_monthly', $plan->price_monthly) }}" required class="mt-1 w-full rounded-lg border border-slate-200 focus:border-brand-500 focus:ring-brand-500">
            </div>
            <div>
                <label class="block text-sm font-medium text-slate-700">{{ __('Monthly price before discount (optional)') }}</label>
                <input type="number" step="0.01" min="0" name="price_monthly_original" value="{{ old('price_monthly_original', $plan->price_monthly_original) }}" class="mt-1 w-full rounded-lg border border-slate-200 focus:border-brand-500 focus:ring-brand-500">
                <p class="text-xs text-slate-400 mt-1">{{ __('Shown struck through on the pricing page. Leave blank to hide.') }}</p>
            </div>
            <div>
                <label class="block text-sm font-medium text-slate-700">{{ __('Yearly price (SAR)') }}</label>
                <input type="number" step="0.01" min="0" name="price_yearly" value="{{ old('price_yearly', $plan->price_yearly) }}" required class="mt-1 w-full rounded-lg border border-slate-200 focus:border-brand-500 focus:ring-brand-500">
            </div>
            <div>
                <label class="block text-sm font-medium text-slate-700">{{ __('Yearly price before discount (optional)') }}</label>
                <input type="number" step="0.01" min="0" name="price_yearly_original" value="{{ old('price_yearly_original', $plan->price_yearly_original) }}" class="mt-1 w-full rounded-lg border border-slate-200 focus:border-brand-500 focus:ring-brand-500">
            </div>
            <div>
                <label class="block text-sm font-medium text-slate-700">{{ __('Sort order') }}</label>
                <input type="number" min="0" name="sort_order" value="{{ old('sort_order', $plan->sort_order ?? 0) }}" class="mt-1 w-full rounded-lg border border-slate-200 focus:border-brand-500 focus:ring-brand-500">
            </div>
        </div>
        <div class="space-y-2">
            <label class="flex items-center gap-2 text-sm text-slate-600">
                <input type="checkbox" name="is_active" value="1" @checked(old('is_active', $plan->is_active ?? true)) class="rounded border-slate-300 text-brand-600 focus:ring-brand-500">
                {{ __('Active') }}
            </label>
            <label class="flex items-center gap-2 text-sm text-slate-600">
                <input type="checkbox" name="is_public" value="1" @checked(old('is_public', $plan->is_public ?? true)) class="rounded border-slate-300 text-brand-600 focus:ring-brand-500">
                {{ __('Public (shown on pricing page and offered at signup)') }}
            </label>
            <label class="flex items-center gap-2 text-sm text-slate-600">
                <input type="checkbox" name="is_featured" value="1" @checked(old('is_featured', $plan->is_featured ?? false)) class="rounded border-slate-300 text-brand-600 focus:ring-brand-500">
                {{ __('Featured (highlighted on the pricing page)') }}
            </label>
            <p class="text-xs text-slate-400">{{ __('An inactive or private plan can still be assigned to a company manually from Companies → Subscription.') }}</p>
        </div>
    </div>

    <div class="bg-white rounded-xl border border-slate-100 p-6 space-y-5">
        <div>
            <h2 class="font-semibold text-slate-900">{{ __('Usage limits') }}</h2>
            <p class="text-sm text-slate-500 mt-1">{{ __('Leave a field blank for unlimited.') }}</p>
        </div>
        <div class="grid sm:grid-cols-2 lg:grid-cols-3 gap-5">
            @foreach ([
                'max_users' => __('Team members'),
                'max_invoices_per_month' => __('Invoices per month'),
                'max_customers' => __('Customers'),
                'max_suppliers' => __('Suppliers'),
                'max_invoice_templates' => __('Invoice templates'),
                'max_warehouses' => __('Warehouses'),
                'max_bank_accounts' => __('Bank & cash accounts'),
                'max_branches' => __('Branches'),
                'max_storage_mb' => __('Storage (MB)'),
                'max_items' => __('Products'),
                'max_api_calls_per_month' => __('API calls per month'),
            ] as $field => $label)
                <div>
                    <label class="block text-sm font-medium text-slate-700">{{ $label }}</label>
                    <input type="number" min="1" name="{{ $field }}" value="{{ old($field, $plan->{$field}) }}" class="mt-1 w-full rounded-lg border border-slate-200 focus:border-brand-500 focus:ring-brand-500">
                </div>
            @endforeach
        </div>
    </div>

    <div class="bg-white rounded-xl border border-slate-100 p-6 space-y-4">
        <h2 class="font-semibold text-slate-900">{{ __('Features included') }}</h2>
        <div class="grid sm:grid-cols-2 gap-3">
            @foreach ($featureLabels as $field => $label)
                <label class="flex items-center gap-2 text-sm text-slate-600">
                    <input type="checkbox" name="{{ $field }}" value="1" @checked(old($field, $plan->{$field} ?? false)) class="rounded border-slate-300 text-brand-600 focus:ring-brand-500">
                    {{ $label }}
                </label>
            @endforeach
        </div>
    </div>

    <div class="bg-white rounded-xl border border-slate-100 p-6">
        <label class="block text-sm font-medium text-slate-700">{{ __('Feature list shown on the pricing card (one per line)') }}</label>
        <textarea name="features" rows="4" class="mt-1 w-full rounded-lg border border-slate-200 focus:border-brand-500 focus:ring-brand-500">{{ old('features', is_array($plan->features) ? implode("\n", $plan->features) : '') }}</textarea>
        <p class="text-xs text-slate-400 mt-1">{{ __('Free-form highlights shown as bullet points under the price — separate from the comparison table, which reflects the limits and features above automatically.') }}</p>
    </div>

    <div class="flex gap-3">
        <button type="submit" class="rounded-lg bg-brand-600 px-6 py-2.5 font-semibold text-white hover:bg-brand-700">{{ __('Save') }}</button>
        <a href="{{ route('admin.plans.index') }}" class="rounded-lg border border-slate-200 px-6 py-2.5 font-semibold text-slate-600 hover:border-slate-300">{{ __('Cancel') }}</a>
    </div>
</form>
@endsection
