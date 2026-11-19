@extends('layouts.app')

@section('title', $budget->exists ? __('Edit Budget') : __('New Budget'))

@section('content')
<div class="max-w-3xl">
    <h1 class="text-xl font-bold text-slate-900 mb-1">{{ $budget->exists ? __('Edit budget') : __('New budget') }}</h1>
    <p class="text-sm text-slate-500 mb-6">{{ __('Enter an annual budgeted amount for each account you want to track. Leave an account blank to skip it.') }}</p>

    <form method="POST" action="{{ $budget->exists ? route('app.budgets.update', $budget) : route('app.budgets.store') }}" class="bg-white rounded-xl border border-slate-100 p-6 space-y-5">
        @csrf
        @if ($budget->exists) @method('PUT') @endif

        <div class="grid sm:grid-cols-2 gap-4">
            <div>
                <label class="block text-xs font-medium text-slate-500 mb-1">{{ __('Name') }}</label>
                <input type="text" name="name" value="{{ old('name', $budget->name) }}" required placeholder="{{ __('e.g. Annual Operating Budget') }}" class="w-full rounded-lg border border-slate-200 text-sm">
                @error('name')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
            </div>
            <div>
                <label class="block text-xs font-medium text-slate-500 mb-1">{{ __('Fiscal year') }}</label>
                <input type="number" name="fiscal_year" value="{{ old('fiscal_year', $budget->fiscal_year) }}" required min="2000" max="2100" class="w-full rounded-lg border border-slate-200 text-sm">
                @error('fiscal_year')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
            </div>
        </div>

        <div>
            <h2 class="text-sm font-semibold text-slate-900 mb-3">{{ __('Account budgets') }}</h2>
            <div class="border border-slate-100 rounded-lg divide-y divide-slate-100 max-h-[28rem] overflow-y-auto">
                @php($grouped = $accounts->groupBy('type'))
                @foreach (['revenue' => __('Revenue'), 'expense' => __('Expenses')] as $type => $label)
                    @if (($grouped[$type] ?? collect())->isNotEmpty())
                        <div class="bg-slate-50 px-4 py-2 text-xs font-semibold uppercase text-slate-500">{{ $label }}</div>
                        @foreach ($grouped[$type] as $account)
                            <div class="flex items-center justify-between gap-4 px-4 py-2">
                                <label class="text-sm text-slate-700">{{ $account->code }} — {{ $account->name }}</label>
                                <input type="number" step="0.01" min="0" name="lines[{{ $account->id }}]" value="{{ old('lines.'.$account->id, $lines[$account->id] ?? '') }}" placeholder="0.00" class="w-40 rounded-lg border border-slate-200 text-sm text-end">
                            </div>
                        @endforeach
                    @endif
                @endforeach
            </div>
        </div>

        <div>
            <label class="block text-xs font-medium text-slate-500 mb-1">{{ __('Notes (optional)') }}</label>
            <textarea name="notes" rows="2" class="w-full rounded-lg border border-slate-200 text-sm">{{ old('notes', $budget->notes) }}</textarea>
        </div>

        <button type="submit" class="rounded-lg bg-brand-600 px-6 py-2.5 text-sm font-semibold text-white hover:bg-brand-700">{{ __('Save budget') }}</button>
    </form>
</div>
@endsection
