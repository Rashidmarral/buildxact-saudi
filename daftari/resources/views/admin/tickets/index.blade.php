@extends('layouts.admin')

@section('title', __('Support Tickets'))

@section('content')
<div class="grid grid-cols-2 gap-4 sm:gap-5 lg:grid-cols-5">
    @foreach ([
        ['label' => __('Open'), 'value' => number_format($stats['open']), 'icon' => 'support', 'accent' => 'from-sky-500 to-blue-500'],
        ['label' => __('In progress'), 'value' => number_format($stats['in_progress']), 'icon' => 'clock', 'accent' => 'from-amber-500 to-orange-500'],
        ['label' => __('Waiting for customer'), 'value' => number_format($stats['waiting_customer']), 'icon' => 'clock', 'accent' => 'from-violet-500 to-purple-500'],
        ['label' => __('Urgent open'), 'value' => number_format($stats['urgent']), 'icon' => 'alert', 'accent' => 'from-red-500 to-rose-500'],
        ['label' => __('Unassigned open'), 'value' => number_format($stats['unassigned']), 'icon' => 'building', 'accent' => 'from-slate-500 to-slate-600'],
    ] as $card)
        <div class="card-hover rounded-2xl border border-slate-100 bg-white p-5 shadow-card">
            <div class="flex items-center justify-between">
                <span class="text-xs font-medium text-slate-500">{{ $card['label'] }}</span>
                <span class="inline-flex h-8 w-8 items-center justify-center rounded-lg bg-gradient-to-br {{ $card['accent'] }} text-white">
                    @include('partials.icon', ['name' => $card['icon'], 'class' => 'h-4 w-4'])
                </span>
            </div>
            <div class="mt-3 text-2xl font-bold text-slate-900">{{ $card['value'] }}</div>
        </div>
    @endforeach
</div>

<form method="GET" class="mt-6 mb-6 bg-white rounded-xl border border-slate-100 p-4 space-y-3">
    <div class="flex flex-wrap items-end gap-3">
        <div>
            <label class="block text-xs font-medium text-slate-500 mb-1">{{ __('Search') }}</label>
            <input type="text" name="q" value="{{ request('q') }}" placeholder="{{ __('Ticket number or subject...') }}" class="rounded-lg border border-slate-200 text-sm">
        </div>
        <div>
            <label class="block text-xs font-medium text-slate-500 mb-1">{{ __('Company') }}</label>
            <select name="company_id" class="rounded-lg border border-slate-200 text-sm">
                <option value="">{{ __('All companies') }}</option>
                @foreach ($companies as $company)
                    <option value="{{ $company->id }}" @selected((string) request('company_id') === (string) $company->id)>{{ $company->name }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label class="block text-xs font-medium text-slate-500 mb-1">{{ __('Status') }}</label>
            <select name="status" class="rounded-lg border border-slate-200 text-sm">
                <option value="">{{ __('All statuses') }}</option>
                @foreach (\App\Models\Ticket::STATUSES as $value)
                    <option value="{{ $value }}" @selected(request('status') === $value)>{{ ucfirst(str_replace('_', ' ', $value)) }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label class="block text-xs font-medium text-slate-500 mb-1">{{ __('Priority') }}</label>
            <select name="priority" class="rounded-lg border border-slate-200 text-sm">
                <option value="">{{ __('All priorities') }}</option>
                @foreach (\App\Models\Ticket::PRIORITIES as $value)
                    <option value="{{ $value }}" @selected(request('priority') === $value)>{{ ucfirst($value) }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label class="block text-xs font-medium text-slate-500 mb-1">{{ __('Assigned to') }}</label>
            <select name="assigned" class="rounded-lg border border-slate-200 text-sm">
                <option value="">{{ __('Anyone') }}</option>
                <option value="unassigned" @selected(request('assigned') === 'unassigned')>{{ __('Unassigned') }}</option>
                @foreach ($admins as $admin)
                    <option value="{{ $admin->id }}" @selected((string) request('assigned') === (string) $admin->id)>{{ $admin->name }}</option>
                @endforeach
            </select>
        </div>
        <button type="submit" class="rounded-lg bg-brand-600 px-4 py-2 text-sm font-semibold text-white hover:bg-brand-700">{{ __('Filter') }}</button>
        @if (request()->anyFilled(['q', 'company_id', 'status', 'priority', 'assigned']))
            <a href="{{ route('admin.tickets.index') }}" class="text-sm font-semibold text-slate-500 hover:underline">{{ __('Clear') }}</a>
        @endif
    </div>
</form>

<div class="bg-white rounded-xl border border-slate-100 overflow-x-auto">
    <table class="w-full text-sm">
        <thead>
            <tr class="text-left text-slate-500 border-b border-slate-100 whitespace-nowrap">
                <th class="px-6 py-3 font-medium">{{ __('Ticket') }}</th>
                <th class="px-4 py-3 font-medium">{{ __('Company') }}</th>
                <th class="px-4 py-3 font-medium">{{ __('User') }}</th>
                <th class="px-4 py-3 font-medium">{{ __('Subject') }}</th>
                <th class="px-4 py-3 font-medium">{{ __('Priority') }}</th>
                <th class="px-4 py-3 font-medium">{{ __('Status') }}</th>
                <th class="px-4 py-3 font-medium">{{ __('Assigned admin') }}</th>
                <th class="px-4 py-3 font-medium">{{ __('Updated') }}</th>
                <th class="px-6 py-3"></th>
            </tr>
        </thead>
        <tbody>
            @forelse ($tickets as $ticket)
                <tr class="border-b border-slate-50 last:border-0 hover:bg-slate-50">
                    <td class="px-6 py-3 font-mono text-xs text-slate-500">{{ $ticket->ticket_number }}</td>
                    <td class="px-4 py-3">{{ $ticket->company?->name ?? '—' }}</td>
                    <td class="px-4 py-3">{{ $ticket->user?->name ?? '—' }}</td>
                    <td class="px-4 py-3 font-medium text-slate-800">{{ \Illuminate\Support\Str::limit($ticket->subject, 40) }}</td>
                    <td class="px-4 py-3"><span class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-semibold {{ $ticket->priorityBadgeClasses() }}">{{ $ticket->priorityLabel() }}</span></td>
                    <td class="px-4 py-3"><span class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-semibold {{ $ticket->statusBadgeClasses() }}">{{ $ticket->statusLabel() }}</span></td>
                    <td class="px-4 py-3">{{ $ticket->assignedAdmin?->name ?? __('Unassigned') }}</td>
                    <td class="px-4 py-3 text-slate-500">{{ $ticket->updated_at->diffForHumans() }}</td>
                    <td class="px-6 py-3 text-right">
                        <a href="{{ route('admin.tickets.show', $ticket) }}" class="text-brand-700 hover:underline">{{ __('View') }}</a>
                    </td>
                </tr>
            @empty
                <tr><td colspan="9" class="px-6 py-8 text-center text-slate-400">{{ __('No tickets match these filters.') }}</td></tr>
            @endforelse
        </tbody>
    </table>
</div>
<div class="mt-4">{{ $tickets->links() }}</div>
@endsection
