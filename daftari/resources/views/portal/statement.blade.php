@extends('layouts.portal')

@section('title', __('Account Statement'))

@section('content')
<h1 class="text-xl font-bold text-slate-900 mb-6">{{ __('Account Statement') }}</h1>

<div class="bg-white rounded-xl border border-slate-100">
    @if ($lines->isEmpty())
        <p class="px-6 py-8 text-sm text-slate-500">{{ __('No account activity yet.') }}</p>
    @else
        <table class="w-full text-sm">
            <thead>
                <tr class="text-left text-slate-500 border-b border-slate-100">
                    <th class="px-6 py-3 font-medium">{{ __('Date') }}</th>
                    <th class="px-6 py-3 font-medium">{{ __('Description') }}</th>
                    <th class="px-6 py-3 font-medium">{{ __('Debit') }}</th>
                    <th class="px-6 py-3 font-medium">{{ __('Credit') }}</th>
                    <th class="px-6 py-3 font-medium">{{ __('Balance') }}</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($lines as $line)
                    <tr class="border-b border-slate-50 last:border-0">
                        <td class="px-6 py-3 text-slate-500">{{ $line->date->format('Y-m-d') }}</td>
                        <td class="px-6 py-3">{{ $line->description }}</td>
                        <td class="px-6 py-3">{{ $line->debit > 0 ? number_format($line->debit, 2) : '—' }}</td>
                        <td class="px-6 py-3">{{ $line->credit > 0 ? number_format($line->credit, 2) : '—' }}</td>
                        <td class="px-6 py-3 font-semibold text-slate-900">SAR {{ number_format($line->balance, 2) }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif
</div>
@endsection
