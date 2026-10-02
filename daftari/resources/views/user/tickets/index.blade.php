@extends('layouts.app')

@section('title', __('Support'))

@section('content')
<div class="flex flex-wrap items-center justify-between gap-3 mb-6">
    <p class="text-sm text-slate-500">{{ __('Open a ticket and our team will get back to you.') }}</p>
    <a href="{{ route('app.tickets.create') }}" class="rounded-lg bg-brand-600 px-4 py-2 text-sm font-semibold text-white hover:bg-brand-700">{{ __('+ New ticket') }}</a>
</div>

<form method="GET" class="mb-6 bg-white rounded-xl border border-slate-100 p-4">
    <div class="flex flex-wrap items-end gap-3">
        <div>
            <label class="block text-xs font-medium text-slate-500 mb-1">{{ __('Status') }}</label>
            <select name="status" class="rounded-lg border border-slate-200 text-sm">
                <option value="">{{ __('All statuses') }}</option>
                @foreach (\App\Models\Ticket::STATUSES as $value)
                    <option value="{{ $value }}" @selected(request('status') === $value)>{{ ucfirst(str_replace('_', ' ', $value)) }}</option>
                @endforeach
            </select>
        </div>
        <button type="submit" class="rounded-lg bg-brand-600 px-4 py-2 text-sm font-semibold text-white hover:bg-brand-700">{{ __('Filter') }}</button>
        @if (request()->filled('status'))
            <a href="{{ route('app.tickets.index') }}" class="text-sm font-semibold text-slate-500 hover:underline">{{ __('Clear') }}</a>
        @endif
    </div>
</form>

<div class="bg-white rounded-xl border border-slate-100 overflow-x-auto">
    @if ($tickets->isEmpty())
        <p class="px-6 py-8 text-sm text-slate-500">{{ __('No support tickets yet.') }}</p>
    @else
        <table class="w-full text-sm">
            <thead>
                <tr class="text-left text-slate-500 border-b border-slate-100 whitespace-nowrap">
                    <th class="px-6 py-3 font-medium">{{ __('Ticket') }}</th>
                    <th class="px-4 py-3 font-medium">{{ __('Subject') }}</th>
                    <th class="px-4 py-3 font-medium">{{ __('Priority') }}</th>
                    <th class="px-4 py-3 font-medium">{{ __('Status') }}</th>
                    <th class="px-4 py-3 font-medium">{{ __('Created') }}</th>
                    <th class="px-4 py-3 font-medium">{{ __('Updated') }}</th>
                    <th class="px-6 py-3"></th>
                </tr>
            </thead>
            <tbody>
                @foreach ($tickets as $ticket)
                    <tr class="border-b border-slate-50 last:border-0 hover:bg-slate-50">
                        <td class="px-6 py-3 font-mono text-xs text-slate-500">{{ $ticket->ticket_number }}</td>
                        <td class="px-4 py-3 font-medium text-slate-800">{{ $ticket->subject }}</td>
                        <td class="px-4 py-3"><span class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-semibold {{ $ticket->priorityBadgeClasses() }}">{{ $ticket->priorityLabel() }}</span></td>
                        <td class="px-4 py-3"><span class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-semibold {{ $ticket->statusBadgeClasses() }}">{{ $ticket->statusLabel() }}</span></td>
                        <td class="px-4 py-3 text-slate-500">{{ $ticket->created_at->format('Y-m-d') }}</td>
                        <td class="px-4 py-3 text-slate-500">{{ $ticket->updated_at->diffForHumans() }}</td>
                        <td class="px-6 py-3 text-right">
                            <a href="{{ route('app.tickets.show', $ticket) }}" class="text-brand-700 hover:underline">{{ __('View') }}</a>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif
</div>
<div class="mt-4">{{ $tickets->links() }}</div>
@endsection
