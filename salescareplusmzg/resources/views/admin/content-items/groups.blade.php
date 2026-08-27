<x-admin.layout title="Page Content">

    <div class="mb-6">
        <h2 class="text-2xl font-bold text-slate-900">Page Content</h2>
        <p class="mt-1 text-sm text-slate-500">The repeatable card/list content on Services, Careers, Quality and About — fully editable, no code needed.</p>
    </div>

    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
        @foreach ($groups as $group => $meta)
            <a href="{{ route('admin.content-items.index', $group) }}" class="rounded-xl border border-slate-200 bg-white p-5 transition hover:border-teal-300 hover:shadow-sm">
                <p class="font-semibold text-slate-900">{{ $meta['label'] }}</p>
                <p class="mt-1 text-xs text-slate-400">{{ $meta['page'] }}</p>
                <p class="mt-3 text-2xl font-bold text-teal-800">{{ $counts[$group] ?? 0 }}</p>
                <p class="text-xs text-slate-400">item{{ ($counts[$group] ?? 0) === 1 ? '' : 's' }}</p>
            </a>
        @endforeach
    </div>

</x-admin.layout>
