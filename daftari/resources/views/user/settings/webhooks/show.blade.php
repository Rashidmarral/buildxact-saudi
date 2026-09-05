@extends('layouts.app')

@section('title', __('Webhook details'))

@section('content')
<div class="max-w-3xl bg-white rounded-xl border border-slate-100 p-6">
    <div class="flex items-center justify-between gap-4">
        <div class="min-w-0">
            <h1 class="text-lg font-semibold text-slate-900 truncate">{{ $webhook->url }}</h1>
            <span class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-semibold mt-1 {{ $webhook->is_active ? 'bg-emerald-50 text-emerald-700' : 'bg-slate-100 text-slate-500' }}">
                {{ $webhook->is_active ? __('Active') : __('Disabled') }}
            </span>
        </div>
        <a href="{{ route('app.settings.webhooks.index') }}" class="shrink-0 text-sm font-semibold text-brand-700 hover:underline">{{ __('Back to webhooks') }}</a>
    </div>

    <div class="mt-6 pt-6 border-t border-slate-100">
        <h2 class="font-semibold text-slate-900 mb-3">{{ __('Configuration') }}</h2>
        <form method="POST" action="{{ route('app.settings.webhooks.update', $webhook) }}" class="max-w-sm space-y-3">
            @csrf
            @method('PUT')
            <div>
                <label class="block text-sm font-medium text-slate-700">{{ __('Endpoint URL') }}</label>
                <input type="url" name="url" required maxlength="2048" value="{{ old('url', $webhook->url) }}" class="mt-1 w-full rounded-lg border border-slate-200 focus:border-brand-500 focus:ring-brand-500">
                @error('url')
                    <p class="text-xs text-red-600 mt-1">{{ $message }}</p>
                @enderror
            </div>
            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1">{{ __('Events') }}</label>
                <div class="space-y-2">
                    @foreach ($availableEvents as $event)
                        <label class="flex items-center gap-2 text-sm text-slate-700">
                            <input type="checkbox" name="events[]" value="{{ $event }}" class="rounded border-slate-300 text-brand-600 focus:ring-brand-500" {{ in_array($event, old('events', $webhook->events ?? [])) ? 'checked' : '' }}>
                            {{ $event }}
                        </label>
                    @endforeach
                </div>
            </div>
            <button type="submit" class="rounded-lg bg-brand-600 px-6 py-2.5 font-semibold text-white hover:bg-brand-700">{{ __('Save changes') }}</button>
        </form>
    </div>

    <div class="mt-6 pt-6 border-t border-slate-100">
        <h2 class="font-semibold text-slate-900 mb-1">{{ __('Signing secret') }}</h2>
        <p class="text-sm text-slate-500 mb-3">{{ __('Verify each delivery by computing an HMAC-SHA256 of the raw request body with this secret and comparing it to the X-Daftari-Signature header.') }}</p>
        @if (session('reveal_webhook_secret'))
            <code class="block break-all rounded-lg bg-slate-50 border border-slate-200 px-3 py-2 text-xs font-mono text-slate-700">{{ $webhook->secret }}</code>
            <p class="text-xs text-amber-600 mt-2">{{ __('This is shown in full only once — copy it now. If you lose it, regenerate a new one below.') }}</p>
        @else
            <code class="block break-all rounded-lg bg-slate-50 border border-slate-200 px-3 py-2 text-xs font-mono text-slate-400">{{ str($webhook->secret)->substr(0, 6) }}{{ str_repeat('•', 24) }}{{ str($webhook->secret)->substr(-4) }}</code>
            <p class="text-xs text-slate-400 mt-2">{{ __('Hidden after the first view. Regenerate to see the full value again.') }}</p>
        @endif
        <form method="POST" action="{{ route('app.settings.webhooks.regenerate-secret', $webhook) }}" class="mt-3" onsubmit="return confirm('{{ __('Regenerate the signing secret? Your endpoint must be updated to verify with the new value.') }}')">
            @csrf
            <button type="submit" class="rounded-lg border border-slate-200 px-4 py-2 text-sm font-semibold text-slate-600 hover:border-slate-300">{{ __('Regenerate secret') }}</button>
        </form>
    </div>

    <div class="mt-6 pt-6 border-t border-slate-100 flex flex-wrap items-center gap-3">
        <form method="POST" action="{{ route('app.settings.webhooks.send-test', $webhook) }}">
            @csrf
            <button type="submit" class="rounded-lg border border-slate-200 px-4 py-2 text-sm font-semibold text-slate-600 hover:border-slate-300">{{ __('Send test event') }}</button>
        </form>
        <form method="POST" action="{{ route('app.settings.webhooks.toggle', $webhook) }}">
            @csrf
            <button type="submit" class="rounded-lg border border-slate-200 px-4 py-2 text-sm font-semibold text-slate-600 hover:border-slate-300">
                {{ $webhook->is_active ? __('Disable') : __('Enable') }}
            </button>
        </form>
        <form method="POST" action="{{ route('app.settings.webhooks.destroy', $webhook) }}" onsubmit="return confirm('{{ __('Delete this webhook?') }}')">
            @csrf
            @method('DELETE')
            <button type="submit" class="rounded-lg border border-red-200 px-4 py-2 text-sm font-semibold text-red-600 hover:bg-red-50">{{ __('Delete') }}</button>
        </form>
    </div>

    <div class="mt-6 pt-6 border-t border-slate-100">
        <h2 class="font-semibold text-slate-900 mb-3">{{ __('Delivery log') }}</h2>
        @if ($deliveries->isEmpty())
            <p class="text-sm text-slate-400">{{ __('No deliveries yet.') }}</p>
        @else
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="text-start text-xs font-semibold text-slate-500 uppercase">
                            <th class="py-2 pe-4">{{ __('Event') }}</th>
                            <th class="py-2 pe-4">{{ __('Status') }}</th>
                            <th class="py-2 pe-4">{{ __('Sent') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @foreach ($deliveries as $delivery)
                            <tr>
                                <td class="py-2 pe-4 font-mono text-xs text-slate-700">{{ $delivery->event }}</td>
                                <td class="py-2 pe-4">
                                    @if ($delivery->success)
                                        <span class="inline-flex items-center rounded-full bg-emerald-50 px-2 py-0.5 text-xs font-semibold text-emerald-700">{{ $delivery->response_status }}</span>
                                    @else
                                        <span class="inline-flex items-center rounded-full bg-red-50 px-2 py-0.5 text-xs font-semibold text-red-700" title="{{ $delivery->error }}">
                                            {{ $delivery->response_status ?? __('No response') }}
                                        </span>
                                    @endif
                                </td>
                                <td class="py-2 pe-4 text-slate-500">{{ $delivery->created_at->diffForHumans() }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="mt-4">{{ $deliveries->links() }}</div>
        @endif
    </div>
</div>
@endsection
