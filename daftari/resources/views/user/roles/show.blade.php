@extends('layouts.app')

@section('title', $role->name)

@section('content')
<div class="flex items-center justify-between mb-6">
    <div class="flex items-center gap-3">
        <h2 class="text-lg font-semibold text-slate-900">{{ $role->name }}</h2>
        @if ($role->is_system)
            <span class="inline-block rounded-full bg-slate-100 text-slate-500 text-xs font-medium px-2.5 py-1">{{ __('System') }}</span>
        @endif
    </div>
    <div class="flex gap-3">
        @unless ($role->is_system)
            <a href="{{ route('app.roles.edit', $role) }}" class="rounded-lg border border-slate-200 px-4 py-2 text-sm font-semibold text-slate-600 hover:border-slate-300">{{ __('Edit') }}</a>
        @endunless
        <a href="{{ route('app.roles.index') }}" class="rounded-lg border border-slate-200 px-4 py-2 text-sm font-semibold text-slate-600 hover:border-slate-300">{{ __('Back to roles') }}</a>
    </div>
</div>

<div class="bg-white rounded-xl border border-slate-100 p-6">
    <h3 class="font-semibold text-slate-900 mb-4">{{ __('Permissions') }}</h3>
    <div class="grid sm:grid-cols-2 gap-2">
        @foreach ($catalog as $key => $label)
            <div class="flex items-center gap-2 rounded-lg border border-slate-200 px-3 py-2 text-sm {{ in_array($key, $role->permissions ?? []) ? 'text-slate-800' : 'text-slate-300' }}">
                <span>{{ in_array($key, $role->permissions ?? []) ? '✓' : '—' }}</span>
                {{ $label }}
            </div>
        @endforeach
    </div>
</div>
@endsection
