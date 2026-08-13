@extends('layouts.app')

@section('title', __('Roles & Permissions'))

@section('content')
<div class="flex items-center justify-between mb-6">
    <p class="text-sm text-slate-500">{{ __('Control what members can do through system and custom roles.') }}</p>
    <a href="{{ route('app.roles.create') }}" class="rounded-lg bg-brand-600 px-4 py-2 text-sm font-semibold text-white hover:bg-brand-700">{{ __('+ Create custom role') }}</a>
</div>

<div class="bg-white rounded-xl border border-slate-100">
    <table class="w-full text-sm">
        <thead>
            <tr class="text-left text-slate-500 border-b border-slate-100">
                <th class="px-6 py-3 font-medium">{{ __('Role name') }}</th>
                <th class="px-6 py-3 font-medium">{{ __('Members') }}</th>
                <th class="px-6 py-3"></th>
            </tr>
        </thead>
        <tbody>
            @foreach ($roles as $role)
                <tr class="border-b border-slate-50 last:border-0 hover:bg-slate-50">
                    <td class="px-6 py-3 font-medium text-slate-800">
                        {{ $role->name }}
                        @if ($role->is_system)
                            <span class="ms-1 inline-block rounded-full bg-slate-100 text-slate-500 text-xs font-medium px-2 py-0.5">{{ __('System') }}</span>
                        @endif
                    </td>
                    <td class="px-6 py-3 text-slate-500">{{ $role->users_count }}</td>
                    <td class="px-6 py-3 text-right space-x-3 rtl:space-x-reverse">
                        <a href="{{ route('app.roles.show', $role) }}" class="text-slate-500 hover:underline">{{ __('View') }}</a>
                        @unless ($role->is_system)
                            <a href="{{ route('app.roles.edit', $role) }}" class="text-brand-700 hover:underline">{{ __('Edit') }}</a>
                            <form method="POST" action="{{ route('app.roles.destroy', $role) }}" class="inline" onsubmit="return confirm('{{ __('Delete this role?') }}')">
                                @csrf @method('DELETE')
                                <button type="submit" class="text-red-600 hover:underline">{{ __('Delete') }}</button>
                            </form>
                        @endunless
                    </td>
                </tr>
            @endforeach
        </tbody>
    </table>
</div>
@endsection
