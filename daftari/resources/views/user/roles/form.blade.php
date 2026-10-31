@extends('layouts.app')

@section('title', $role->exists ? __('Edit Role') : __('Create Custom Role'))

@section('content')
<form method="POST" action="{{ $role->exists ? route('app.roles.update', $role) : route('app.roles.store') }}" class="max-w-2xl space-y-6">
    @csrf
    @if ($role->exists) @method('PUT') @endif

    <div class="bg-white rounded-xl border border-slate-100 p-6">
        <label class="block text-sm font-medium text-slate-700">{{ __('Role name') }}</label>
        <input type="text" name="name" value="{{ old('name', $role->name) }}" required class="mt-1 w-full max-w-sm rounded-lg border border-slate-200 focus:border-brand-500 focus:ring-brand-500">
    </div>

    <div class="bg-white rounded-xl border border-slate-100 p-6">
        <div class="flex items-center justify-between mb-4">
            <h2 class="font-semibold text-slate-900">{{ __('Permissions') }}</h2>
            <div class="flex gap-3 text-xs">
                <button type="button" id="select-all" class="font-semibold text-brand-700 hover:underline">{{ __('Select all') }}</button>
                <button type="button" id="clear-all" class="font-semibold text-slate-500 hover:underline">{{ __('Clear all') }}</button>
            </div>
        </div>
        @php $selected = old('permissions', $role->permissions ?? []); @endphp
        <div class="grid sm:grid-cols-2 gap-2">
            @foreach ($catalog as $key => $label)
                <label class="flex items-start gap-2 rounded-lg border border-slate-200 px-3 py-2 text-sm">
                    <input type="checkbox" name="permissions[]" value="{{ $key }}" class="perm-checkbox rounded border-slate-300 text-brand-600 focus:ring-brand-500 mt-0.5" @checked(in_array($key, $selected))>
                    <span>
                        {{ $label }}
                        @if ($key === 'members_roles')
                            <span class="block text-xs text-amber-600 mt-0.5">{{ __('High trust: lets someone invite a new account owner. Grant only to people you\'d trust with full company access.') }}</span>
                        @endif
                    </span>
                </label>
            @endforeach
        </div>
    </div>

    <div class="flex gap-3">
        <button type="submit" class="rounded-lg bg-brand-600 px-6 py-2.5 font-semibold text-white hover:bg-brand-700">{{ __('Save role') }}</button>
        <a href="{{ route('app.roles.index') }}" class="rounded-lg border border-slate-200 px-6 py-2.5 font-semibold text-slate-600 hover:border-slate-300">{{ __('Cancel') }}</a>
    </div>
</form>

<script>
document.getElementById('select-all').addEventListener('click', () => {
    document.querySelectorAll('.perm-checkbox').forEach(cb => cb.checked = true);
});
document.getElementById('clear-all').addEventListener('click', () => {
    document.querySelectorAll('.perm-checkbox').forEach(cb => cb.checked = false);
});
</script>
@endsection
