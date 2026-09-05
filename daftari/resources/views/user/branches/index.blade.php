@extends('layouts.app')

@section('title', __('Branches'))

@section('content')
<div class="flex items-center justify-between mb-6">
    <p class="text-sm text-slate-500">{{ __('Manage the branch locations invoices and quotations can be issued from.') }}</p>
    <a href="{{ route('app.branches.create') }}" class="rounded-lg bg-brand-600 px-4 py-2 text-sm font-semibold text-white hover:bg-brand-700">{{ __('+ New branch') }}</a>
</div>

<div class="bg-white rounded-xl border border-slate-100">
    @if ($branches->isEmpty())
        <p class="px-6 py-8 text-sm text-slate-500">{{ __('No branches yet. Your company details are used by default.') }}</p>
    @else
        <table class="w-full text-sm">
            <thead>
                <tr class="text-left text-slate-500 border-b border-slate-100">
                    <th class="px-6 py-3 font-medium">{{ __('Code') }}</th>
                    <th class="px-6 py-3 font-medium">{{ __('Name') }}</th>
                    <th class="px-6 py-3 font-medium">{{ __('City') }}</th>
                    <th class="px-6 py-3 font-medium">{{ __('CR number') }}</th>
                    <th class="px-6 py-3 font-medium"></th>
                    <th class="px-6 py-3"></th>
                </tr>
            </thead>
            <tbody>
                @foreach ($branches as $branch)
                    <tr class="border-b border-slate-50 last:border-0 hover:bg-slate-50">
                        <td class="px-6 py-3 text-slate-400 font-mono text-xs">{{ $branch->code }}</td>
                        <td class="px-6 py-3 font-medium text-slate-800">{{ $branch->name }}</td>
                        <td class="px-6 py-3 text-slate-500">{{ $branch->city ?: '—' }}</td>
                        <td class="px-6 py-3 text-slate-500">{{ $branch->cr_number ?: '—' }}</td>
                        <td class="px-6 py-3">
                            @if (auth()->user()->company->default_branch_id === $branch->id)
                                <span class="inline-block rounded-full bg-brand-50 text-brand-700 text-xs font-medium px-2.5 py-1">{{ __('Default') }}</span>
                            @else
                                <form method="POST" action="{{ route('app.branches.make-default', $branch) }}">
                                    @csrf
                                    <button type="submit" class="text-xs font-semibold text-slate-500 hover:text-brand-700">{{ __('Set as default') }}</button>
                                </form>
                            @endif
                        </td>
                        <td class="px-6 py-3 text-right space-x-3 rtl:space-x-reverse">
                            <a href="{{ route('app.branches.edit', $branch) }}" class="text-brand-700 hover:underline">{{ __('Edit') }}</a>
                            <form method="POST" action="{{ route('app.branches.destroy', $branch) }}" class="inline" onsubmit="return confirm('{{ __('Delete this branch?') }}')">
                                @csrf @method('DELETE')
                                <button type="submit" class="text-red-600 hover:underline">{{ __('Delete') }}</button>
                            </form>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif
</div>
@endsection
