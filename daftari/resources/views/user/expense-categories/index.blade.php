@extends('layouts.app')

@section('title', __('Expense Categories'))

@section('content')
<div class="flex items-center justify-between mb-6">
    <div>
        <h2 class="text-lg font-semibold text-slate-900">{{ __('Expense Categories') }}</h2>
        <p class="text-sm text-slate-500 mt-1">{{ __('Manage the categories expenses are classified under, their codes and which account they post to.') }}</p>
    </div>
    <div class="flex items-center gap-2">
        <a href="{{ request()->fullUrlWithQuery(['export' => 'csv']) }}" class="rounded-lg border border-slate-200 px-4 py-2 text-sm font-semibold text-slate-600 hover:border-slate-300">{{ __('Export CSV') }}</a>
        <a href="{{ route('app.expense-categories.create') }}" class="rounded-lg bg-brand-600 px-4 py-2 text-sm font-semibold text-white hover:bg-brand-700">+ {{ __('Add category') }}</a>
    </div>
</div>

<form method="GET" class="bg-white rounded-xl border border-slate-100 p-4 mb-6 flex flex-wrap items-center gap-3">
    <input type="text" name="search" value="{{ request('search') }}" placeholder="{{ __('Search by name or code') }}" class="flex-1 min-w-[12rem] rounded-lg border border-slate-200 text-sm focus:border-brand-500 focus:ring-brand-500">
    <button type="submit" class="rounded-lg border border-slate-200 px-4 py-2 text-sm font-semibold text-slate-600 hover:border-slate-300">{{ __('Search') }}</button>
    @if (request('search'))
        <a href="{{ route('app.expense-categories.index') }}" class="text-sm text-slate-400 hover:text-slate-600">{{ __('Clear') }}</a>
    @endif
</form>

<div class="bg-white rounded-xl border border-slate-100">
    @if ($categories->isEmpty())
        <p class="px-6 py-8 text-sm text-slate-500">{{ __('No expense categories yet.') }}</p>
    @else
        <table class="w-full text-sm">
            <thead>
                <tr class="text-left text-slate-500 border-b border-slate-100">
                    <th class="px-6 py-3 font-medium">{{ __('Category name') }}</th>
                    <th class="px-6 py-3 font-medium">{{ __('Category code') }}</th>
                    <th class="px-6 py-3 font-medium">{{ __('Group') }}</th>
                    <th class="px-6 py-3 font-medium">{{ __('Status') }}</th>
                    <th class="px-6 py-3"></th>
                </tr>
            </thead>
            <tbody>
                @foreach ($categories as $category)
                    <tr class="border-b border-slate-50 last:border-0 hover:bg-slate-50">
                        <td class="px-6 py-3 font-medium text-slate-800">
                            {{ $category->name }}
                            @if ($category->name_ar)
                                <span class="block text-xs text-slate-400" dir="rtl">{{ $category->name_ar }}</span>
                            @endif
                        </td>
                        <td class="px-6 py-3 text-slate-500 font-mono text-xs">{{ $category->code ?: '—' }}</td>
                        <td class="px-6 py-3 text-slate-500">{{ $category->parent?->name ?: '—' }}</td>
                        <td class="px-6 py-3">
                            @if ($category->is_active)
                                <span class="rounded-full bg-emerald-50 px-2 py-0.5 text-[10px] font-semibold text-emerald-600">{{ __('Active') }}</span>
                            @else
                                <span class="rounded-full bg-slate-100 px-2 py-0.5 text-[10px] font-semibold text-slate-500">{{ __('Inactive') }}</span>
                            @endif
                        </td>
                        <td class="px-6 py-3 text-right space-x-3 rtl:space-x-reverse">
                            <a href="{{ route('app.expense-categories.edit', $category) }}" class="text-brand-700 hover:underline">{{ __('Edit') }}</a>
                            <form method="POST" action="{{ route('app.expense-categories.destroy', $category) }}" class="inline" onsubmit="return confirm('{{ __('Delete this category?') }}')">
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
@include('partials.pagination', ['paginator' => $categories])
@endsection
