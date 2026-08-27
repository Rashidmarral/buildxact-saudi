<x-admin.layout title="Our Clients">

    <div class="mb-6 flex items-center justify-between">
        <div>
            <h2 class="text-2xl font-bold text-slate-900">Our Clients</h2>
            <p class="mt-1 text-sm text-slate-500">Client/partner logos shown as a trust bar on the homepage.</p>
        </div>
        <a href="{{ route('admin.client-logos.create') }}" class="rounded-lg bg-teal-800 px-4 py-2 text-sm font-semibold text-white hover:bg-teal-900">+ Add Client</a>
    </div>

    <div class="grid grid-cols-2 gap-4 sm:grid-cols-3 lg:grid-cols-5">
        @forelse ($clientLogos as $client)
            <div class="overflow-hidden rounded-xl border border-slate-200 bg-white">
                <div class="flex aspect-[3/2] items-center justify-center bg-slate-50 p-4">
                    @if ($client->logo_path)
                        <img src="{{ asset('storage/'.$client->logo_path) }}" alt="{{ $client->name }}" class="max-h-full max-w-full object-contain">
                    @else
                        <x-icon name="building" class="h-8 w-8 text-slate-300" />
                    @endif
                </div>
                <div class="p-3">
                    <p class="truncate text-sm font-semibold text-slate-800">{{ $client->name }}</p>
                    <p class="mt-0.5 text-xs {{ $client->is_visible ? 'text-emerald-600' : 'text-slate-400' }}">{{ $client->is_visible ? 'Visible' : 'Hidden' }}</p>
                    <div class="mt-2 flex items-center justify-between text-xs">
                        <a href="{{ route('admin.client-logos.edit', $client) }}" class="font-medium text-teal-700 hover:underline">Edit</a>
                        <form action="{{ route('admin.client-logos.destroy', $client) }}" method="POST" onsubmit="return confirm('Delete this client?')">
                            @csrf @method('DELETE')
                            <button type="submit" class="font-medium text-red-600 hover:underline">Delete</button>
                        </form>
                    </div>
                </div>
            </div>
        @empty
            <p class="col-span-full rounded-xl border border-dashed border-slate-300 px-4 py-10 text-center text-sm text-slate-400">No clients yet.</p>
        @endforelse
    </div>

</x-admin.layout>
