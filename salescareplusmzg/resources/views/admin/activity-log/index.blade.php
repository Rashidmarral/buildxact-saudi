<x-admin.layout title="Activity Log">

    <div class="mb-6">
        <h2 class="text-2xl font-bold text-slate-900">Activity Log</h2>
        <p class="mt-1 text-sm text-slate-500">Every content change made from the admin panel — who, what, and when.</p>
    </div>

    <div class="overflow-x-auto rounded-xl border border-slate-200 bg-white">
        <table class="w-full min-w-[720px] text-left text-sm">
            <thead class="bg-slate-50 text-xs uppercase text-slate-500">
                <tr>
                    <th class="px-4 py-3">When</th>
                    <th class="px-4 py-3">Admin</th>
                    <th class="px-4 py-3">Action</th>
                    <th class="px-4 py-3">Item</th>
                    <th class="px-4 py-3">Changes</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse ($logs as $log)
                    <tr>
                        <td class="px-4 py-3 whitespace-nowrap text-slate-500">{{ $log->created_at->format('M j, Y g:ia') }}</td>
                        <td class="px-4 py-3 font-medium text-slate-800">{{ $log->user_name ?? 'Unknown' }}</td>
                        <td class="px-4 py-3">
                            <span @class([
                                'rounded-full px-2 py-0.5 text-xs font-semibold',
                                'bg-emerald-50 text-emerald-700' => $log->action === 'created',
                                'bg-teal-50 text-teal-700' => $log->action === 'updated',
                                'bg-red-50 text-red-700' => $log->action === 'deleted',
                            ])>{{ ucfirst($log->action) }}</span>
                        </td>
                        <td class="px-4 py-3 text-slate-700">
                            {{ $log->subject_type }}
                            <span class="text-slate-400">&mdash; {{ $log->subject_label }}</span>
                        </td>
                        <td class="px-4 py-3 text-xs text-slate-500">
                            @if ($log->changes)
                                <ul class="space-y-0.5">
                                    @foreach ($log->changes as $field => $value)
                                        <li><span class="font-medium text-slate-600">{{ $field }}:</span> {{ $value }}</li>
                                    @endforeach
                                </ul>
                            @else
                                &mdash;
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="px-4 py-10 text-center text-slate-400">No activity recorded yet.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">{{ $logs->links() }}</div>

</x-admin.layout>
