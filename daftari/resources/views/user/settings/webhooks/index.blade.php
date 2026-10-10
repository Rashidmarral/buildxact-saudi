@extends('layouts.app')

@section('title', __('Webhooks'))

@section('content')
<div class="max-w-2xl bg-white rounded-xl border border-slate-100 p-6">
    <h1 class="text-lg font-semibold text-slate-900">{{ __('Webhooks') }}</h1>
    <p class="text-sm text-slate-500 mt-1">{{ __('Register a URL to receive a signed POST request whenever one of the events below happens in your account.') }}</p>

    @if ($webhooks->isEmpty())
        <p class="mt-6 text-sm text-slate-400">{{ __('No webhooks yet.') }}</p>
    @else
        <div class="mt-6 divide-y divide-slate-100">
            @foreach ($webhooks as $webhook)
                <a href="{{ route('app.settings.webhooks.show', $webhook) }}" class="py-3 flex items-center justify-between gap-4 hover:bg-slate-50 -mx-2 px-2 rounded-lg">
                    <div class="min-w-0">
                        <p class="text-sm font-medium text-slate-800 truncate">{{ $webhook->url }}</p>
                        <p class="text-xs text-slate-400 mt-0.5">{{ implode(', ', $webhook->events ?? []) }}</p>
                    </div>
                    <span class="shrink-0 inline-flex items-center rounded-full px-2 py-0.5 text-xs font-semibold {{ $webhook->is_active ? 'bg-emerald-50 text-emerald-700' : 'bg-slate-100 text-slate-500' }}">
                        {{ $webhook->is_active ? __('Active') : __('Disabled') }}
                    </span>
                </a>
            @endforeach
        </div>
    @endif

    <div class="mt-6 pt-6 border-t border-slate-100">
        <h2 class="font-semibold text-slate-900 mb-3">{{ __('Register a new webhook') }}</h2>
        <form method="POST" action="{{ route('app.settings.webhooks.store') }}" class="max-w-sm space-y-3">
            @csrf
            <div>
                <label class="block text-sm font-medium text-slate-700">{{ __('Endpoint URL') }}</label>
                <input type="url" name="url" required maxlength="2048" placeholder="https://example.com/webhooks/daftari" class="mt-1 w-full rounded-lg border border-slate-200 focus:border-brand-500 focus:ring-brand-500" value="{{ old('url') }}">
                @error('url')
                    <p class="text-xs text-red-600 mt-1">{{ $message }}</p>
                @enderror
            </div>
            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1">{{ __('Events') }}</label>
                <div class="space-y-2">
                    @foreach ($availableEvents as $event)
                        <label class="flex items-center gap-2 text-sm text-slate-700">
                            <input type="checkbox" name="events[]" value="{{ $event }}" class="rounded border-slate-300 text-brand-600 focus:ring-brand-500" {{ in_array($event, old('events', [])) ? 'checked' : '' }}>
                            {{ $event }}
                        </label>
                    @endforeach
                </div>
                @error('events')
                    <p class="text-xs text-red-600 mt-1">{{ $message }}</p>
                @enderror
            </div>
            <button type="submit" class="rounded-lg bg-brand-600 px-6 py-2.5 font-semibold text-white hover:bg-brand-700">{{ __('Register webhook') }}</button>
        </form>
    </div>
</div>
@endsection
