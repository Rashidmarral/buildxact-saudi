@php
    $editing = $navItem->exists;
    $linkType = old('link_type', $navItem->url ? 'url' : ($navItem->page_id ? 'page' : ($navItem->route_name ? 'route' : 'route')));
@endphp
<x-admin.layout :title="$editing ? 'Edit Navigation Link' : 'Add Navigation Link'">

    <a href="{{ route('admin.nav-items.index') }}" class="text-sm font-medium text-teal-700 hover:underline">&larr; Back to Navigation</a>

    <form method="POST" action="{{ $editing ? route('admin.nav-items.update', $navItem) : route('admin.nav-items.store') }}" class="mt-4 max-w-2xl space-y-5 rounded-xl border border-slate-200 bg-white p-6">
        @csrf
        @if ($editing) @method('PUT') @endif

        <div class="grid gap-5 sm:grid-cols-2">
            <x-admin.field label="Label" name="label" :value="$navItem->label" required />

            <x-admin.field label="Menu Location" name="location" type="select" required>
                @foreach (\App\Http\Controllers\Admin\NavItemController::LOCATIONS as $value => $locationLabel)
                    <option value="{{ $value }}" @selected(old('location', $navItem->location) === $value)>{{ $locationLabel }}</option>
                @endforeach
            </x-admin.field>
        </div>

        <div>
            <label class="block text-sm font-medium text-slate-700">Link Target</label>
            <div class="mt-1.5 flex gap-4 text-sm">
                <label class="flex items-center gap-1.5"><input type="radio" name="link_type" value="route" onchange="document.querySelectorAll('[data-link-target]').forEach(el => el.classList.add('hidden')); document.querySelector('[data-link-target=route]').classList.remove('hidden')" @checked($linkType === 'route')> Built-in Page</label>
                <label class="flex items-center gap-1.5"><input type="radio" name="link_type" value="page" onchange="document.querySelectorAll('[data-link-target]').forEach(el => el.classList.add('hidden')); document.querySelector('[data-link-target=page]').classList.remove('hidden')" @checked($linkType === 'page')> Custom Page</label>
                <label class="flex items-center gap-1.5"><input type="radio" name="link_type" value="url" onchange="document.querySelectorAll('[data-link-target]').forEach(el => el.classList.add('hidden')); document.querySelector('[data-link-target=url]').classList.remove('hidden')" @checked($linkType === 'url')> External URL</label>
            </div>
        </div>

        <div data-link-target="route" class="{{ $linkType === 'route' ? '' : 'hidden' }}">
            <x-admin.field label="Built-in Page" name="route_name" type="select">
                <option value="">Select a page</option>
                @foreach (\App\Http\Controllers\Admin\NavItemController::ROUTES as $value => $routeLabel)
                    <option value="{{ $value }}" @selected(old('route_name', $navItem->route_name) === $value)>{{ $routeLabel }}</option>
                @endforeach
            </x-admin.field>
        </div>

        <div data-link-target="page" class="{{ $linkType === 'page' ? '' : 'hidden' }}">
            <x-admin.field label="Custom Page" name="page_id" type="select">
                <option value="">Select a page</option>
                @foreach ($pages as $page)
                    <option value="{{ $page->id }}" @selected(old('page_id', $navItem->page_id) == $page->id)>{{ $page->title }}</option>
                @endforeach
            </x-admin.field>
            <p class="mt-1 text-xs text-slate-400">Don't see your page? <a href="{{ route('admin.pages.create') }}" class="text-teal-700 underline">Create it first</a>.</p>
        </div>

        <div data-link-target="url" class="{{ $linkType === 'url' ? '' : 'hidden' }}">
            <x-admin.field label="External URL" name="url" :value="$navItem->url" hint="e.g. https://wa.me/923001234567" />
        </div>

        <div class="grid gap-5 sm:grid-cols-2">
            <x-admin.field label="Sort Order" name="sort_order" type="number" :value="$navItem->sort_order ?? 0" />
            <div class="flex flex-col gap-3 pt-6">
                <x-admin.field label="" name="is_visible" type="checkbox" :value="$navItem->exists ? $navItem->is_visible : true" hint="Visible in menu" />
                <x-admin.field label="" name="open_in_new_tab" type="checkbox" :value="$navItem->open_in_new_tab" hint="Open in new tab" />
            </div>
        </div>

        <button type="submit" class="rounded-lg bg-teal-800 px-5 py-2.5 text-sm font-semibold text-white hover:bg-teal-900">
            {{ $editing ? 'Save Changes' : 'Create Link' }}
        </button>
    </form>

</x-admin.layout>
