@php $editing = $principal->exists; @endphp
<x-admin.layout :title="$editing ? 'Edit Principal' : 'Add Principal'">

    <a href="{{ route('admin.principals.index') }}" class="text-sm font-medium text-teal-700 hover:underline">&larr; Back to Principals</a>

    <form method="POST" action="{{ $editing ? route('admin.principals.update', $principal) : route('admin.principals.store') }}" enctype="multipart/form-data" class="mt-4 max-w-2xl space-y-5 rounded-xl border border-slate-200 bg-white p-6">
        @csrf
        @if ($editing) @method('PUT') @endif

        <div class="grid gap-5 sm:grid-cols-2">
            <x-admin.field label="Company Name" name="name" :value="$principal->name" required />
            <x-admin.field label="Initials (logo fallback)" name="initials" :value="$principal->initials" required hint="e.g. AN" />
        </div>

        <x-admin.field label="Tagline" name="tagline" :value="$principal->tagline" required />
        <x-admin.field label="Description" name="description" type="textarea" :value="$principal->description" />

        <div>
            <x-admin.field label="Logo" name="logo" type="file" accept="image/*" />
            @if ($principal->logo_path)
                <img src="{{ asset('storage/'.$principal->logo_path) }}" class="mt-2 h-16 w-16 rounded-lg border border-slate-200 object-cover" alt="">
            @endif
        </div>

        <div class="grid gap-5 sm:grid-cols-3">
            <x-admin.field label="Years of Partnership" name="years_partnership" type="number" :value="$principal->years_partnership ?? 0" required />
            <x-admin.field label="Products Count" name="products_count" type="number" :value="$principal->products_count ?? 0" required />
            <x-admin.field label="Sort Order" name="sort_order" type="number" :value="$principal->sort_order ?? 0" />
        </div>

        <button type="submit" class="rounded-lg bg-teal-800 px-5 py-2.5 text-sm font-semibold text-white hover:bg-teal-900">
            {{ $editing ? 'Save Changes' : 'Create Principal' }}
        </button>
    </form>

</x-admin.layout>
