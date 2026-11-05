@extends('layouts.auth')

@section('title', __('Confirm password'))

@section('content')
<h1 class="text-xl font-bold text-slate-900">{{ __('Confirm your password') }}</h1>
<p class="mt-2 text-sm text-slate-500">{{ __('This is a sensitive action. Please confirm your password to continue.') }}</p>

<form method="POST" action="{{ route('admin.password.confirm.store') }}" class="mt-6 space-y-5">
    @csrf
    <div>
        <label class="block text-sm font-medium text-slate-700">{{ __('Password') }}</label>
        <input type="password" name="password" required autofocus autocomplete="current-password" class="mt-1 w-full rounded-lg border border-slate-200 focus:border-brand-500 focus:ring-brand-500">
        @error('password')
            <p class="text-xs text-red-600 mt-1">{{ $message }}</p>
        @enderror
    </div>
    <button type="submit" class="w-full rounded-lg bg-brand-600 px-6 py-3 font-semibold text-white hover:bg-brand-700">{{ __('Confirm') }}</button>
</form>
@endsection
