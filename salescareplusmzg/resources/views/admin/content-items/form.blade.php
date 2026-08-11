@php $editing = $item->exists; @endphp
<x-admin.layout :title="($editing ? 'Edit' : 'Add').' — '.$meta['label']">

    <a href="{{ route('admin.content-items.index', $group) }}" class="text-sm font-medium text-teal-700 hover:underline">&larr; Back to {{ $meta['label'] }}</a>

    <form method="POST" action="{{ $editing ? route('admin.content-items.update', [$group, $item]) : route('admin.content-items.store', $group) }}" class="mt-4 max-w-xl space-y-5 rounded-xl border border-slate-200 bg-white p-6">
        @csrf
        @if ($editing) @method('PUT') @endif

        @if ($meta['icon_mode'] === 'icon')
            <x-admin.field label="Icon" name="icon" type="select">
                <option value="">Select an icon</option>
                @foreach (['pill','droplet','thermometer','shield','heart','leaf','sprout','sun','wind','warehouse','truck','file-check','badge-check','globe','headset','users','clock','building','check-circle','quote','star'] as $icon)
                    <option value="{{ $icon }}" @selected(old('icon', $item->icon) === $icon)>{{ ucfirst(str_replace('-', ' ', $icon)) }}</option>
                @endforeach
            </x-admin.field>
        @elseif ($meta['icon_mode'] === 'badge')
            <x-admin.field label="Badge / Step Number" name="icon" :value="$item->icon" hint='e.g. "01"' />
        @endif

        <x-admin.field label="Title" name="title" :value="$item->title" required />

        @if ($meta['subtitle_label'])
            <x-admin.field :label="$meta['subtitle_label']" name="subtitle" :value="$item->subtitle" />
        @endif

        <x-admin.field label="Description" name="description" type="textarea" rows="4" :value="$item->description" />

        <div class="grid gap-5 sm:grid-cols-2">
            <x-admin.field label="Sort Order" name="sort_order" type="number" :value="$item->sort_order ?? 0" />
            <div class="pt-6">
                <x-admin.field label="" name="is_visible" type="checkbox" :value="$item->exists ? $item->is_visible : true" hint="Visible" />
            </div>
        </div>

        <button type="submit" class="rounded-lg bg-teal-800 px-5 py-2.5 text-sm font-semibold text-white hover:bg-teal-900">
            {{ $editing ? 'Save Changes' : 'Add Item' }}
        </button>
    </form>

</x-admin.layout>
