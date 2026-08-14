@extends('layouts.app')

@section('title', __('Cost Centers'))

@section('content')
@include('user.accounting.partials.tabs')

<div class="flex items-center justify-between mb-6">
    <div>
        <h2 class="text-lg font-semibold text-slate-900">{{ __('Cost Centers') }}</h2>
        <p class="text-sm text-slate-500 mt-1">{{ __('Tag journal lines by department, project, or branch for internal reporting.') }}</p>
    </div>
    <button type="button" onclick="document.getElementById('add-cc-modal').showModal()" class="rounded-lg bg-brand-600 px-4 py-2 text-sm font-semibold text-white hover:bg-brand-700">+ {{ __('Add Cost Center') }}</button>
</div>

<div class="bg-white rounded-xl border border-slate-100 divide-y divide-slate-50">
    @forelse ($costCenters as $cc)
        <div class="flex items-center justify-between px-5 py-3">
            <div>
                <span class="font-semibold text-slate-900">{{ $cc->code }} {{ $cc->name }}</span>
                @unless ($cc->is_active)<span class="ms-2 rounded-full bg-red-50 px-2 py-0.5 text-[10px] font-semibold text-red-500">{{ __('Inactive') }}</span>@endunless
            </div>
            <div class="flex items-center gap-3 text-xs">
                <button type="button" onclick="openEditCc({{ $cc->id }}, '{{ addslashes($cc->code) }}', '{{ addslashes($cc->name) }}', '{{ addslashes($cc->name_ar) }}', {{ $cc->is_active ? 'true' : 'false' }})" class="text-brand-700 hover:underline">{{ __('Edit') }}</button>
                <form method="POST" action="{{ route('app.cost-centers.destroy', $cc) }}" onsubmit="return confirm('{{ __('Delete this cost center?') }}')">
                    @csrf @method('DELETE')
                    <button type="submit" class="text-red-600 hover:underline">{{ __('Delete') }}</button>
                </form>
            </div>
        </div>
    @empty
        <p class="px-5 py-10 text-center text-sm text-slate-400">{{ __('No cost centers yet.') }}</p>
    @endforelse
</div>

<dialog id="add-cc-modal" class="rounded-2xl border border-slate-100 p-0 w-full max-w-md backdrop:bg-slate-900/40">
    <form method="POST" action="{{ route('app.cost-centers.store') }}" class="p-6 space-y-4">
        @csrf
        <div class="flex items-start justify-between">
            <h3 class="text-lg font-bold text-slate-900">{{ __('Add Cost Center') }}</h3>
            <button type="button" onclick="document.getElementById('add-cc-modal').close()" class="text-slate-400 hover:text-slate-600">✕</button>
        </div>
        <div><label class="block text-xs font-medium text-slate-500">{{ __('Code') }}</label><input type="text" name="code" required class="mt-1 w-full rounded-lg border border-slate-200 text-sm focus:border-brand-500 focus:ring-brand-500"></div>
        <div><label class="block text-xs font-medium text-slate-500">{{ __('Name') }}</label><input type="text" name="name" required class="mt-1 w-full rounded-lg border border-slate-200 text-sm focus:border-brand-500 focus:ring-brand-500"></div>
        <div><label class="block text-xs font-medium text-slate-500">{{ __('Name (Arabic)') }}</label><input type="text" name="name_ar" class="mt-1 w-full rounded-lg border border-slate-200 text-sm focus:border-brand-500 focus:ring-brand-500"></div>
        <button type="submit" class="w-full rounded-lg bg-brand-600 px-4 py-2 text-sm font-semibold text-white hover:bg-brand-700">{{ __('Add Cost Center') }}</button>
    </form>
</dialog>

<dialog id="edit-cc-modal" class="rounded-2xl border border-slate-100 p-0 w-full max-w-md backdrop:bg-slate-900/40">
    <form method="POST" id="edit-cc-form" class="p-6 space-y-4">
        @csrf @method('PUT')
        <div class="flex items-start justify-between">
            <h3 class="text-lg font-bold text-slate-900">{{ __('Edit Cost Center') }}</h3>
            <button type="button" onclick="document.getElementById('edit-cc-modal').close()" class="text-slate-400 hover:text-slate-600">✕</button>
        </div>
        <div><label class="block text-xs font-medium text-slate-500">{{ __('Code') }}</label><input type="text" name="code" id="edit-cc-code" required class="mt-1 w-full rounded-lg border border-slate-200 text-sm focus:border-brand-500 focus:ring-brand-500"></div>
        <div><label class="block text-xs font-medium text-slate-500">{{ __('Name') }}</label><input type="text" name="name" id="edit-cc-name" required class="mt-1 w-full rounded-lg border border-slate-200 text-sm focus:border-brand-500 focus:ring-brand-500"></div>
        <div><label class="block text-xs font-medium text-slate-500">{{ __('Name (Arabic)') }}</label><input type="text" name="name_ar" id="edit-cc-name-ar" class="mt-1 w-full rounded-lg border border-slate-200 text-sm focus:border-brand-500 focus:ring-brand-500"></div>
        <label class="flex items-center gap-2 text-sm text-slate-700">
            <input type="checkbox" name="is_active" value="1" id="edit-cc-active" class="rounded border-slate-300 text-brand-600 focus:ring-brand-500">
            {{ __('Active') }}
        </label>
        <button type="submit" class="w-full rounded-lg bg-brand-600 px-4 py-2 text-sm font-semibold text-white hover:bg-brand-700">{{ __('Save changes') }}</button>
    </form>
</dialog>

<script>
function openEditCc(id, code, name, nameAr, isActive) {
    document.getElementById('edit-cc-form').action = '{{ url('app/accounting/cost-centers') }}/' + id;
    document.getElementById('edit-cc-code').value = code;
    document.getElementById('edit-cc-name').value = name;
    document.getElementById('edit-cc-name-ar').value = nameAr;
    document.getElementById('edit-cc-active').checked = isActive;
    document.getElementById('edit-cc-modal').showModal();
}
</script>
@endsection
