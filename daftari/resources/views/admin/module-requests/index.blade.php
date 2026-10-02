@extends('layouts.admin')

@section('title', __('Module requests'))

@section('content')
<div class="flex items-center justify-between mb-6">
    <div>
        <h1 class="text-2xl font-bold text-slate-900">{{ __('Module requests') }}</h1>
        <p class="text-sm text-slate-500 mt-1">{{ __('Companies requesting a paid module (Payroll, POS, Restaurant Management, ...) from their Modules page. Approving one installs it for that company only.') }}</p>
    </div>
</div>

@if (session('status'))
    <div class="mb-4 rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-700">{{ session('status') }}</div>
@endif
@if ($errors->any())
    <div class="mb-4 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">{{ $errors->first() }}</div>
@endif

<div class="flex gap-2 mb-4">
    @foreach (['requested' => __('Pending'), 'approved' => __('Approved'), 'rejected' => __('Rejected'), 'all' => __('All')] as $value => $label)
        <a href="{{ route('admin.module-requests.index', ['status' => $value]) }}" class="rounded-lg px-3 py-1.5 text-sm font-semibold {{ $status === $value ? 'bg-brand-600 text-white' : 'bg-white border border-slate-200 text-slate-600 hover:border-slate-300' }}">{{ $label }}</a>
    @endforeach
</div>

<div class="bg-white rounded-xl border border-slate-100">
    @if ($requests->isEmpty())
        <p class="px-6 py-8 text-sm text-slate-500">{{ __('No requests here.') }}</p>
    @else
        <table class="w-full text-sm">
            <thead>
                <tr class="text-left text-slate-500 border-b border-slate-100">
                    <th class="px-6 py-3 font-medium">{{ __('Company') }}</th>
                    <th class="px-6 py-3 font-medium">{{ __('Module') }}</th>
                    <th class="px-6 py-3 font-medium">{{ __('Requested by') }}</th>
                    <th class="px-6 py-3 font-medium">{{ __('Note') }}</th>
                    <th class="px-6 py-3 font-medium">{{ __('Status') }}</th>
                    <th class="px-6 py-3"></th>
                </tr>
            </thead>
            <tbody>
                @foreach ($requests as $moduleRequest)
                    <tr class="border-b border-slate-50 last:border-0 hover:bg-slate-50">
                        <td class="px-6 py-3 font-medium text-slate-800">
                            <a href="{{ route('admin.companies.show', $moduleRequest->company) }}" class="hover:underline">{{ $moduleRequest->company->name }}</a>
                        </td>
                        <td class="px-6 py-3 text-slate-600">{{ $moduleRequest->moduleLabel() }}</td>
                        <td class="px-6 py-3 text-slate-500">{{ $moduleRequest->requester->name ?? '—' }}</td>
                        <td class="px-6 py-3 text-slate-500 max-w-xs truncate">{{ $moduleRequest->note ?: '—' }}</td>
                        <td class="px-6 py-3">
                            <span class="rounded-full px-2.5 py-0.5 text-xs font-semibold {{ $moduleRequest->statusBadgeClasses() }}">{{ $moduleRequest->statusLabel() }}</span>
                        </td>
                        <td class="px-6 py-3 text-end">
                            @if ($moduleRequest->status === 'requested')
                                <div class="flex items-center justify-end gap-3">
                                    <form method="POST" action="{{ route('admin.module-requests.approve', $moduleRequest) }}">
                                        @csrf
                                        <button type="submit" class="text-emerald-700 hover:underline">{{ __('Approve') }}</button>
                                    </form>
                                    <button type="button" onclick="document.getElementById('reject-modal-{{ $moduleRequest->id }}').showModal()" class="text-red-600 hover:underline">{{ __('Reject') }}</button>
                                </div>

                                <dialog id="reject-modal-{{ $moduleRequest->id }}" class="rounded-2xl border border-slate-100 p-0 w-full max-w-sm backdrop:bg-slate-900/40">
                                    <form method="POST" action="{{ route('admin.module-requests.reject', $moduleRequest) }}" class="p-6 space-y-4 text-start">
                                        @csrf
                                        <h3 class="text-lg font-bold text-slate-900">{{ __('Reject request') }}</h3>
                                        <div>
                                            <label class="block text-xs font-semibold uppercase text-slate-500">{{ __('Note (optional, visible to the company)') }}</label>
                                            <textarea name="admin_note" rows="3" class="mt-1 w-full rounded-lg border border-slate-200 text-sm focus:border-brand-500 focus:ring-brand-500"></textarea>
                                        </div>
                                        <div class="flex gap-3">
                                            <button type="button" onclick="document.getElementById('reject-modal-{{ $moduleRequest->id }}').close()" class="flex-1 rounded-lg border border-slate-200 px-4 py-2 text-sm font-semibold text-slate-600">{{ __('Cancel') }}</button>
                                            <button type="submit" class="flex-1 rounded-lg bg-red-600 px-4 py-2 text-sm font-semibold text-white hover:bg-red-700">{{ __('Reject') }}</button>
                                        </div>
                                    </form>
                                </dialog>
                            @else
                                <span class="text-xs text-slate-400">{{ $moduleRequest->reviewer->name ?? '—' }}</span>
                            @endif
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
        <div class="px-6 py-4">{{ $requests->links() }}</div>
    @endif
</div>
@endsection
