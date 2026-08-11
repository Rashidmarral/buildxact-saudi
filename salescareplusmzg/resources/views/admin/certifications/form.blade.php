@php $editing = $certification->exists; @endphp
<x-admin.layout :title="$editing ? 'Edit Certification' : 'Add Certification'">

    <a href="{{ route('admin.certifications.index') }}" class="text-sm font-medium text-teal-700 hover:underline">&larr; Back to Certifications</a>

    <form method="POST" action="{{ $editing ? route('admin.certifications.update', $certification) : route('admin.certifications.store') }}" class="mt-4 max-w-2xl space-y-5 rounded-xl border border-slate-200 bg-white p-6">
        @csrf
        @if ($editing) @method('PUT') @endif

        <x-admin.field label="Title" name="title" :value="$certification->title" required />
        <x-admin.field label="Issuing Body" name="issuing_body" :value="$certification->issuing_body" />
        <x-admin.field label="Description" name="description" type="textarea" :value="$certification->description" />

        <x-admin.field label="Icon" name="icon" type="select" required>
            <option value="">Select an icon</option>
            @foreach (['badge-check','shield','file-check','check-circle','star','globe'] as $icon)
                <option value="{{ $icon }}" @selected(old('icon', $certification->icon) === $icon)>{{ ucfirst(str_replace('-', ' ', $icon)) }}</option>
            @endforeach
        </x-admin.field>

        <x-admin.field label="Sort Order" name="sort_order" type="number" :value="$certification->sort_order ?? 0" />

        <button type="submit" class="rounded-lg bg-teal-800 px-5 py-2.5 text-sm font-semibold text-white hover:bg-teal-900">
            {{ $editing ? 'Save Changes' : 'Create Certification' }}
        </button>
    </form>

</x-admin.layout>
