@extends('layouts.app')

@section('title', $payment->exists ? __('Edit Kafeel Payment') : __('Record a Kafeel Payment'))

@section('content')
<div class="max-w-2xl">
    <a href="{{ route('app.kafeel-payments.index') }}" class="text-sm text-slate-400 hover:text-slate-600">{{ __('← Kafeel Payments') }}</a>
    <h1 class="text-xl font-bold text-slate-900 mt-1 mb-6">{{ $payment->exists ? __('Edit Kafeel Payment') : __('Record a Kafeel Payment') }}</h1>

    <form method="POST" action="{{ $payment->exists ? route('app.kafeel-payments.update', $payment) : route('app.kafeel-payments.store') }}" class="bg-white rounded-xl border border-slate-100 p-6 space-y-4">
        @csrf
        @if ($payment->exists) @method('PUT') @endif

        <div class="grid sm:grid-cols-2 gap-4">
            <div>
                <label class="block text-xs font-medium text-slate-500 mb-1">{{ __('Date') }}</label>
                <input type="date" name="payment_date" value="{{ old('payment_date', $payment->payment_date?->toDateString()) }}" required class="w-full rounded-lg border border-slate-200 text-sm focus:border-brand-500 focus:ring-brand-500">
                @error('payment_date') <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror
            </div>
            <div>
                <label class="block text-xs font-medium text-slate-500 mb-1">{{ __('Amount') }}</label>
                <input type="number" step="0.01" min="0.01" name="amount" value="{{ old('amount', $payment->amount) }}" required class="w-full rounded-lg border border-slate-200 text-sm focus:border-brand-500 focus:ring-brand-500">
                @error('amount') <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror
            </div>
        </div>

        <div class="grid sm:grid-cols-2 gap-4">
            <div>
                <label class="block text-xs font-medium text-slate-500 mb-1">{{ __('Paid to (optional)') }}</label>
                <input type="text" name="paid_to" value="{{ old('paid_to', $payment->paid_to) }}" placeholder="{{ __('e.g. Abdul Majeed, STC, Kafeel Account') }}" class="w-full rounded-lg border border-slate-200 text-sm focus:border-brand-500 focus:ring-brand-500">
            </div>
            <div>
                <label class="block text-xs font-medium text-slate-500 mb-1">{{ __('Category') }}</label>
                <select name="category" required class="w-full rounded-lg border border-slate-200 text-sm focus:border-brand-500 focus:ring-brand-500">
                    @foreach ($categories as $key => $label)
                        <option value="{{ $key }}" @selected(old('category', $payment->category ?: 'other') === $key)>{{ __($label) }}</option>
                    @endforeach
                </select>
            </div>
        </div>

        <div>
            <label class="block text-xs font-medium text-slate-500 mb-1">{{ __('Description') }}</label>
            <input type="text" name="description" value="{{ old('description', $payment->description) }}" required class="w-full rounded-lg border border-slate-200 text-sm focus:border-brand-500 focus:ring-brand-500">
            @error('description') <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror
        </div>

        <div>
            <label class="block text-xs font-medium text-slate-500 mb-1">{{ __('Notes (optional)') }}</label>
            <textarea name="notes" rows="2" class="w-full rounded-lg border border-slate-200 text-sm focus:border-brand-500 focus:ring-brand-500">{{ old('notes', $payment->notes) }}</textarea>
        </div>

        <div class="flex items-center justify-between pt-2">
            <button type="submit" class="rounded-lg bg-brand-600 px-6 py-2.5 text-sm font-semibold text-white hover:bg-brand-700">{{ $payment->exists ? __('Save changes') : __('Record payment') }}</button>
            @if ($payment->exists)
                <form method="POST" action="{{ route('app.kafeel-payments.destroy', $payment) }}" onsubmit="return confirm('{{ __('Delete this payment record?') }}')">
                    @csrf @method('DELETE')
                    <button type="submit" class="text-sm text-red-600 hover:underline">{{ __('Delete') }}</button>
                </form>
            @endif
        </div>
    </form>

    @if ($payment->exists)
        <div class="bg-white rounded-xl border border-slate-100 p-6 mt-6">
            <div class="flex items-center justify-between mb-4">
                <h3 class="font-semibold text-slate-900">{{ __('Attachments') }}</h3>
                <button type="button" onclick="document.getElementById('attach-file-input').click()" class="text-sm font-semibold text-brand-700 hover:underline">{{ __('+ Attach file') }}</button>
                <form method="POST" action="{{ route('app.kafeel-payments.attachments.store', $payment) }}" enctype="multipart/form-data" id="attach-file-form" class="hidden">
                    @csrf
                    <input type="file" name="file" id="attach-file-input" onchange="document.getElementById('attach-file-form').submit()">
                </form>
            </div>
            <p class="text-xs text-slate-400 mb-3">{{ __('Attach a receipt or proof of payment, if you have one.') }}</p>

            @if ($payment->attachments->isEmpty())
                <p class="text-sm text-slate-400">{{ __('No attachments') }}</p>
            @else
                <ul class="divide-y divide-slate-50">
                    @foreach ($payment->attachments as $attachment)
                        <li class="flex items-center justify-between py-2 text-sm">
                            <a href="{{ \Illuminate\Support\Facades\Storage::url($attachment->path) }}" target="_blank" class="text-brand-700 hover:underline">{{ $attachment->original_name }}</a>
                            <div class="flex items-center gap-3 text-slate-400">
                                <span>{{ $attachment->humanSize() }}</span>
                                <form method="POST" action="{{ route('app.kafeel-payments.attachments.destroy', [$payment, $attachment]) }}" onsubmit="return confirm('{{ __('Remove this attachment?') }}')">
                                    @csrf @method('DELETE')
                                    <button type="submit" class="text-red-600 hover:underline">{{ __('Remove') }}</button>
                                </form>
                            </div>
                        </li>
                    @endforeach
                </ul>
            @endif
        </div>
    @endif
</div>
@endsection
