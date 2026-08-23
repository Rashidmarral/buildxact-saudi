@extends('layouts.admin')

@section('title', __('Admin Users'))

@section('content')
<div class="bg-white rounded-xl border border-slate-100 mb-8">
    <table class="w-full text-sm">
        <thead>
            <tr class="text-left text-slate-500 border-b border-slate-100">
                <th class="px-6 py-3 font-medium">{{ __('Name') }}</th>
                <th class="px-6 py-3 font-medium">{{ __('Email') }}</th>
                <th class="px-6 py-3 font-medium">{{ __('Access') }}</th>
                <th class="px-6 py-3"></th>
            </tr>
        </thead>
        <tbody>
            @foreach ($admins as $admin)
                <tr class="border-b border-slate-50 last:border-0">
                    <td class="px-6 py-3 font-medium text-slate-800">{{ $admin->name }}</td>
                    <td class="px-6 py-3 text-slate-500">{{ $admin->email }}</td>
                    <td class="px-6 py-3 text-slate-500">
                        @if ($admin->role === 'super_admin')
                            <span class="inline-block rounded-full bg-brand-50 text-brand-700 text-xs font-medium px-2.5 py-1">{{ __('Super admin — full access') }}</span>
                        @else
                            @forelse ($admin->adminRoles as $role)
                                <span class="inline-block rounded-full bg-slate-100 text-slate-600 text-xs font-medium px-2.5 py-1 me-1 mb-1">{{ $role->name }}</span>
                            @empty
                                <span class="text-xs text-slate-400">{{ __('No roles assigned') }}</span>
                            @endforelse
                        @endif
                    </td>
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
            <input type="text" name="name" required class="mt-1 w-full rounded-lg border border-slate-200 focus:border-brand-500 focus:ring-brand-500">
        </div>
        <div>
            <label class="block text-sm font-medium text-slate-700">{{ __('Email') }}</label>
            <input type="email" name="email" required class="mt-1 w-full rounded-lg border border-slate-200 focus:border-brand-500 focus:ring-brand-500">
        </div>
        <div>
            <label class="block text-sm font-medium text-slate-700">{{ __('Password') }}</label>
            <input type="password" name="password" required class="mt-1 w-full rounded-lg border border-slate-200 focus:border-brand-500 focus:ring-brand-500">
        </div>
        <div>
            <label class="block text-sm font-medium text-slate-700">{{ __('Confirm password') }}</label>
            <input type="password" name="password_confirmation" required class="mt-1 w-full rounded-lg border border-slate-200 focus:border-brand-500 focus:ring-brand-500">
        </div>
        <div>
            <label class="block text-xs font-semibold uppercase text-slate-500 mb-2">{{ __('Access level') }}</label>
            <div class="grid grid-cols-2 gap-3">
                <label class="cursor-pointer">
                    <input type="radio" name="admin_type" id="admin-type-staff" value="admin_staff" class="peer sr-only" checked>
                    <span class="block text-center rounded-lg border border-slate-200 py-2.5 text-sm font-semibold text-slate-600 peer-checked:border-brand-500 peer-checked:bg-brand-50 peer-checked:text-brand-700">{{ __('Limited (custom role)') }}</span>
                </label>
                <label class="cursor-pointer">
                    <input type="radio" name="admin_type" id="admin-type-super" value="super_admin" class="peer sr-only">
                    <span class="block text-center rounded-lg border border-slate-200 py-2.5 text-sm font-semibold text-slate-600 peer-checked:border-brand-500 peer-checked:bg-brand-50 peer-checked:text-brand-700">{{ __('Super admin') }}</span>
                </label>
            </div>
        </div>
        <div id="admin-role-picker">
            <label class="block text-xs font-semibold uppercase text-slate-500 mb-2">{{ __('Admin roles') }}</label>
            @if ($adminRoles->isEmpty())
                <p class="text-xs text-slate-400">{{ __('No admin roles yet — create one from Admin Roles first.') }}</p>
            @else
                <div class="space-y-2">
                    @foreach ($adminRoles as $role)
                        <label class="flex items-center gap-2 text-sm text-slate-700">
                            <input type="checkbox" name="admin_role_ids[]" value="{{ $role->id }}" class="rounded border-slate-300 text-brand-600 focus:ring-brand-500">
                            {{ $role->name }}
                        </label>
                    @endforeach
                </div>
            @endif
        </div>
        <button type="submit" class="rounded-lg bg-brand-600 px-6 py-2.5 font-semibold text-white hover:bg-brand-700">{{ __('Add admin') }}</button>
    </form>
</div>

<script>
(function () {
    const staffRadio = document.getElementById('admin-type-staff');
    const superRadio = document.getElementById('admin-type-super');
    const picker = document.getElementById('admin-role-picker');

    function sync() {
        picker.classList.toggle('hidden', ! staffRadio.checked);
    }

    staffRadio.addEventListener('change', sync);
    superRadio.addEventListener('change', sync);
    sync();
})();
</script>
@endsection
