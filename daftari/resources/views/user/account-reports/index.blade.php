@extends('layouts.app')

@section('title', __('Account Reports'))

@section('content')
@include('user.bank-accounts.partials.tabs')

<div class="rounded-2xl bg-gradient-to-br from-slate-900 to-slate-700 px-6 py-7 mb-6 text-white">
    <p class="text-sm text-slate-300">{{ __('Every bank, cash, and custody account — one statement each, ready to show the owner.') }}</p>
    <div class="mt-3 flex flex-wrap items-end justify-between gap-4">
        <div>
            <p class="text-xs uppercase tracking-wide text-slate-400">{{ __('Combined balance across all accounts') }}</p>
            <p class="text-3xl font-bold mt-1 tabular-nums">{{ \App\Support\Money::format($totalBalance) }}</p>
        </div>
        <div class="flex gap-6 text-sm">
            <div>
                <p class="text-slate-400">{{ __('Bank') }}</p>
                <p class="font-semibold tabular-nums">{{ $groups['bank']->count() }}</p>
            </div>
            <div>
                <p class="text-slate-400">{{ __('Cash') }}</p>
                <p class="font-semibold tabular-nums">{{ $groups['cash']->count() }}</p>
            </div>
            <div>
                <p class="text-slate-400">{{ __('Custody') }}</p>
                <p class="font-semibold tabular-nums">{{ $groups['custody']->count() }}</p>
            </div>
        </div>
    </div>
</div>

@php
    $sections = [
        'bank' => ['label' => __('Bank Accounts'), 'icon' => 'bank', 'accent' => 'text-sky-600 bg-sky-50'],
        'cash' => ['label' => __('Cash Accounts'), 'icon' => 'cash', 'accent' => 'text-emerald-600 bg-emerald-50'],
        'custody' => ['label' => __('Custody / Advances'), 'icon' => 'custody', 'accent' => 'text-amber-600 bg-amber-50'],
    ];
@endphp

@foreach ($sections as $key => $section)
    <div class="mb-8">
        <h3 class="text-sm font-semibold text-slate-900 mb-3">{{ $section['label'] }}</h3>

        @if ($groups[$key]->isEmpty())
            <div class="bg-white rounded-xl border border-dashed border-slate-200 px-6 py-8 text-center">
                <p class="text-sm text-slate-400">{{ __('No accounts in this category yet.') }}</p>
            </div>
        @else
            <div class="grid sm:grid-cols-2 lg:grid-cols-3 gap-4">
                @foreach ($groups[$key] as $account)
                    <div class="group relative bg-white rounded-xl border border-slate-100 p-5 hover:border-brand-300 hover:shadow-sm transition">
                        <div class="flex items-start justify-between">
                            <div>
                                <p class="text-sm font-semibold text-slate-900">
                                    <a href="{{ route('app.account-reports.show', $account) }}" class="static after:absolute after:inset-0">{{ $account->name }}</a>
                                </p>
                                @if ($key === 'custody' && $account->personal_owner_name)
                                    <p class="text-xs text-slate-400 mt-0.5">{{ $account->personal_owner_name }}</p>
                                @elseif ($account->bank_name)
                                    <p class="text-xs text-slate-400 mt-0.5">{{ $account->bank_name }}</p>
                                @endif
                            </div>
                            <span class="inline-block rounded-full {{ $section['accent'] }} text-xs font-medium px-2.5 py-1 shrink-0">{{ $account->currency }}</span>
                        </div>
                        <p class="text-xl font-bold mt-4 tabular-nums {{ $account->currentBalance() < 0 ? 'text-red-600' : 'text-slate-900' }}">{{ \App\Support\Money::format($account->currentBalance()) }}</p>
                        <div class="mt-4 flex items-center justify-between text-xs">
                            <span class="font-semibold text-brand-700 group-hover:underline">{{ __('View statement →') }}</span>
                            <a href="{{ route('app.account-reports.pdf', $account) }}" class="relative z-10 text-slate-400 hover:text-slate-600">{{ __('Download PDF') }}</a>
                        </div>
                    </div>
                @endforeach
            </div>
        @endif
    </div>
@endforeach
@endsection
