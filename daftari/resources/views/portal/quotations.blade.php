@extends('layouts.portal')

@section('title', __('Quotes'))

@section('content')
<h1 class="text-xl font-bold text-slate-900 mb-6">{{ __('Quotes') }}</h1>

<div class="bg-white rounded-xl border border-slate-100">
    @if ($quotations->isEmpty())
        <p class="px-6 py-8 text-sm text-slate-500">{{ __('No quotations yet.') }}</p>
    @else
        <table class="w-full text-sm">
            <thead>
                <tr class="text-left text-slate-500 border-b border-slate-100">
                    <th class="px-6 py-3 font-medium">{{ __('Quotation') }}</th>
                    <th class="px-6 py-3 font-medium">{{ __('Issue date') }}</th>
                    <th class="px-6 py-3 font-medium">{{ __('Total') }}</th>
                    <th class="px-6 py-3 font-medium">{{ __('Status') }}</th>
                    <th class="px-6 py-3 font-medium"></th>
                </tr>
            </thead>
            <tbody>
                @foreach ($quotations as $quotation)
                    @php
                        $statusColors = [
                            'accepted' => 'bg-emerald-50 text-emerald-700',
                            'converted' => 'bg-emerald-50 text-emerald-700',
                            'rejected' => 'bg-red-50 text-red-600',
                            'expired' => 'bg-slate-100 text-slate-500',
                        ];
                    @endphp
                    <tr class="border-b border-slate-50 last:border-0">
                        <td class="px-6 py-3 font-medium text-slate-900">{{ $quotation->quotation_number }}</td>
                        <td class="px-6 py-3 text-slate-500">{{ $quotation->issue_date->format('Y-m-d') }}</td>
                        <td class="px-6 py-3">{{ \App\Support\Money::format($quotation->total) }}</td>
                        <td class="px-6 py-3">
                            <span class="inline-flex items-center gap-1.5 rounded-full px-2 py-0.5 text-xs font-semibold {{ $statusColors[$quotation->isExpired() ? 'expired' : $quotation->status] ?? 'bg-amber-50 text-amber-700' }}">
                                {{ $quotation->isExpired() ? __('Expired') : ucfirst(str_replace('_', ' ', $quotation->status)) }}
                            </span>
                        </td>
                        <td class="px-6 py-3 text-right">
                            <a href="{{ route('public.quotations.show', [$quotation->id, $quotation->public_token]) }}" class="text-xs font-semibold text-brand-700 hover:underline">{{ __('View') }}</a>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif
</div>

<div class="mt-4">{{ $quotations->links() }}</div>
@endsection
