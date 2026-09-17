@extends('layouts.app')

@section('title', __('Transfers'))

@section('content')
@include('user.bank-accounts.partials.tabs')

<div class="flex items-center justify-between mb-6">
    <p class="text-sm text-slate-500">{{ __('Money moved between your own accounts.') }}</p>
    <a href="{{ route('app.bank-transfers.create') }}" class="rounded-lg bg-brand-600 px-4 py-2 text-sm font-semibold text-white hover:bg-brand-700">{{ __('+ New transfer') }}</a>
</div>

<div class="bg-white rounded-xl border border-slate-100">
    @if ($transfers->isEmpty())
        <p class="px-6 py-8 text-sm text-slate-500">{{ __('No transfers yet.') }}</p>
    @else
        <table class="w-full text-sm">
            <thead>
                <tr class="text-left text-slate-500 border-b border-slate-100">
                    <th class="px-6 py-3 font-medium">{{ __('Date') }}</th>
                    <th class="px-6 py-3 font-medium">{{ __('From') }}</th>
                    <th class="px-6 py-3 font-medium">{{ __('To') }}</th>
                    <th class="px-6 py-3 font-medium">{{ __('Amount') }}</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($transfers as $transfer)
                    <tr class="border-b border-slate-50 last:border-0">
                        <td class="px-6 py-3">{{ $transfer->date->format('Y-m-d') }}</td>
                        <td class="px-6 py-3">{{ $transfer->fromAccount->name }}</td>
                        <td class="px-6 py-3">{{ $transfer->toAccount->name }}</td>
                        <td class="px-6 py-3">{{ \App\Support\Money::format($transfer->amount) }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif
</div>
<div class="mt-4">{{ $transfers->links() }}</div>
@endsection
