@extends('layouts.app')

@section('title', __('Roles & Permissions'))

@section('content')
<div class="flex items-center justify-between mb-6">
    <p class="text-sm text-slate-500">{{ __('Control what members can do through system and custom roles.') }}</p>
    @if (auth()->user()->company->hasFeature('roles_permissions'))
        <a href="{{ route('app.roles.create') }}" class="rounded-lg bg-brand-600 px-4 py-2 text-sm font-semibold text-white hover:bg-brand-700">{{ __('+ Create custom role') }}</a>
    @else
        <span class="rounded-lg bg-amber-50 border border-amber-200 px-4 py-2 text-sm font-medium text-amber-700">{{ __('Custom roles: upgrade your plan') }}</span>
    @endif
</div>

<x-list-toolbar>
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
                    <td class="px-6 py-3 text-right">
                        <div class="flex items-center justify-end gap-1.5">
                            <x-icon-button href="{{ route('app.roles.show', $role) }}" icon="eye" title="{{ __('View') }}" />
                            @unless ($role->is_system)
                                <x-icon-button href="{{ route('app.roles.edit', $role) }}" icon="pencil" title="{{ __('Edit') }}" />
                                <form method="POST" action="{{ route('app.roles.destroy', $role) }}" onsubmit="return confirm('{{ __('Delete this role?') }}')">
                                    @csrf @method('DELETE')
                                    <x-icon-button type="submit" icon="trash" color="red" title="{{ __('Delete') }}" />
                                </form>
                            @endunless
                        </div>
                    </td>
                </tr>
            @endforeach
        </tbody>
    </table>
</div>
</x-list-toolbar>
@endsection
