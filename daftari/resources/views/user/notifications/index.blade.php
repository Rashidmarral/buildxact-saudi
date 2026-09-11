@extends('layouts.app')

@section('title', __('Notifications'))

@section('content')
<div class="mb-6 flex items-center justify-between">
    <div>
        <h1 class="text-2xl font-bold text-slate-900">{{ __('Notifications') }}</h1>
        <p class="text-sm text-slate-500 mt-1">{{ __('Updates about your account, billing, and team.') }}</p>
    </div>
    @if (Auth::user()->unreadNotifications->isNotEmpty())
        <form method="POST" action="{{ route('app.notifications.read-all') }}">
            @csrf
            <button type="submit" class="rounded-lg border border-slate-200 px-4 py-2 text-sm font-semibold text-slate-600 hover:border-slate-300">{{ __('Mark all as read') }}</button>
        </form>
    @endif
</div>

<div class="bg-white rounded-xl border border-slate-100">
    @if ($notifications->isEmpty())
        <p class="px-6 py-8 text-sm text-slate-500">{{ __('No notifications yet.') }}</p>
    @else
        <div class="divide-y divide-slate-50">
            @foreach ($notifications as $notification)
                <form method="POST" action="{{ route('app.notifications.read', $notification->id) }}" class="block">
                    @csrf
                    <button type="submit" class="flex w-full items-start gap-3 px-6 py-4 text-start hover:bg-slate-50 {{ $notification->read_at ? '' : 'bg-brand-50/40' }}">
                        <span class="mt-0.5 inline-flex h-8 w-8 shrink-0 items-center justify-center rounded-full {{ $notification->read_at ? 'bg-slate-100 text-slate-400' : 'bg-brand-100 text-brand-600' }}">
                            @include('partials.icon', ['name' => $notification->data['icon'] ?? 'bell', 'class' => 'h-4 w-4'])
                        </span>
                        <span class="min-w-0 flex-1">
                            <span class="block text-sm font-medium text-slate-800">{{ $notification->data['title'] ?? '' }}</span>
                            <span class="block text-sm text-slate-500">{{ $notification->data['body'] ?? '' }}</span>
                            <span class="block text-xs text-slate-400 mt-1">{{ $notification->created_at->diffForHumans() }}</span>
                        </span>
                        @unless ($notification->read_at)
                            <span class="mt-1.5 h-2 w-2 shrink-0 rounded-full bg-brand-500"></span>
                        @endunless
                    </button>
                </form>
            @endforeach
        </div>
    @endif
</div>

<div class="mt-4">{{ $notifications->links() }}</div>
@endsection
