@extends('layouts.app')

@section('title', __('Import Kafeel Payments'))

@section('content')
<div class="max-w-2xl">
    <a href="{{ route('app.kafeel-payments.index') }}" class="text-sm text-slate-400 hover:text-slate-600">{{ __('← Kafeel Payments') }}</a>
    <h1 class="text-xl font-bold text-slate-900 mt-1 mb-6">{{ __('Import Kafeel Payments') }}</h1>

    <div class="bg-white rounded-xl border border-slate-100 p-6">
        <p class="text-sm text-slate-600 mb-4">{{ __('Upload a CSV file to bulk-add historical payments — for example, from an existing Excel sheet you were already using to track these.') }}</p>

        @error('file')
            <p class="text-sm text-red-600 mb-4">{{ $message }}</p>
        @enderror

        <form method="POST" action="{{ route('app.kafeel-payments.import') }}" enctype="multipart/form-data" class="space-y-4">
            @csrf
            <div>
                <label class="block text-xs font-medium text-slate-500 mb-1">{{ __('CSV file') }}</label>
                <input type="file" name="file" accept=".csv,.txt" required class="w-full text-sm">
            </div>
            <button type="submit" class="rounded-lg bg-brand-600 px-6 py-2.5 text-sm font-semibold text-white hover:bg-brand-700">{{ __('Import') }}</button>
        </form>
    </div>

    <div class="bg-white rounded-xl border border-slate-100 p-6 mt-6">
        <h3 class="font-semibold text-slate-900 mb-3">{{ __('File format') }}</h3>
        <p class="text-sm text-slate-500 mb-3">{{ __('Column order doesn\'t matter — columns are matched by header name.') }}</p>
        <ul class="text-sm text-slate-600 space-y-1.5 mb-4">
            <li><span class="font-semibold text-slate-900">{{ __('Date') }}</span> — {{ __('required. Any common date format.') }}</li>
            <li><span class="font-semibold text-slate-900">{{ __('Description') }}</span> — {{ __('required.') }}</li>
            <li><span class="font-semibold text-slate-900">{{ __('Amount') }}</span> — {{ __('required. Numbers only.') }}</li>
            <li><span class="font-semibold text-slate-900">{{ __('Paid To') }}</span> — {{ __('optional.') }}</li>
            <li>
                <span class="font-semibold text-slate-900">{{ __('Category') }}</span> —
                {{ __('optional. One of: :categories. Anything else (or blank) is imported as "Other" so you can recategorize it afterward.', ['categories' => implode(', ', array_keys($categories))]) }}
            </li>
            <li><span class="font-semibold text-slate-900">{{ __('Notes') }}</span> — {{ __('optional.') }}</li>
        </ul>
        <p class="text-xs text-slate-400">{{ __('Rows with an invalid or missing date/amount are skipped and listed after import — nothing partially-valid is silently dropped without telling you.') }}</p>
    </div>
</div>
@endsection
