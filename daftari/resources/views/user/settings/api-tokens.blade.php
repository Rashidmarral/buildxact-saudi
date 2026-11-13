@extends('layouts.app')

@section('title', __('API tokens'))

@section('content')
<div class="max-w-2xl bg-white rounded-xl border border-slate-100 p-6">
    <h1 class="text-lg font-semibold text-slate-900">{{ __('API tokens') }}</h1>
    <p class="text-sm text-slate-500 mt-1">{{ __('Personal access tokens let external scripts and integrations act on your account through the Daftari API. Treat a token like a password — anyone who has it can access your data.') }}</p>

    @if ($tokens->isEmpty())
        <p class="mt-6 text-sm text-slate-400">{{ __('No API tokens yet.') }}</p>
    @else
        <div class="mt-6 divide-y divide-slate-100">
            @foreach ($tokens as $token)
                <div class="py-3 flex items-center justify-between gap-4">
                    <div class="min-w-0">
                        <p class="text-sm font-medium text-slate-800">
                            {{ $token->name }}
                            <span class="ml-1 inline-flex items-center rounded-full bg-slate-100 px-2 py-0.5 text-xs font-semibold text-slate-600">
                                {{ in_array('write', $token->abilities ?? []) ? __('Read & write') : __('Read only') }}
                            </span>
                        </p>
                        <p class="text-xs text-slate-400 mt-0.5">
                            {{ __('Created :time', ['time' => $token->created_at->diffForHumans()]) }}
                            &middot;
                            {{ $token->last_used_at ? __('Last used :time', ['time' => $token->last_used_at->diffForHumans()]) : __('Never used') }}
                            &middot;
                            {{ $token->expires_at ? __('Expires :time', ['time' => $token->expires_at->diffForHumans()]) : __('Never expires') }}
                        </p>
                    </div>
                    <form method="POST" action="{{ route('app.settings.api-tokens.destroy', $token->id) }}" onsubmit="return confirm('{{ __('Revoke this token? Any integration using it will stop working immediately.') }}')">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="shrink-0 rounded-lg border border-slate-200 px-3 py-1.5 text-xs font-semibold text-slate-600 hover:border-red-300 hover:text-red-600">{{ __('Revoke') }}</button>
                    </form>
                </div>
            @endforeach
        </div>
    @endif

    <div class="mt-6 pt-6 border-t border-slate-100">
        <h2 class="font-semibold text-slate-900 mb-3">{{ __('Create a new token') }}</h2>
        <form method="POST" action="{{ route('app.settings.api-tokens.store') }}" class="max-w-sm space-y-3">
            @csrf
            <div>
                <label class="block text-sm font-medium text-slate-700">{{ __('Token name') }}</label>
                <input type="text" name="name" required maxlength="100" placeholder="{{ __('e.g. Accounting sync script') }}" class="mt-1 w-full rounded-lg border border-slate-200 focus:border-brand-500 focus:ring-brand-500">
                @error('name')
                    <p class="text-xs text-red-600 mt-1">{{ $message }}</p>
                @enderror
            </div>
            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1">{{ __('Access') }}</label>
                <div class="space-y-2">
                    <label class="flex items-center gap-2 text-sm text-slate-700">
                        <input type="radio" name="access" value="read" checked class="rounded border-slate-300 text-brand-600 focus:ring-brand-500">
                        {{ __('Read only') }}
                    </label>
                    <label class="flex items-center gap-2 text-sm text-slate-700">
                        <input type="radio" name="access" value="write" class="rounded border-slate-300 text-brand-600 focus:ring-brand-500">
                        {{ __('Read & write') }}
                    </label>
                </div>
            </div>
            <div>
                <label class="block text-sm font-medium text-slate-700">{{ __('Expires') }}</label>
                <select name="expires_in" class="mt-1 w-full rounded-lg border border-slate-200 focus:border-brand-500 focus:ring-brand-500">
                    <option value="90" selected>{{ __('In 90 days') }}</option>
                    <option value="30">{{ __('In 30 days') }}</option>
                    <option value="365">{{ __('In 1 year') }}</option>
                    <option value="never">{{ __('Never (not recommended)') }}</option>
                </select>
            </div>
            <button type="submit" class="rounded-lg bg-brand-600 px-6 py-2.5 font-semibold text-white hover:bg-brand-700">{{ __('Generate token') }}</button>
        </form>
    </div>
</div>
@endsection
