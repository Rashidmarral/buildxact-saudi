@extends('layouts.app')

@section('title', __('Open Shift'))

@section('content')
<div class="max-w-md mx-auto">
    <div class="bg-white rounded-xl border border-slate-100 p-6 space-y-5">
        <div>
            <h1 class="text-lg font-semibold text-slate-900">{{ __('Open a shift') }}</h1>
            <p class="mt-1 text-sm text-slate-500">{{ __('Count the starting cash in the drawer before you begin selling on :register.', ['register' => $register->name]) }}</p>
        </div>

        @if ($registers->count() > 1)
            <form method="GET" action="{{ route('app.pos.terminal') }}">
                <label class="block text-xs font-semibold uppercase text-slate-500">{{ __('Register') }}</label>
                <select name="register_id" onchange="this.form.submit()" class="mt-1 w-full rounded-lg border border-slate-200 text-sm focus:border-brand-500 focus:ring-brand-500">
                    @foreach ($registers as $r)
                        <option value="{{ $r->id }}" @selected($r->id === $register->id)>{{ $r->name }}</option>
                    @endforeach
                </select>
            </form>
        @endif

        <form method="POST" action="{{ route('app.pos.shift.open') }}" class="space-y-4">
            @csrf
            <input type="hidden" name="register_id" value="{{ $register->id }}">
            <div>
                <label class="block text-xs font-semibold uppercase text-slate-500">{{ __('Opening cash') }}</label>
                <input type="number" step="0.01" min="0" name="opening_cash" value="0.00" required autofocus class="mt-1 w-full rounded-lg border border-slate-200 text-lg focus:border-brand-500 focus:ring-brand-500">
            </div>
            <button type="submit" class="w-full rounded-lg bg-brand-600 px-4 py-3 text-sm font-semibold text-white hover:bg-brand-700">{{ __('Open shift & start selling') }}</button>
        </form>
    </div>
</div>
@endsection
