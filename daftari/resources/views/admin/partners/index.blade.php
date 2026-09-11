@extends('layouts.admin')

@section('title', __('Partner Program'))

@section('content')
<div class="grid grid-cols-2 gap-4 sm:gap-5 lg:grid-cols-4">
    @foreach ([
        ['label' => __('Pending applications'), 'value' => number_format($stats['pending']), 'icon' => 'clock', 'accent' => 'from-amber-500 to-orange-500'],
        ['label' => __('Active partners'), 'value' => number_format($stats['active']), 'icon' => 'team', 'accent' => 'from-emerald-500 to-teal-500'],
        ['label' => __('Unpaid approved commission'), 'value' => \App\Support\Money::format($stats['unpaid_commission']), 'icon' => 'billing', 'accent' => 'from-sky-500 to-blue-500'],
        ['label' => __('Payout requests'), 'value' => number_format($stats['payout_requests']), 'icon' => 'alert', 'accent' => 'from-violet-500 to-purple-500'],
    ] as $card)
        <div class="card-hover rounded-2xl border border-slate-100 bg-white p-5 shadow-card">
            <div class="flex items-center justify-between">
                <span class="text-xs font-medium text-slate-500">{{ $card['label'] }}</span>
                <span class="inline-flex h-8 w-8 items-center justify-center rounded-lg bg-gradient-to-br {{ $card['accent'] }} text-white">
                    @include('partials.icon', ['name' => $card['icon'], 'class' => 'h-4 w-4'])
                </span>
            </div>
            <div class="mt-3 text-2xl font-bold text-slate-900">{{ $card['value'] }}</div>
        </div>
    @endforeach
</div>

<div class="mt-6 mb-4 flex items-center justify-between">
    <p class="text-sm text-slate-500">{{ __('Applications, active partners, and referral commissions.') }}</p>
    <a href="{{ route('admin.partner-types.index') }}" class="text-sm font-semibold text-brand-700 hover:underline">{{ __('Manage partner types') }} →</a>
</div>

<form method="GET" class="mb-6 bg-white rounded-xl border border-slate-100 p-4 space-y-3">
    <div class="flex flex-wrap items-end gap-3">
        <div>
            <label class="block text-xs font-medium text-slate-500 mb-1">{{ __('Search') }}</label>
            <input type="text" name="q" value="{{ request('q') }}" placeholder="{{ __('Name or email...') }}" class="rounded-lg border border-slate-200 text-sm">
        </div>
        <div>
            <label class="block text-xs font-medium text-slate-500 mb-1">{{ __('Status') }}</label>
            <select name="status" class="rounded-lg border border-slate-200 text-sm">
                <option value="">{{ __('All statuses') }}</option>
                @foreach (\App\Models\Partner::STATUSES as $value)
                    <option value="{{ $value }}" @selected(request('status') === $value)>{{ (new \App\Models\Partner(['status' => $value]))->statusLabel() }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label class="block text-xs font-medium text-slate-500 mb-1">{{ __('Partner type') }}</label>
            <select name="partner_type_id" class="rounded-lg border border-slate-200 text-sm">
                <option value="">{{ __('All types') }}</option>
                @foreach ($partnerTypes as $type)
                    <option value="{{ $type->id }}" @selected((string) request('partner_type_id') === (string) $type->id)>{{ $type->name() }}</option>
                @endforeach
            </select>
        </div>
        <button type="submit" class="rounded-lg bg-brand-600 px-4 py-2 text-sm font-semibold text-white hover:bg-brand-700">{{ __('Filter') }}</button>
        @if (request()->anyFilled(['q', 'status', 'partner_type_id']))
            <a href="{{ route('admin.partners.index') }}" class="text-sm font-semibold text-slate-500 hover:underline">{{ __('Clear') }}</a>
        @endif
    </div>
</form>

<div class="bg-white rounded-xl border border-slate-100 overflow-x-auto">
    <table class="w-full text-sm">
        <thead>
            <tr class="text-left text-slate-500 border-b border-slate-100 whitespace-nowrap">
                <th class="px-6 py-3 font-medium">{{ __('Name') }}</th>
                <th class="px-4 py-3 font-medium">{{ __('Type') }}</th>
                <th class="px-4 py-3 font-medium">{{ __('Status') }}</th>
                <th class="px-4 py-3 font-medium">{{ __('Applied') }}</th>
                <th class="px-6 py-3"></th>
            </tr>
        </thead>
        <tbody>
            @forelse ($partners as $partner)
                <tr class="border-b border-slate-50 last:border-0 hover:bg-slate-50">
                    <td class="px-6 py-3">
                        <div class="font-medium text-slate-800">{{ $partner->name }}</div>
                        <div class="text-xs text-slate-400">{{ $partner->email }}</div>
                    </td>
                    <td class="px-4 py-3">{{ $partner->partnerType?->name() ?? '—' }}</td>
                    <td class="px-4 py-3"><span class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-semibold {{ $partner->statusBadgeClasses() }}">{{ $partner->statusLabel() }}</span></td>
                    <td class="px-4 py-3 text-slate-500">{{ $partner->created_at->diffForHumans() }}</td>
                    <td class="px-6 py-3 text-right">
                        <a href="{{ route('admin.partners.show', $partner) }}" class="text-brand-700 hover:underline">{{ __('View') }}</a>
                    </td>
                </tr>
            @empty
                <tr><td colspan="5" class="px-6 py-8 text-center text-slate-400">{{ __('No partners match these filters.') }}</td></tr>
            @endforelse
        </tbody>
    </table>
</div>
<div class="mt-4">{{ $partners->links() }}</div>
@endsection
