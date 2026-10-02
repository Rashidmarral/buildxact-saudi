@extends('layouts.app')

@section('title', $ticket->ticket_number)

@section('content')
<div class="mb-4">
    <a href="{{ route('app.tickets.index') }}" class="text-sm font-semibold text-slate-500 hover:underline">← {{ __('Back to tickets') }}</a>
</div>

<div class="flex flex-wrap items-start justify-between gap-3 mb-6">
    <div>
        <h2 class="text-lg font-semibold text-slate-900">{{ $ticket->subject }}</h2>
        <p class="text-sm text-slate-500">{{ $ticket->ticket_number }} · {{ __('Opened') }} {{ $ticket->created_at->format('Y-m-d H:i') }}</p>
    </div>
    <div class="flex items-center gap-2">
        <span class="inline-flex items-center rounded-full px-3 py-1 text-sm font-semibold {{ $ticket->priorityBadgeClasses() }}">{{ $ticket->priorityLabel() }}</span>
        <span class="inline-flex items-center rounded-full px-3 py-1 text-sm font-semibold {{ $ticket->statusBadgeClasses() }}">{{ $ticket->statusLabel() }}</span>
    </div>
</div>

<div class="space-y-4">
    {{-- Initial description, rendered as the first message in the thread --}}
    <div class="bg-white rounded-xl border border-slate-100 p-5">
        <div class="flex items-center justify-between mb-2">
            <span class="text-sm font-semibold text-slate-800">{{ $ticket->user?->name ?? __('You') }}</span>
            <span class="text-xs text-slate-400">{{ $ticket->created_at->format('Y-m-d H:i') }}</span>
        </div>
        <p class="text-sm text-slate-700 whitespace-pre-line">{{ $ticket->description }}</p>
        @if ($initialAttachments->isNotEmpty())
            <div class="mt-3 flex flex-wrap gap-2">
                @foreach ($initialAttachments as $attachment)
                    <a href="{{ route('app.tickets.attachments.download', $attachment) }}" class="inline-flex items-center gap-1.5 rounded-lg border border-slate-200 px-2.5 py-1 text-xs text-slate-600 hover:border-slate-300">
                        @include('partials.icon', ['name' => 'clipboard', 'class' => 'h-3.5 w-3.5'])
                        {{ $attachment->original_name }} ({{ $attachment->humanSize() }})
                    </a>
                @endforeach
            </div>
        @endif
    </div>

    {{-- Public replies only — internal notes never reach this view (see TicketController::show) --}}
    @foreach ($ticket->publicReplies as $reply)
        @php $isAdmin = $reply->author && ($reply->author->isSuperAdmin() || $reply->author->isAdminStaff()); @endphp
        <div class="bg-white rounded-xl border {{ $isAdmin ? 'border-brand-100 bg-brand-50/30' : 'border-slate-100' }} p-5">
            <div class="flex items-center justify-between mb-2">
                <span class="text-sm font-semibold text-slate-800">{{ $reply->author?->name ?? __('Support team') }} @if ($isAdmin) <span class="text-xs font-normal text-brand-600">({{ __('Support') }})</span> @endif</span>
                <span class="text-xs text-slate-400">{{ $reply->created_at->format('Y-m-d H:i') }}</span>
            </div>
            <p class="text-sm text-slate-700 whitespace-pre-line">{{ $reply->body }}</p>
            @if ($reply->attachments->isNotEmpty())
                <div class="mt-3 flex flex-wrap gap-2">
                    @foreach ($reply->attachments as $attachment)
                        <a href="{{ route('app.tickets.attachments.download', $attachment) }}" class="inline-flex items-center gap-1.5 rounded-lg border border-slate-200 px-2.5 py-1 text-xs text-slate-600 hover:border-slate-300">
                            @include('partials.icon', ['name' => 'clipboard', 'class' => 'h-3.5 w-3.5'])
                            {{ $attachment->original_name }} ({{ $attachment->humanSize() }})
                        </a>
                    @endforeach
                </div>
            @endif
        </div>
    @endforeach
</div>

@if ($ticket->isOpenForReply())
    <form method="POST" action="{{ route('app.tickets.reply', $ticket) }}" enctype="multipart/form-data" class="mt-6 bg-white rounded-xl border border-slate-100 p-5 space-y-3">
        @csrf
        <label class="block text-sm font-medium text-slate-700">{{ __('Reply') }}</label>
        <textarea name="body" rows="4" required maxlength="10000" class="w-full rounded-lg border border-slate-200 focus:border-brand-500 focus:ring-brand-500"></textarea>
        <input type="file" name="attachment" class="w-full text-sm text-slate-600 file:mr-3 file:rounded-lg file:border-0 file:bg-slate-100 file:px-3 file:py-2 file:text-sm file:font-semibold file:text-slate-600 hover:file:bg-slate-200">
        <button type="submit" class="rounded-lg bg-brand-600 px-6 py-2.5 font-semibold text-white hover:bg-brand-700">{{ __('Send reply') }}</button>
    </form>
@else
    <p class="mt-6 text-sm text-slate-500">{{ __('This ticket is closed. Open a new ticket if you need further help.') }}</p>
@endif
@endsection
