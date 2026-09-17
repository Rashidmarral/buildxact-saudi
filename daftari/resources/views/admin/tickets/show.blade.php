@extends('layouts.admin')

@section('title', $ticket->ticket_number)

@section('content')
<div class="mb-4">
    <a href="{{ route('admin.tickets.index') }}" class="text-sm font-semibold text-slate-500 hover:underline">← {{ __('Back to tickets') }}</a>
</div>

<div class="flex flex-wrap items-start justify-between gap-3 mb-6">
    <div>
        <h2 class="text-lg font-semibold text-slate-900">{{ $ticket->subject }}</h2>
        <p class="text-sm text-slate-500">
            {{ $ticket->ticket_number }} ·
            <a href="{{ route('admin.companies.show', $ticket->company) }}" class="text-brand-700 hover:underline">{{ $ticket->company?->name }}</a>
            · {{ $ticket->user?->name ?? __('Unknown user') }}
            · {{ __('Opened') }} {{ $ticket->created_at->format('Y-m-d H:i') }}
        </p>
    </div>
    <div class="flex items-center gap-2">
        <span class="inline-flex items-center rounded-full px-3 py-1 text-sm font-semibold {{ $ticket->priorityBadgeClasses() }}">{{ $ticket->priorityLabel() }}</span>
        <span class="inline-flex items-center rounded-full px-3 py-1 text-sm font-semibold {{ $ticket->statusBadgeClasses() }}">{{ $ticket->statusLabel() }}</span>
    </div>
</div>

