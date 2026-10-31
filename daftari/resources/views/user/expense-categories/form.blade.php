@extends('layouts.app')

@section('title', $category->exists ? __('Edit Expense Category') : __('New Expense Category'))

@section('content')
<form method="POST" action="{{ $category->exists ? route('app.expense-categories.update', $category) : route('app.expense-categories.store') }}" class="space-y-6">
    @csrf
    @if ($category->exists) @method('PUT') @endif

    <div class="grid lg:grid-cols-2 gap-6">
        <div class="bg-white rounded-xl border border-slate-100 p-6 space-y-5">
            <div>
                <label class="block text-xs font-semibold uppercase text-slate-500">{{ __('Category name') }}</label>
                <input type="text" name="name" value="{{ old('name', $category->name) }}" required class="mt-1 w-full rounded-lg border border-slate-200 focus:border-brand-500 focus:ring-brand-500">
            </div>
            <div>
                <label class="block text-xs font-semibold uppercase text-slate-500">{{ __('Name (Arabic)') }}</label>
                <input type="text" name="name_ar" value="{{ old('name_ar', $category->name_ar) }}" dir="rtl" class="mt-1 w-full rounded-lg border border-slate-200 focus:border-brand-500 focus:ring-brand-500">
            </div>
            <div>
                <label class="block text-xs font-semibold uppercase text-slate-500">{{ __('Category code') }}</label>
                <input type="text" name="code" value="{{ old('code', $category->code) }}" placeholder="{{ __('e.g. 6100') }}" class="mt-1 w-full rounded-lg border border-slate-200 font-mono focus:border-brand-500 focus:ring-brand-500">
                <p class="text-xs text-slate-400 mt-1">{{ __('A short stable code for exports and reporting — any format you like.') }}</p>
            </div>
            <div>
                <label class="block text-xs font-semibold uppercase text-slate-500">{{ __('Group') }}</label>
                <select name="parent_category_id" class="mt-1 w-full rounded-lg border border-slate-200 focus:border-brand-500 focus:ring-brand-500">
                    <option value="">{{ __('— None —') }}</option>
                    @foreach ($parentOptions as $option)
                        <option value="{{ $option->id }}" @selected((string) old('parent_category_id', $category->parent_category_id) === (string) $option->id)>{{ $option->name }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="flex items-center gap-2 text-sm font-medium text-slate-700">
                    <input type="checkbox" name="is_active" value="1" @checked(old('is_active', $category->exists ? $category->is_active : true)) class="rounded border-slate-300 text-brand-600 focus:ring-brand-500">
                    {{ __('Active') }}
                </label>
                <p class="text-xs text-slate-400 mt-1">{{ __('Inactive categories stay on past transactions but no longer appear when recording a new expense.') }}</p>
            </div>
        </div>

        <div class="bg-white rounded-xl border border-slate-100 p-6 space-y-5">
            <div>
                <label class="block text-xs font-semibold uppercase text-slate-500">{{ __('Posts to GL account') }}</label>
                <select name="linked_account_id" class="mt-1 w-full rounded-lg border border-slate-200 focus:border-brand-500 focus:ring-brand-500">
                    <option value="">{{ __('— Use the company default (Operating Expenses) —') }}</option>
                    @foreach ($accountOptions as $account)
                        <option value="{{ $account->id }}" @selected((string) old('linked_account_id', $category->linked_account_id) === (string) $account->id)>{{ $account->code }} — {{ $account->name }}</option>
                    @endforeach
                </select>
                <p class="text-xs text-slate-400 mt-1">{{ __('A specific expense on this category can still override this per transaction.') }}</p>
            </div>
            <div>
                <label class="flex items-center gap-2 text-sm font-medium text-slate-700">
                    <input type="checkbox" name="is_direct_cost" value="1" @checked(old('is_direct_cost', $category->is_direct_cost)) class="rounded border-slate-300 text-brand-600 focus:ring-brand-500">
                    {{ __('Direct project cost') }}
                </label>
                <p class="text-xs text-slate-400 mt-1">{{ __('Separates direct project cost from general operating expense for project profitability.') }}</p>
            </div>
            <div>
                <label class="flex items-center gap-2 text-sm font-medium text-slate-700">
                    <input type="checkbox" name="requires_project" value="1" @checked(old('requires_project', $category->requires_project)) class="rounded border-slate-300 text-brand-600 focus:ring-brand-500">
                    {{ __('Requires a project to be selected') }}
                </label>
            </div>
            <div>
                <label class="flex items-center gap-2 text-sm font-medium text-slate-700">
                    <input type="checkbox" name="vat_eligible" value="1" @checked(old('vat_eligible', $category->exists ? $category->vat_eligible : true)) class="rounded border-slate-300 text-brand-600 focus:ring-brand-500">
                    {{ __('Input VAT normally recoverable') }}
                </label>
            </div>
        </div>
    </div>

    <div class="flex items-center gap-3">
        <button type="submit" class="rounded-lg bg-brand-600 px-5 py-2.5 text-sm font-semibold text-white hover:bg-brand-700">{{ $category->exists ? __('Save changes') : __('Add category') }}</button>
        <a href="{{ route('app.expense-categories.index') }}" class="text-sm text-slate-500 hover:text-slate-700">{{ __('Cancel') }}</a>
    </div>
</form>
@endsection
