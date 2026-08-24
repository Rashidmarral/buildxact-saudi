@extends('layouts.app')

@section('title', __('Team'))

@section('content')
@if (session('dev_invite_url'))
    <div class="mb-6 rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-xs text-amber-800">
        <p class="font-semibold">{{ __('Development mode: no email transport is configured, so here is the invite link directly.') }}</p>
        <a href="{{ session('dev_invite_url') }}" class="mt-1 block break-all font-mono text-amber-900 underline">{{ session('dev_invite_url') }}</a>
    </div>
@endif

<div class="flex items-center justify-between mb-6">
    <p class="text-sm text-slate-500">{{ __('Control who can access your account and what they can do.') }}</p>
    <a href="{{ route('app.roles.index') }}" class="rounded-lg border border-slate-200 px-4 py-2 text-sm font-semibold text-slate-600 hover:border-slate-300">{{ __('Manage roles & permissions') }}</a>
</div>

<div class="bg-white rounded-xl border border-slate-100 mb-8">
    <div class="px-6 py-4 border-b border-slate-100">
        <h2 class="font-semibold text-slate-900">{{ __('Team members') }}</h2>
    </div>
    <table class="w-full text-sm">
        <thead>
            <tr class="text-left text-slate-500 border-b border-slate-100">
                <th class="px-6 py-3 font-medium">{{ __('Name') }}</th>
                <th class="px-6 py-3 font-medium">{{ __('Email') }}</th>
                <th class="px-6 py-3 font-medium">{{ __('Roles') }}</th>
                <th class="px-6 py-3"></th>
            </tr>
        </thead>
        <tbody>
            @foreach ($members as $member)
                <tr class="border-b border-slate-50 last:border-0">
                    <td class="px-6 py-3 font-medium text-slate-800">
                        {{ $member->name }}
                        @if ($member->status === 'invited')
                            <span class="ms-1 inline-block rounded-full bg-amber-50 text-amber-700 text-xs font-medium px-2.5 py-1">{{ __('Invited') }}</span>
                        @endif
                    </td>
                    <td class="px-6 py-3 text-slate-500">{{ $member->email }}</td>
                    <td class="px-6 py-3 text-slate-500">
                        @if ($member->role === 'owner')
                            <span class="inline-block rounded-full bg-brand-50 text-brand-700 text-xs font-medium px-2.5 py-1">{{ __('Owner') }}</span>
                        @elseif ($member->roles->isEmpty())
                            <span class="text-xs text-slate-400">{{ __('No roles assigned') }}</span>
                        @else
                            @foreach ($member->roles as $role)
                                <span class="inline-block rounded-full bg-slate-100 text-slate-600 text-xs font-medium px-2.5 py-1 me-1">{{ $role->name }}</span>
                            @endforeach
                        @endif
                    </td>
                    <td class="px-6 py-3 text-right whitespace-nowrap">
                        @if ($member->status === 'invited')
                            <form method="POST" action="{{ route('app.team.resend-invite', $member) }}" class="inline">
                                @csrf
                                <button type="submit" class="text-brand-700 hover:underline me-3">{{ __('Resend invite') }}</button>
                            </form>
                        @endif
                        @if ($member->id !== auth()->id())
                            <form method="POST" action="{{ route('app.team.destroy', $member) }}" onsubmit="return confirm('{{ __('Remove this team member?') }}')" class="inline">
                                @csrf @method('DELETE')
                                <button type="submit" class="text-red-600 hover:underline">{{ __('Remove') }}</button>
                            </form>
                        @endif
                    </td>
                </tr>
            @endforeach
        </tbody>
    </table>
</div>

<div class="bg-white rounded-xl border border-slate-100 p-6 max-w-xl">
    <h2 class="font-semibold text-slate-900 mb-4">{{ __('Invite team member') }}</h2>
    <form method="POST" action="{{ route('app.team.store') }}" class="space-y-4">
        @csrf
        <div>
            <label class="block text-sm font-medium text-slate-700">{{ __('Name') }}</label>
            <input type="text" name="name" required class="mt-1 w-full rounded-lg border border-slate-200 focus:border-brand-500 focus:ring-brand-500">
        </div>
        <div>
            <label class="block text-sm font-medium text-slate-700">{{ __('Email') }}</label>
            <input type="email" name="email" required class="mt-1 w-full rounded-lg border border-slate-200 focus:border-brand-500 focus:ring-brand-500">
        </div>
        <div>
            <label class="block text-sm font-medium text-slate-700">{{ __('Account level') }}</label>
            <select name="role" class="mt-1 w-full rounded-lg border border-slate-200 focus:border-brand-500 focus:ring-brand-500">
                <option value="staff">{{ __('Staff (access controlled by roles below)') }}</option>
                <option value="owner">{{ __('Owner (full access)') }}</option>
            </select>
        </div>
        <div>
            <label class="block text-sm font-medium text-slate-700 mb-1">{{ __('Roles') }}</label>
            <p class="text-xs text-slate-400 mb-2">{{ __('Select at least one role to define this member\'s permissions.') }}</p>
            <div class="grid sm:grid-cols-2 gap-2">
                @foreach ($roles as $role)
                    <label class="flex items-center gap-2 rounded-lg border border-slate-200 px-3 py-2 text-sm">
                        <input type="checkbox" name="role_ids[]" value="{{ $role->id }}" class="rounded border-slate-300 text-brand-600 focus:ring-brand-500">
                        {{ $role->name }}
                        @if ($role->is_system)
                            <span class="text-xs text-slate-400">{{ __('System') }}</span>
                        @endif
                    </label>
                @endforeach
            </div>
        </div>
        <button type="submit" class="rounded-lg bg-brand-600 px-6 py-2.5 font-semibold text-white hover:bg-brand-700">{{ __('Send invite') }}</button>
    </form>
</div>
@endsection
