@php $editing = $teamMember->exists; @endphp
<x-admin.layout :title="$editing ? 'Edit Team Member' : 'Add Team Member'">

    <a href="{{ route('admin.team-members.index') }}" class="text-sm font-medium text-teal-700 hover:underline">&larr; Back to Team Members</a>

    <form method="POST" action="{{ $editing ? route('admin.team-members.update', $teamMember) : route('admin.team-members.store') }}" enctype="multipart/form-data" class="mt-4 max-w-2xl space-y-5 rounded-xl border border-slate-200 bg-white p-6">
        @csrf
        @if ($editing) @method('PUT') @endif

        <div class="grid gap-5 sm:grid-cols-2">
            <x-admin.field label="Name" name="name" :value="$teamMember->name" required />
            <x-admin.field label="Designation" name="designation" :value="$teamMember->designation" required />
        </div>

        <x-admin.field label="Bio" name="bio" type="textarea" :value="$teamMember->bio" />

        <div class="grid gap-5 sm:grid-cols-2">
            <x-admin.field label="Initials (photo fallback)" name="initials" :value="$teamMember->initials" required hint="e.g. AR" />
            <x-admin.field label="Sort Order" name="sort_order" type="number" :value="$teamMember->sort_order ?? 0" />
        </div>

        <div>
            <x-admin.field label="Photo" name="photo" type="file" accept="image/*" />
            @if ($teamMember->photo_path)
                <img src="{{ asset('storage/'.$teamMember->photo_path) }}" class="mt-2 h-16 w-16 rounded-full border border-slate-200 object-cover" alt="">
            @endif
        </div>

        <button type="submit" class="rounded-lg bg-teal-800 px-5 py-2.5 text-sm font-semibold text-white hover:bg-teal-900">
            {{ $editing ? 'Save Changes' : 'Create Team Member' }}
        </button>
    </form>

</x-admin.layout>
