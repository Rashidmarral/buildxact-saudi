@extends('layouts.app')

@section('title', __('Recurring Invoices'))

@section('content')
<div class="flex items-center justify-between mb-6">
    <p class="text-sm text-slate-500">{{ __('Automatically generate draft invoices on a schedule.') }}</p>
    <a href="{{ route('app.recurring-invoices.create') }}" class="rounded-lg bg-brand-600 px-4 py-2 text-sm font-semibold text-white hover:bg-brand-700">{{ __('+ New recurring invoice') }}</a>
</div>

<div class="bg-white rounded-xl border border-slate-100">
    @if ($recurringInvoices->isEmpty())
        <p class="px-6 py-8 text-sm text-slate-500">{{ __('No recurring invoices yet.') }}</p>
    @else
        <table class="w-full text-sm">
            <thead>
                <tr class="text-left text-slate-500 border-b border-slate-100">
                    <th class="px-6 py-3 font-medium">{{ __('Title') }}</th>
                    <th class="px-6 py-3 font-medium">{{ __('Client') }}</th>
                    <th class="px-6 py-3 font-medium">{{ __('Frequency') }}</th>
                    <th class="px-6 py-3 font-medium">{{ __('Next invoice') }}</th>
                    <th class="px-6 py-3 font-medium">{{ __('Generated') }}</th>
                    <th class="px-6 py-3 font-medium">{{ __('Status') }}</th>
                    <th class="px-6 py-3"></th>
                </tr>
            </thead>
            <tbody>
                @foreach ($recurringInvoices as $recurringInvoice)
                    <tr class="border-b border-slate-50 last:border-0">
                        <td class="px-6 py-3 font-medium text-slate-800">{{ $recurringInvoice->title }}</td>
                        <td class="px-6 py-3">{{ $recurringInvoice->client->name }}</td>
                        <td class="px-6 py-3">{{ __(ucfirst($recurringInvoice->frequency)) }}</td>
                        <td class="px-6 py-3">{{ $recurringInvoice->status === 'active' ? $recurringInvoice->next_run_date->format('Y-m-d') : '—' }}</td>
                        <td class="px-6 py-3">{{ $recurringInvoice->generated_count }}</td>
                        <td class="px-6 py-3">
                            @php($colors = ['active' => 'bg-emerald-50 text-emerald-700', 'paused' => 'bg-amber-50 text-amber-700', 'completed' => 'bg-slate-100 text-slate-600', 'cancelled' => 'bg-red-50 text-red-700'])
                            <span class="inline-flex rounded-full px-2.5 py-0.5 text-xs font-medium {{ $colors[$recurringInvoice->status] ?? 'bg-slate-100 text-slate-600' }}">
                                {{ __(ucfirst($recurringInvoice->status)) }}
                            </span>
                        </td>
                        <td class="px-6 py-3 text-end">
                            <div class="flex items-center justify-end gap-3">
                                @if ($recurringInvoice->status === 'active')
                                    <form method="POST" action="{{ route('app.recurring-invoices.pause', $recurringInvoice) }}">
                                        @csrf
                                        <button type="submit" class="text-slate-500 hover:text-slate-700">{{ __('Pause') }}</button>
                                    </form>
                                @elseif ($recurringInvoice->status === 'paused')
                                    <form method="POST" action="{{ route('app.recurring-invoices.resume', $recurringInvoice) }}">
                                        @csrf
                                        <button type="submit" class="text-brand-700 hover:underline">{{ __('Resume') }}</button>
                                    </form>
                                @endif
                                <a href="{{ route('app.recurring-invoices.edit', $recurringInvoice) }}" class="text-brand-700 hover:underline">{{ __('Edit') }}</a>
                                <form method="POST" action="{{ route('app.recurring-invoices.destroy', $recurringInvoice) }}" onsubmit="return confirm('{{ __('Delete this recurring invoice? This does not affect invoices already generated.') }}')">
                                    @csrf @method('DELETE')
                                    <button type="submit" class="text-red-600 hover:underline">{{ __('Delete') }}</button>
                                </form>
                            </div>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif
</div>

<div class="mt-4">{{ $recurringInvoices->links() }}</div>
@endsection
