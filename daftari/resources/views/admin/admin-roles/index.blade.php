@extends('layouts.admin')

@section('title', __('Admin Roles'))

@section('content')
<div class="flex items-center justify-between mb-6">
    <div>
        <h1 class="text-2xl font-bold text-slate-900">{{ __('Admin Roles') }}</h1>
        <p class="text-sm text-slate-500 mt-1">{{ __('Grant platform admins access to only the areas they need. Managing other admins, admin roles, and platform settings always stays super-admin only.') }}</p>
    </div>
    <button type="button" onclick="document.getElementById('add-admin-role-modal').showModal()" class="rounded-lg bg-brand-600 px-4 py-2 text-sm font-semibold text-white hover:bg-brand-700">{{ __('+ Create custom role') }}</button>
</div>

<div class="bg-white rounded-xl border border-slate-100">
    <table class="w-full text-sm">
        <thead>
            <tr class="text-left text-slate-500 border-b border-slate-100">
                <th class="px-6 py-3 font-medium">{{ __('Role name') }}</th>
                <th class="px-6 py-3 font-medium">{{ __('Permissions') }}</th>
                <th class="px-6 py-3 font-medium">{{ __('Members') }}</th>
                <th class="px-6 py-3"></th>
            </tr>
        </thead>
        <tbody>
            @foreach ($adminRoles as $adminRole)
                <tr class="border-b border-slate-50 last:border-0 hover:bg-slate-50">
                    <td class="px-6 py-3 font-medium text-slate-800">
                        {{ $adminRole->name }}
                        @if ($adminRole->is_system)
                            <span class="ms-1 inline-block rounded-full bg-slate-100 text-slate-500 text-xs font-medium px-2 py-0.5">{{ __('System') }}</span>
                        @endif
                    </td>
                    <td class="px-6 py-3 text-slate-500">
                        @forelse ($adminRole->permissions ?? [] as $key)
                            <span class="inline-block rounded-full bg-slate-100 text-slate-600 text-xs font-medium px-2 py-0.5 me-1 mb-1">{{ $permissionCatalog[$key] ?? $key }}</span>
                        @empty
                            <span class="text-xs text-slate-400">{{ __('None') }}</span>
                        @endforelse
                    </td>
                    <td class="px-6 py-3 text-slate-500">{{ $adminRole->users_count }}</td>
                    <td class="px-6 py-3 text-right space-x-3 rtl:space-x-reverse">
                        @unless ($adminRole->is_system)
                            <button type="button" class="text-brand-700 hover:underline"
                                data-edit-admin-role
                                data-update-url="{{ route('admin.admin-roles.update', $adminRole) }}"
                                data-name="{{ $adminRole->name }}"
                                data-name-ar="{{ $adminRole->name_ar }}"
                                data-slug="{{ $adminRole->slug }}"
                                data-permissions="{{ json_encode($adminRole->permissions ?? []) }}"
                            >{{ __('Edit') }}</button>
                            <form method="POST" action="{{ route('admin.admin-roles.destroy', $adminRole) }}" class="inline" onsubmit="return confirm('{{ __('Delete this admin role?') }}')">
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

<dialog id="add-admin-role-modal" class="rounded-2xl border border-slate-100 p-0 w-full max-w-md backdrop:bg-slate-900/40">
    <form method="POST" action="{{ route('admin.admin-roles.store') }}" class="p-6 space-y-4">
        @csrf
        <div class="flex items-start justify-between">
            <h3 class="text-lg font-bold text-slate-900">{{ __('Create custom role') }}</h3>
            <button type="button" onclick="document.getElementById('add-admin-role-modal').close()" class="text-slate-400 hover:text-slate-600">✕</button>
        </div>
        <div>
            <label class="block text-xs font-medium text-slate-500">{{ __('Name') }}</label>
            <input type="text" name="name" required class="mt-1 w-full rounded-lg border border-slate-200 text-sm focus:border-brand-500 focus:ring-brand-500">
        </div>
        <div>
            <label class="block text-xs font-medium text-slate-500">{{ __('Name (Arabic)') }}</label>
            <input type="text" name="name_ar" class="mt-1 w-full rounded-lg border border-slate-200 text-sm focus:border-brand-500 focus:ring-brand-500">
        </div>
        <div>
            <label class="block text-xs font-medium text-slate-500">{{ __('Slug') }}</label>
            <input type="text" name="slug" required pattern="[a-z0-9_-]+" placeholder="e.g. support_agent" class="mt-1 w-full rounded-lg border border-slate-200 text-sm focus:border-brand-500 focus:ring-brand-500">
        </div>
        <div>
            <label class="block text-xs font-medium text-slate-500 mb-2">{{ __('Permissions') }}</label>
            <div class="space-y-2">
                @foreach ($permissionCatalog as $key => $label)
                    <label class="flex items-center gap-2 text-sm text-slate-700">
                        <input type="checkbox" name="permissions[]" value="{{ $key }}" class="rounded border-slate-300 text-brand-600 focus:ring-brand-500">
                        {{ $label }}
                    </label>
                @endforeach
            </div>
        </div>
        <div class="flex gap-3">
            <button type="button" onclick="document.getElementById('add-admin-role-modal').close()" class="flex-1 rounded-lg border border-slate-200 px-4 py-2 text-sm font-semibold text-slate-600 hover:border-slate-300">{{ __('Cancel') }}</button>
            <button type="submit" class="flex-1 rounded-lg bg-brand-600 px-4 py-2 text-sm font-semibold text-white hover:bg-brand-700">{{ __('Save') }}</button>
        </div>
    </form>
