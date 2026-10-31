@extends('layouts.admin')

@section('title', __('Partner Types'))

@section('content')
<div class="flex items-center justify-between mb-6">
    <p class="text-sm text-slate-500">{{ __('Categories of partner and their commission rule. Nothing here is pre-set — define every rate before inviting partners.') }}</p>
    <a href="{{ route('admin.partner-types.create') }}" class="rounded-lg bg-brand-600 px-4 py-2 text-sm font-semibold text-white hover:bg-brand-700">{{ __('+ New partner type') }}</a>
</div>

<div class="bg-white rounded-xl border border-slate-100 overflow-x-auto">
    <table class="w-full text-sm">
        <thead>
            <tr class="text-left text-slate-500 border-b border-slate-100">
                <th class="px-6 py-3 font-medium">{{ __('Name') }}</th>
                <th class="px-6 py-3 font-medium">{{ __('Commission') }}</th>
                <th class="px-6 py-3 font-medium">{{ __('Status') }}</th>
                <th class="px-6 py-3"></th>
            </tr>
        </thead>
        <tbody>
            @forelse ($partnerTypes as $partnerType)
                <tr class="border-b border-slate-50 last:border-0">
                    <td class="px-6 py-3 font-medium text-slate-800">{{ $partnerType->name() }}</td>
                    <td class="px-6 py-3 text-slate-600">{{ $partnerType->commissionLabel() }}</td>
                    <td class="px-6 py-3">
                        @if ($partnerType->is_active)
                            <span class="rounded-full bg-emerald-50 px-2.5 py-1 text-xs font-semibold text-emerald-700">{{ __('Active') }}</span>
                        @else
                            <span class="rounded-full bg-slate-100 px-2.5 py-1 text-xs font-semibold text-slate-500">{{ __('Inactive') }}</span>
                        @endif
                    </td>
                    <td class="px-6 py-3 text-right space-x-3 rtl:space-x-reverse">
                        <a href="{{ route('admin.partner-types.edit', $partnerType) }}" class="text-brand-700 hover:underline">{{ __('Edit') }}</a>
                        <form method="POST" action="{{ route('admin.partner-types.destroy', $partnerType) }}" class="inline" onsubmit="return confirm('{{ __('Delete this partner type?') }}')">
                            @csrf @method('DELETE')
                            <button type="submit" class="text-red-600 hover:underline">{{ __('Delete') }}</button>
                        </form>
                    </td>
                </tr>
            @empty
                <tr><td colspan="4" class="px-6 py-8 text-center text-slate-400">{{ __('No partner types defined yet.') }}</td></tr>
            @endforelse
        </tbody>
    </table>
</div>
@endsection
