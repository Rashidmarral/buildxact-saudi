@extends('layouts.app')

@section('title', __('Import clients'))

@section('content')
<div class="mb-6">
    <h1 class="text-2xl font-bold text-slate-900">{{ __('Import clients') }}</h1>
    <p class="text-sm text-slate-500 mt-1">{{ __('Add many clients at once from a CSV file.') }}</p>
</div>

@include('partials.csv-import-form', [
    'importAction' => route('app.clients.import.store'),
    'templateUrl' => route('app.clients.import.template'),
    'backUrl' => route('app.clients.index'),
    'columns' => [
        ['key' => 'name', 'label' => __('Name'), 'required' => true],
        ['key' => 'type', 'label' => __('Type (individual/company)'), 'required' => false],
        ['key' => 'email', 'label' => __('Email'), 'required' => false],
        ['key' => 'phone', 'label' => __('Phone'), 'required' => false],
        ['key' => 'vat_number', 'label' => __('VAT number'), 'required' => false],
        ['key' => 'cr_number', 'label' => __('CR number'), 'required' => false],
        ['key' => 'city', 'label' => __('City'), 'required' => false],
        ['key' => 'notes', 'label' => __('Notes'), 'required' => false],
    ],
])
@endsection