</dialog>

<dialog id="edit-admin-role-modal" class="rounded-2xl border border-slate-100 p-0 w-full max-w-md backdrop:bg-slate-900/40">
    <form method="POST" id="edit-admin-role-form" class="p-6 space-y-4">
        @csrf
        @method('PUT')
        <div class="flex items-start justify-between">
            <h3 class="text-lg font-bold text-slate-900">{{ __('Edit admin role') }}</h3>
            <button type="button" onclick="document.getElementById('edit-admin-role-modal').close()" class="text-slate-400 hover:text-slate-600">✕</button>
        </div>
        <div>
            <label class="block text-xs font-medium text-slate-500">{{ __('Name') }}</label>
            <input type="text" name="name" id="edit-admin-role-name" required class="mt-1 w-full rounded-lg border border-slate-200 text-sm focus:border-brand-500 focus:ring-brand-500">
        </div>
        <div>
            <label class="block text-xs font-medium text-slate-500">{{ __('Name (Arabic)') }}</label>
            <input type="text" name="name_ar" id="edit-admin-role-name-ar" class="mt-1 w-full rounded-lg border border-slate-200 text-sm focus:border-brand-500 focus:ring-brand-500">
        </div>
        <div>
            <label class="block text-xs font-medium text-slate-500">{{ __('Slug') }}</label>
            <input type="text" name="slug" id="edit-admin-role-slug" required pattern="[a-z0-9_-]+" class="mt-1 w-full rounded-lg border border-slate-200 text-sm focus:border-brand-500 focus:ring-brand-500">
        </div>
        <div>
            <label class="block text-xs font-medium text-slate-500 mb-2">{{ __('Permissions') }}</label>
            <div class="space-y-2" id="edit-admin-role-permissions">
                @foreach ($permissionCatalog as $key => $label)
                    <label class="flex items-center gap-2 text-sm text-slate-700">
                        <input type="checkbox" name="permissions[]" value="{{ $key }}" class="rounded border-slate-300 text-brand-600 focus:ring-brand-500">
                        {{ $label }}
                    </label>
                @endforeach
            </div>
        </div>
        <div class="flex gap-3">
            <button type="button" onclick="document.getElementById('edit-admin-role-modal').close()" class="flex-1 rounded-lg border border-slate-200 px-4 py-2 text-sm font-semibold text-slate-600 hover:border-slate-300">{{ __('Cancel') }}</button>
            <button type="submit" class="flex-1 rounded-lg bg-brand-600 px-4 py-2 text-sm font-semibold text-white hover:bg-brand-700">{{ __('Save') }}</button>
        </div>
    </form>
</dialog>

<script>
document.querySelectorAll('[data-edit-admin-role]').forEach(btn => {
    btn.addEventListener('click', () => {
        document.getElementById('edit-admin-role-form').action = btn.dataset.updateUrl;
        document.getElementById('edit-admin-role-name').value = btn.dataset.name || '';
        document.getElementById('edit-admin-role-name-ar').value = btn.dataset.nameAr || '';
        document.getElementById('edit-admin-role-slug').value = btn.dataset.slug || '';
        const selected = JSON.parse(btn.dataset.permissions || '[]');
        document.querySelectorAll('#edit-admin-role-permissions input[type="checkbox"]').forEach(cb => {
            cb.checked = selected.includes(cb.value);
        });
        document.getElementById('edit-admin-role-modal').showModal();
    });
});
</script>
@endsection
