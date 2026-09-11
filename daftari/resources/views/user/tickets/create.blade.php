@extends('layouts.app')

@section('title', __('New Ticket'))

@section('content')
<form method="POST" action="{{ route('app.tickets.store') }}" enctype="multipart/form-data" class="max-w-2xl bg-white rounded-xl border border-slate-100 p-6 space-y-5">
    @csrf

    <div>
        <label class="block text-sm font-medium text-slate-700">{{ __('Subject') }}</label>
        <input type="text" name="subject" value="{{ old('subject') }}" required maxlength="255" class="mt-1 w-full rounded-lg border border-slate-200 focus:border-brand-500 focus:ring-brand-500">
    </div>

    <div>
        <label class="block text-sm font-medium text-slate-700">{{ __('Priority') }}</label>
        <select name="priority" class="mt-1 w-full rounded-lg border border-slate-200 focus:border-brand-500 focus:ring-brand-500">
            @foreach (\App\Models\Ticket::PRIORITIES as $value)
                <option value="{{ $value }}" @selected(old('priority', 'normal') === $value)>{{ ucfirst($value) }}</option>
            @endforeach
        </select>
    </div>

    <div>
        <label class="block text-sm font-medium text-slate-700">{{ __('Description') }}</label>
        <textarea name="description" rows="6" required maxlength="10000" class="mt-1 w-full rounded-lg border border-slate-200 focus:border-brand-500 focus:ring-brand-500">{{ old('description') }}</textarea>
    </div>

    <div>
        <label class="block text-sm font-medium text-slate-700">{{ __('Attachment (optional)') }}</label>
        <input type="file" name="attachment" class="mt-1 w-full text-sm text-slate-600 file:mr-3 file:rounded-lg file:border-0 file:bg-slate-100 file:px-3 file:py-2 file:text-sm file:font-semibold file:text-slate-600 hover:file:bg-slate-200">
        <p class="text-xs text-slate-400 mt-1">{{ __('PDF, Word, Excel, images, or ZIP — up to 10 MB.') }}</p>
    </div>

    <div class="flex gap-3">
        <button type="submit" class="rounded-lg bg-brand-600 px-6 py-2.5 font-semibold text-white hover:bg-brand-700">{{ __('Submit ticket') }}</button>
        <a href="{{ route('app.tickets.index') }}" class="rounded-lg border border-slate-200 px-6 py-2.5 font-semibold text-slate-600 hover:border-slate-300">{{ __('Cancel') }}</a>
    </div>
</form>
@endsection