<div class="grid lg:grid-cols-3 gap-6">
    <div class="lg:col-span-2 space-y-4">
        {{-- Initial description --}}
        <div class="bg-white rounded-xl border border-slate-100 p-5">
            <div class="flex items-center justify-between mb-2">
                <span class="text-sm font-semibold text-slate-800">{{ $ticket->user?->name ?? __('Customer') }}</span>
                <span class="text-xs text-slate-400">{{ $ticket->created_at->format('Y-m-d H:i') }}</span>
            </div>
            <p class="text-sm text-slate-700 whitespace-pre-line">{{ $ticket->description }}</p>
            @if ($initialAttachments->isNotEmpty())
                <div class="mt-3 flex flex-wrap gap-2">
                    @foreach ($initialAttachments as $attachment)
                        <a href="{{ route('admin.tickets.attachments.download', $attachment) }}" class="inline-flex items-center gap-1.5 rounded-lg border border-slate-200 px-2.5 py-1 text-xs text-slate-600 hover:border-slate-300">
                            @include('partials.icon', ['name' => 'clipboard', 'class' => 'h-3.5 w-3.5'])
                            {{ $attachment->original_name }} ({{ $attachment->humanSize() }})
                        </a>
                    @endforeach
                </div>
            @endif
        </div>

        {{-- Full thread: public replies AND internal notes, clearly distinguished --}}
        @foreach ($ticket->replies as $reply)
            <div class="bg-white rounded-xl border p-5 {{ $reply->is_internal_note ? 'border-amber-200 bg-amber-50/40' : 'border-slate-100' }}">
                <div class="flex items-center justify-between mb-2">
                    <span class="text-sm font-semibold text-slate-800">
                        {{ $reply->author?->name ?? __('Unknown') }}
                        @if ($reply->is_internal_note)
                            <span class="ms-1 inline-flex items-center rounded-full bg-amber-100 px-2 py-0.5 text-xs font-semibold text-amber-800">{{ __('Internal note — not visible to customer') }}</span>
                        @endif
                    </span>
                    <span class="text-xs text-slate-400">{{ $reply->created_at->format('Y-m-d H:i') }}</span>
                </div>
                <p class="text-sm text-slate-700 whitespace-pre-line">{{ $reply->body }}</p>
                @if ($reply->attachments->isNotEmpty())
                    <div class="mt-3 flex flex-wrap gap-2">
                        @foreach ($reply->attachments as $attachment)
                            <a href="{{ route('admin.tickets.attachments.download', $attachment) }}" class="inline-flex items-center gap-1.5 rounded-lg border border-slate-200 px-2.5 py-1 text-xs text-slate-600 hover:border-slate-300">
                                @include('partials.icon', ['name' => 'clipboard', 'class' => 'h-3.5 w-3.5'])
                                {{ $attachment->original_name }} ({{ $attachment->humanSize() }})
                            </a>
                        @endforeach
                    </div>
                @endif
            </div>
        @endforeach

        {{-- Public reply --}}
        <form method="POST" action="{{ route('admin.tickets.reply', $ticket) }}" enctype="multipart/form-data" class="bg-white rounded-xl border border-slate-100 p-5 space-y-3">
            @csrf
            <label class="block text-sm font-medium text-slate-700">{{ __('Reply to customer') }}</label>
            <textarea name="body" rows="4" required maxlength="10000" class="w-full rounded-lg border border-slate-200 focus:border-brand-500 focus:ring-brand-500"></textarea>
            <input type="file" name="attachment" class="w-full text-sm text-slate-600 file:mr-3 file:rounded-lg file:border-0 file:bg-slate-100 file:px-3 file:py-2 file:text-sm file:font-semibold file:text-slate-600 hover:file:bg-slate-200">
            <button type="submit" class="rounded-lg bg-brand-600 px-6 py-2.5 font-semibold text-white hover:bg-brand-700">{{ __('Send reply') }}</button>
        </form>

        {{-- Internal note --}}
        <form method="POST" action="{{ route('admin.tickets.note', $ticket) }}" enctype="multipart/form-data" class="bg-amber-50/40 rounded-xl border border-amber-200 p-5 space-y-3">
            @csrf
            <label class="block text-sm font-medium text-amber-900">{{ __('Add internal note (never visible to the customer)') }}</label>
            <textarea name="body" rows="3" required maxlength="10000" class="w-full rounded-lg border border-amber-200 focus:border-amber-500 focus:ring-amber-500"></textarea>
            <input type="file" name="attachment" class="w-full text-sm text-slate-600 file:mr-3 file:rounded-lg file:border-0 file:bg-white file:px-3 file:py-2 file:text-sm file:font-semibold file:text-slate-600 hover:file:bg-slate-100">
            <button type="submit" class="rounded-lg bg-amber-600 px-6 py-2.5 font-semibold text-white hover:bg-amber-700">{{ __('Add internal note') }}</button>
        </form>
    </div>

    <div class="space-y-6">
        <div class="bg-white rounded-xl border border-slate-100 p-5 space-y-4">
            <h3 class="font-semibold text-slate-900">{{ __('Manage ticket') }}</h3>

            <form method="POST" action="{{ route('admin.tickets.status', $ticket) }}" class="space-y-1">
                @csrf
                <label class="block text-xs font-medium text-slate-500">{{ __('Status') }}</label>
                <div class="flex gap-2">
                    <select name="status" class="flex-1 rounded-lg border border-slate-200 text-sm">
                        @foreach (\App\Models\Ticket::STATUSES as $value)
                            <option value="{{ $value }}" @selected($ticket->status === $value)>{{ ucfirst(str_replace('_', ' ', $value)) }}</option>
                        @endforeach
                    </select>
                    <button type="submit" class="rounded-lg bg-slate-800 px-3 py-1.5 text-xs font-semibold text-white hover:bg-slate-900">{{ __('Save') }}</button>
                </div>
            </form>

            <form method="POST" action="{{ route('admin.tickets.priority', $ticket) }}" class="space-y-1">
                @csrf
                <label class="block text-xs font-medium text-slate-500">{{ __('Priority') }}</label>
                <div class="flex gap-2">
                    <select name="priority" class="flex-1 rounded-lg border border-slate-200 text-sm">
                        @foreach (\App\Models\Ticket::PRIORITIES as $value)
                            <option value="{{ $value }}" @selected($ticket->priority === $value)>{{ ucfirst($value) }}</option>
                        @endforeach
                    </select>
                    <button type="submit" class="rounded-lg bg-slate-800 px-3 py-1.5 text-xs font-semibold text-white hover:bg-slate-900">{{ __('Save') }}</button>
                </div>
            </form>

            <form method="POST" action="{{ route('admin.tickets.assign', $ticket) }}" class="space-y-1">
                @csrf
                <label class="block text-xs font-medium text-slate-500">{{ __('Assigned admin') }}</label>
                <div class="flex gap-2">
                    <select name="assigned_admin_id" class="flex-1 rounded-lg border border-slate-200 text-sm">
                        <option value="">{{ __('Unassigned') }}</option>
                        @foreach ($admins as $admin)
                            <option value="{{ $admin->id }}" @selected($ticket->assigned_admin_id === $admin->id)>{{ $admin->name }}</option>
                        @endforeach
                    </select>
                    <button type="submit" class="rounded-lg bg-slate-800 px-3 py-1.5 text-xs font-semibold text-white hover:bg-slate-900">{{ __('Save') }}</button>
                </div>
            </form>

            <dl class="pt-2 border-t border-slate-100 space-y-1.5 text-sm">
                <div class="flex justify-between"><dt class="text-slate-500">{{ __('Created') }}</dt><dd class="font-medium">{{ $ticket->created_at->format('Y-m-d H:i') }}</dd></div>
                <div class="flex justify-between"><dt class="text-slate-500">{{ __('Updated') }}</dt><dd class="font-medium">{{ $ticket->updated_at->format('Y-m-d H:i') }}</dd></div>
                @if ($ticket->resolved_at)
                    <div class="flex justify-between"><dt class="text-slate-500">{{ __('Resolved') }}</dt><dd class="font-medium">{{ $ticket->resolved_at->format('Y-m-d H:i') }}</dd></div>
                @endif
                @if ($ticket->closed_at)
                    <div class="flex justify-between"><dt class="text-slate-500">{{ __('Closed') }}</dt><dd class="font-medium">{{ $ticket->closed_at->format('Y-m-d H:i') }}</dd></div>
                @endif
            </dl>
        </div>

        {{-- View company history --}}
        <div class="bg-white rounded-xl border border-slate-100">
            <div class="px-5 py-4 border-b border-slate-100 flex items-center justify-between">
                <h3 class="font-semibold text-slate-900">{{ __('Company ticket history') }}</h3>
                <a href="{{ route('admin.tickets.index', ['company_id' => $ticket->company_id]) }}" class="text-xs font-semibold text-brand-700 hover:underline">{{ __('View all') }}</a>
            </div>
            @if ($companyHistory->isEmpty())
                <p class="px-5 py-6 text-sm text-slate-500">{{ __('No other tickets from this company.') }}</p>
            @else
                <ul class="divide-y divide-slate-50">
                    @foreach ($companyHistory as $past)
                        <li class="px-5 py-3">
                            <a href="{{ route('admin.tickets.show', $past) }}" class="text-sm font-medium text-brand-700 hover:underline">{{ \Illuminate\Support\Str::limit($past->subject, 35) }}</a>
                            <div class="mt-1 flex items-center gap-2">
                                <span class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-semibold {{ $past->statusBadgeClasses() }}">{{ $past->statusLabel() }}</span>
                                <span class="text-xs text-slate-400">{{ $past->created_at->format('Y-m-d') }}</span>
                            </div>
                        </li>
                    @endforeach
                </ul>
            @endif
        </div>
    </div>
</div>
@endsection
