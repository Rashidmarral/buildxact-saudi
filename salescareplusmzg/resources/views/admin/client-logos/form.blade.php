@php $editing = $clientLogo->exists; @endphp
<x-admin.layout :title="$editing ? 'Edit Client' : 'Add Client'">

    <a href="{{ route('admin.client-logos.index') }}" class="text-sm font-medium text-teal-700 hover:underline">&larr; Back to Our Clients</a>

    <form method="POST" action="{{ $editing ? route('admin.client-logos.update', $clientLogo) : route('admin.client-logos.store') }}" enctype="multipart/form-data" class="mt-4 max-w-xl space-y-5 rounded-xl border border-slate-200 bg-white p-6">
        @csrf
        @if ($editing) @method('PUT') @endif

        <x-admin.field label="Client / Company Name" name="name" :value="$clientLogo->name" required />
        <x-admin.field label="Website URL" name="website_url" :value="$clientLogo->website_url" hint="Optional — makes the logo clickable." />

        <div>
            <x-admin.field label="Logo" name="logo" type="file" accept="image/*" hint="Transparent PNG recommended." />
            @if ($clientLogo->logo_path)
                <img src="{{ asset('storage/'.$clientLogo->logo_path) }}" class="mt-2 h-16 w-auto rounded-lg border border-slate-200 bg-slate-50 object-contain p-2" alt="">
            @endif
        </div>

        <div class="grid gap-5 sm:grid-cols-2">
            <x-admin.field label="Sort Order" name="sort_order" type="number" :value="$clientLogo->sort_order ?? 0" />
            <div class="pt-6">
                <x-admin.field label="" name="is_visible" type="checkbox" :value="$clientLogo->exists ? $clientLogo->is_visible : true" hint="Visible on homepage" />
            </div>
        </div>

        <button type="submit" class="rounded-lg bg-teal-800 px-5 py-2.5 text-sm font-semibold text-white hover:bg-teal-900">
            {{ $editing ? 'Save Changes' : 'Add Client' }}
        </button>
    </form>

</x-admin.layout>
