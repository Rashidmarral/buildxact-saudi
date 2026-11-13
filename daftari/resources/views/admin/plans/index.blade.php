@extends('layouts.admin')

@section('title', __('Plans'))

@section('content')
<div class="flex flex-wrap items-center justify-between gap-3 mb-6">
    <p class="text-sm text-slate-500">{{ __('Plans shown on the public pricing page and at signup.') }}</p>
    <a href="{{ route('admin.plans.create') }}" class="rounded-lg bg-brand-600 px-4 py-2 text-sm font-semibold text-white hover:bg-brand-700">{{ __('+ New plan') }}</a>
</div>

<div class="bg-white rounded-xl border border-slate-100">
    <table class="w-full text-sm">
        <thead>
            <tr class="text-left text-slate-500 border-b border-slate-100">
                <th class="px-6 py-3 font-medium">{{ __('Name') }}</th>
                <th class="px-6 py-3 font-medium">{{ __('Monthly') }}</th>
                <th class="px-6 py-3 font-medium">{{ __('Yearly') }}</th>
                <th class="px-6 py-3 font-medium">{{ __('Subscribers') }}</th>
                <th class="px-6 py-3 font-medium">{{ __('Visibility') }}</th>
                <th class="px-6 py-3 font-medium">{{ __('Status') }}</th>
                <th class="px-6 py-3"></th>
            </tr>
        </thead>
        <tbody>
            @foreach ($plans as $plan)
                <tr class="border-b border-slate-50 last:border-0">
                    <td class="px-6 py-3 font-medium text-slate-800">{{ $plan->name }}</td>
                    <td class="px-6 py-3">{{ \App\Support\Money::format($plan->price_monthly, $plan->currency) }}</td>
                    <td class="px-6 py-3">{{ \App\Support\Money::format($plan->price_yearly, $plan->currency) }}</td>
                    <td class="px-6 py-3">{{ $plan->subscriptions()->count() }}</td>
                    <td class="px-6 py-3">
                        {{ $plan->is_public ? __('Public') : __('Private') }}
                        @if ($plan->is_featured)
                            <span class="ms-1 inline-flex items-center rounded-full bg-amber-50 px-2 py-0.5 text-xs font-semibold text-amber-700">{{ __('Featured') }}</span>
                        @endif
                    </td>
                    <td class="px-6 py-3">{{ $plan->is_active ? __('Active') : __('Hidden') }}</td>
                    <td class="px-6 py-3 text-right">
                        <x-row-actions>
                            <x-icon-button menu href="{{ route('admin.plans.edit', $plan) }}" icon="pencil" title="{{ __('Edit') }}" />
                            <form method="POST" action="{{ route('admin.plans.destroy', $plan) }}" onsubmit="return confirm('{{ __('Delete this plan?') }}')">
                                @csrf @method('DELETE')
                                <x-icon-button menu type="submit" icon="trash" color="red" title="{{ __('Delete') }}" />
                            </form>
                        </x-row-actions>
                    </td>
                </tr>
            @endforeach
        </tbody>
    </table>
</div>
@endsection
