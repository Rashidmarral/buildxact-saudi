@extends('layouts.app')

@section('title', __('Import items'))

@section('content')
<div class="mb-6">
    <h1 class="text-2xl font-bold text-slate-900">{{ __('Import items') }}</h1>
    <p class="text-sm text-slate-500 mt-1">{{ __('Add many items or services at once from a CSV file.') }}</p>
</div>

@include('partials.csv-import-form', [
    'importAction' => route('app.items.import.store'),
    'templateUrl' => route('app.items.import.template'),
    'backUrl' => route('app.items.index'),
    'columns' => [
        ['key' => 'name', 'label' => __('Name'), 'required' => true],
        ['key' => 'item_type', 'label' => __('Type (service/physical)'), 'required' => false],
        ['key' => 'sku', 'label' => __('SKU'), 'required' => false],
        ['key' => 'barcode', 'label' => __('Barcode'), 'required' => false],
        ['key' => 'category', 'label' => __('Category'), 'required' => false],
        ['key' => 'unit_price', 'label' => __('Sale price'), 'required' => true],
        ['key' => 'purchase_price', 'label' => __('Purchase price'), 'required' => false],
        ['key' => 'vat_rate', 'label' => __('VAT rate (%)'), 'required' => false],
    ],
])
@endsection
