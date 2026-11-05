@extends('layouts.app')

@section('title', __('Point of Sale'))

@section('content')
<div class="max-w-lg mx-auto bg-white rounded-xl border border-slate-100 p-8 text-center">
    <h1 class="text-lg font-semibold text-slate-900">{{ __('No active registers') }}</h1>
    <p class="mt-2 text-sm text-slate-500">{{ __('Set up at least one active register to start selling.') }}</p>
    <a href="{{ route('app.pos-registers.index') }}" class="mt-4 inline-block rounded-lg bg-brand-600 px-4 py-2 text-sm font-semibold text-white hover:bg-brand-700">{{ __('Manage registers') }}</a>
</div>
@endsection
