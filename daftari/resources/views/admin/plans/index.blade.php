@extends('layouts.admin')

@section('title', __('Plans'))

@section('content')
<div class="flex items-center justify-between mb-6">
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
                <th class="px-6 py-3 font-medium">{{ __('Status') }}</th>
                <th class="px-6 py-3"></th>
            </tr>
        </thead>
        <tbody>
            @foreach ($plans as $plan)
                <tr class="border-b border-slate-50 last:border-0">
                    <td class="px-6 py-3 font-medium text-slate-800">{{ $plan->name }}</td>
                    <td class="px-6 py-3">SAR {{ number_format($plan->price_monthly, 0) }}</td>
                    <td class="px-6 py-3">SAR {{ number_format($plan->price_yearly, 0) }}</td>
                    <td class="px-6 py-3">{{ $plan->subscriptions()->count() }}</td>
                    <td class="px-6 py-3">{{ $plan->is_active ? __('Active') : __('Hidden') }}</td>
                    <td class="px-6 py-3 text-right space-x-3 rtl:space-x-reverse">
                        <a href="{{ route('admin.plans.edit', $plan) }}" class="text-brand-700 hover:underline">{{ __('Edit') }}</a>
                        <form method="POST" action="{{ route('admin.plans.destroy', $plan) }}" class="inline" onsubmit="return confirm('{{ __('Delete this plan?') }}')">
                            @csrf @method('DELETE')
                            <button type="submit" class="text-red-600 hover:underline">{{ __('Delete') }}</button>
                        </form>
                    </td>
                </tr>
            @endforeach
        </tbody>
    </table>
</div>
@endsection
