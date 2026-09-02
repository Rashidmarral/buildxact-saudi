@extends('layouts.admin')

@section('title', __('Backups'))

@section('content')
<div class="mb-6">
    <a href="{{ route('admin.settings.edit') }}" class="text-sm text-slate-500 hover:text-brand-700">&larr; {{ __('Platform settings') }}</a>
    <h1 class="text-2xl font-bold text-slate-900 mt-1">{{ __('Database Backups') }}</h1>
    <p class="text-sm text-slate-500 mt-1">{{ __('A full database dump runs automatically every day and is kept for the retention window below.') }}</p>
</div>

<div class="grid sm:grid-cols-2 gap-6 mb-6">
    <div class="bg-white rounded-xl border border-slate-100 p-6">
        <h2 class="font-semibold text-slate-900 mb-3">{{ __('Last run') }}</h2>
        @if ($lastRunAt)
            <p class="text-sm text-slate-600">{{ $lastRunAt }}</p>
            <p class="mt-1 text-sm">
                @if ($lastStatus === 'success')
                    <span class="inline-flex items-center gap-1 rounded-full bg-emerald-50 px-2.5 py-1 text-xs font-semibold text-emerald-700">{{ __('Success') }}</span>
                @else
                    <span class="inline-flex items-center gap-1 rounded-full bg-red-50 px-2.5 py-1 text-xs font-semibold text-red-700">{{ __('Failed') }}</span>
                @endif
            </p>
            @if ($lastStatus !== 'success' && $lastError)
                <p class="mt-2 text-xs text-red-600">{{ $lastError }}</p>
            @endif
        @else
            <p class="text-sm text-slate-400">{{ __('No backup has run yet.') }}</p>
        @endif
        <form method="POST" action="{{ route('admin.backups.run') }}" class="mt-4">
            @csrf
            <button type="submit" class="rounded-lg bg-brand-600 px-4 py-2 text-sm font-semibold text-white hover:bg-brand-700">{{ __('Run backup now') }}</button>
        </form>
    </div>

    <div class="bg-white rounded-xl border border-slate-100 p-6">
        <h2 class="font-semibold text-slate-900 mb-3">{{ __('Retention') }}</h2>
        <form method="POST" action="{{ route('admin.backups.retention') }}" class="flex items-end gap-3">
            @csrf
            <div>
                <label class="block text-xs font-semibold uppercase text-slate-500">{{ __('Keep backups for (days)') }}</label>
                <input type="number" name="retention_days" min="1" max="365" value="{{ old('retention_days', $retentionDays) }}" class="mt-1 w-32 rounded-lg border border-slate-200 focus:border-brand-500 focus:ring-brand-500">
            </div>
            <button type="submit" class="rounded-lg border border-slate-200 px-4 py-2 text-sm font-semibold text-slate-600 hover:border-slate-300">{{ __('Save') }}</button>
        </form>
        <p class="text-xs text-slate-400 mt-3">{{ __('Backups older than this are deleted automatically the next time the daily backup runs.') }}</p>
    </div>
</div>

<div class="bg-white rounded-xl border border-slate-100">
    @if ($files->isEmpty())
        <p class="px-6 py-8 text-sm text-slate-500">{{ __('No backups yet.') }}</p>
    @else
        <table class="w-full text-sm">
            <thead>
                <tr class="text-left text-slate-500 border-b border-slate-100">
                    <th class="px-6 py-3 font-medium">{{ __('File') }}</th>
                    <th class="px-6 py-3 font-medium">{{ __('Size') }}</th>
                    <th class="px-6 py-3 font-medium">{{ __('Created') }}</th>
                    <th class="px-6 py-3"></th>
                </tr>
            </thead>
            <tbody>
                @foreach ($files as $file)
                    <tr class="border-b border-slate-50 last:border-0">
                        <td class="px-6 py-3 font-mono text-xs text-slate-700">{{ $file['name'] }}</td>
                        <td class="px-6 py-3 text-slate-500">{{ number_format($file['size'] / 1024, 1) }} KB</td>
                        <td class="px-6 py-3 text-slate-500">{{ \Illuminate\Support\Carbon::createFromTimestamp($file['modified_at'])->format('Y-m-d H:i') }}</td>
                        <td class="px-6 py-3 text-right space-x-3 rtl:space-x-reverse">
                            <a href="{{ route('admin.backups.download', $file['name']) }}" class="text-brand-700 hover:underline">{{ __('Download') }}</a>
                            <form method="POST" action="{{ route('admin.backups.destroy', $file['name']) }}" class="inline" onsubmit="return confirm('{{ __('Delete this backup?') }}')">
                                @csrf @method('DELETE')
                                <button type="submit" class="text-red-600 hover:underline">{{ __('Delete') }}</button>
                            </form>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif
</div>
@endsection
