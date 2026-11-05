@extends('layouts.app')

@section('title', __('Branches'))

@section('content')
<div class="flex flex-wrap items-center justify-between gap-3 mb-6">
    <p class="text-sm text-slate-500">{{ __('Manage the branch locations invoices and quotations can be issued from.') }}</p>
    <a href="{{ route('app.branches.create') }}" class="rounded-lg bg-brand-600 px-4 py-2 text-sm font-semibold text-white hover:bg-brand-700">{{ __('+ New branch') }}</a>
</div>

<x-list-toolbar>
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
                                    <x-icon-button type="submit" icon="check-circle" color="brand" title="{{ __('Set as default') }}" />
                                </form>
                            @endif
                        </td>
                        <td class="px-6 py-3 text-right">
                            <div class="flex items-center justify-end gap-1.5">
                                <x-icon-button href="{{ route('app.branches.edit', $branch) }}" icon="pencil" title="{{ __('Edit') }}" />
                                <form method="POST" action="{{ route('app.branches.destroy', $branch) }}" onsubmit="return confirm('{{ __('Delete this branch?') }}')">
                                    @csrf @method('DELETE')
                                    <x-icon-button type="submit" icon="trash" color="red" title="{{ __('Delete') }}" />
                                </form>
                            </div>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif
</div>
</x-list-toolbar>
@endsection
