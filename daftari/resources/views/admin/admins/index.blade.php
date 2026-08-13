@extends('layouts.admin')

@section('title', __('Admin Users'))

@section('content')
<div class="bg-white rounded-xl border border-slate-100 mb-8">
    <table class="w-full text-sm">
        <thead>
            <tr class="text-left text-slate-500 border-b border-slate-100">
                <th class="px-6 py-3 font-medium">{{ __('Name') }}</th>
                <th class="px-6 py-3 font-medium">{{ __('Email') }}</th>
                <th class="px-6 py-3"></th>
            </tr>
        </thead>
        <tbody>
            @foreach ($admins as $admin)
                <tr class="border-b border-slate-50 last:border-0">
                    <td class="px-6 py-3 font-medium text-slate-800">{{ $admin->name }}</td>
                    <td class="px-6 py-3 text-slate-500">{{ $admin->email }}</td>
                    <td class="px-6 py-3 text-right">
                        @if ($admin->id !== auth()->id())
                            <form method="POST" action="{{ route('admin.admins.destroy', $admin) }}" onsubmit="return confirm('{{ __('Remove this admin?') }}')">
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
    <h2 class="font-semibold text-slate-900 mb-4">{{ __('Add platform admin') }}</h2>
    <form method="POST" action="{{ route('admin.admins.store') }}" class="space-y-4">
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
            <label class="block text-sm font-medium text-slate-700">{{ __('Password') }}</label>
            <input type="password" name="password" required class="mt-1 w-full rounded-lg border-slate-200 focus:border-brand-500 focus:ring-brand-500">
        </div>
        <div>
            <label class="block text-sm font-medium text-slate-700">{{ __('Confirm password') }}</label>
            <input type="password" name="password_confirmation" required class="mt-1 w-full rounded-lg border-slate-200 focus:border-brand-500 focus:ring-brand-500">
        </div>
        <button type="submit" class="rounded-lg bg-brand-600 px-6 py-2.5 font-semibold text-white hover:bg-brand-700">{{ __('Add admin') }}</button>
    </form>
</div>
@endsection
