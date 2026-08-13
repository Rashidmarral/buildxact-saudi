@extends('layouts.app')

@section('title', __('Team'))

@section('content')
@if (session('temporary_password'))
    <div class="mb-6 rounded-lg bg-amber-50 border border-amber-200 text-amber-800 px-4 py-3 text-sm">
        {{ __('Share these credentials with :email — there is no email delivery configured yet.', ['email' => session('temporary_password')['email']]) }}
        <br>
        {{ __('Temporary password') }}: <code class="font-mono font-semibold">{{ session('temporary_password')['password'] }}</code>
    </div>
@endif

<div class="bg-white rounded-xl border border-slate-100 mb-8">
    <div class="px-6 py-4 border-b border-slate-100">
        <h2 class="font-semibold text-slate-900">{{ __('Team members') }}</h2>
    </div>
    <table class="w-full text-sm">
        <thead>
            <tr class="text-left text-slate-500 border-b border-slate-100">
                <th class="px-6 py-3 font-medium">{{ __('Name') }}</th>
                <th class="px-6 py-3 font-medium">{{ __('Email') }}</th>
                <th class="px-6 py-3 font-medium">{{ __('Role') }}</th>
                <th class="px-6 py-3"></th>
            </tr>
        </thead>
        <tbody>
            @foreach ($members as $member)
                <tr class="border-b border-slate-50 last:border-0">
                    <td class="px-6 py-3 font-medium text-slate-800">{{ $member->name }}</td>
                    <td class="px-6 py-3 text-slate-500">{{ $member->email }}</td>
                    <td class="px-6 py-3 text-slate-500">{{ ucfirst($member->role) }}</td>
                    <td class="px-6 py-3 text-right">
                        @if ($member->id !== auth()->id())
                            <form method="POST" action="{{ route('app.team.destroy', $member) }}" onsubmit="return confirm('{{ __('Remove this team member?') }}')">
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
    <h2 class="font-semibold text-slate-900 mb-4">{{ __('Add team member') }}</h2>
    <form method="POST" action="{{ route('app.team.store') }}" class="space-y-4">
        @csrf
        <div>
            <label class="block text-sm font-medium text-slate-700">{{ __('Name') }}</label>
            <input type="text" name="name" required class="mt-1 w-full rounded-lg border-slate-200 focus:border-brand-500 focus:ring-brand-500">
        </div>
        <div>
            <label class="block text-sm font-medium text-slate-700">{{ __('Email') }}</label>
            <input type="email" name="email" required class="mt-1 w-full rounded-lg border-slate-200 focus:border-brand-500 focus:ring-brand-500">
        </div>
        <div>
            <label class="block text-sm font-medium text-slate-700">{{ __('Role') }}</label>
            <select name="role" class="mt-1 w-full rounded-lg border-slate-200 focus:border-brand-500 focus:ring-brand-500">
                <option value="staff">{{ __('Staff') }}</option>
                <option value="owner">{{ __('Owner') }}</option>
            </select>
        </div>
        <button type="submit" class="rounded-lg bg-brand-600 px-6 py-2.5 font-semibold text-white hover:bg-brand-700">{{ __('Add member') }}</button>
    </form>
</div>
@endsection
