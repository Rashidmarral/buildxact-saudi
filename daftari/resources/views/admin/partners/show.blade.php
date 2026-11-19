@extends('layouts.admin')

@section('title', $partner->name)

@section('content')
<div class="mb-4">
    <a href="{{ route('admin.partners.index') }}" class="text-sm font-semibold text-slate-500 hover:underline">← {{ __('Back to partners') }}</a>
</div>

<div class="flex flex-wrap items-start justify-between gap-3 mb-6">
    <div>
        <h2 class="text-lg font-semibold text-slate-900">{{ $partner->name }}</h2>
        <p class="text-sm text-slate-500">
            {{ $partner->email }}
            @if ($partner->phone) · {{ $partner->phone }} @endif
            @if ($partner->company_name) · {{ $partner->company_name }} @endif
            · {{ __('Applied') }} {{ $partner->created_at->format('Y-m-d H:i') }}
        </p>
    </div>
    <span class="inline-flex items-center rounded-full px-3 py-1 text-sm font-semibold {{ $partner->statusBadgeClasses() }}">{{ $partner->statusLabel() }}</span>
</div>

<div class="grid lg:grid-cols-3 gap-6">
    <div class="lg:col-span-2 space-y-4">
        @if ($partner->message)
            <div class="bg-white rounded-xl border border-slate-100 p-5">
                <div class="text-sm font-semibold text-slate-800 mb-2">{{ __('Application message') }}</div>
                <p class="text-sm text-slate-700 whitespace-pre-line">{{ $partner->message }}</p>
            </div>
        @endif

        @if ($partner->status === 'rejected' && $partner->rejected_reason)
            <div class="rounded-xl border border-red-200 bg-red-50 p-5 text-sm text-red-700">
                <span class="font-semibold">{{ __('Rejection reason:') }}</span> {{ $partner->rejected_reason }}
            </div>
        @endif

        <div class="bg-white rounded-xl border border-slate-100">
            <div class="px-5 py-4 border-b border-slate-100 flex items-center justify-between">
                <h3 class="font-semibold text-slate-900">{{ __('Referrals') }}</h3>
                @if ($partner->referral_code)
                    <span class="text-xs text-slate-400">{{ __('Referral link') }}: {{ url('/r/'.$partner->referral_code) }}</span>
                @endif
            </div>
            @if ($partner->referrals->isEmpty())
                <p class="px-5 py-6 text-sm text-slate-500">{{ __('No referrals recorded yet.') }}</p>
            @else
                <div class="divide-y divide-slate-50">
                    @foreach ($partner->referrals as $referral)
                        <form method="POST" action="{{ route('admin.partners.referrals.update', $referral) }}" class="px-5 py-4 grid gap-3 sm:grid-cols-2 lg:grid-cols-5 items-end">
                            @csrf
                            <div class="lg:col-span-2">
                                <div class="text-sm font-medium text-slate-800">{{ $referral->lead?->name ?? __('Manual referral') }}</div>
                                <div class="text-xs text-slate-400">{{ $referral->lead?->email }}</div>
                                @if ($referral->company)
                                    <a href="{{ route('admin.companies.show', $referral->company) }}" class="text-xs font-semibold text-brand-700 hover:underline">{{ $referral->company->name }} →</a>
                                @endif
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-slate-500">{{ __('Status') }}</label>
                                <select name="status" class="w-full rounded-lg border border-slate-200 text-sm">
                                    @foreach (\App\Models\PartnerReferral::STATUSES as $value)
                                        <option value="{{ $value }}" @selected($referral->status === $value)>{{ (new \App\Models\PartnerReferral(['status' => $value]))->statusLabel() }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-slate-500">{{ __('Commission amount') }}</label>
                                <input type="number" step="0.01" min="0" name="commission_amount" value="{{ $referral->commission_amount }}" class="w-full rounded-lg border border-slate-200 text-sm">
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-slate-500">{{ __('Commission status') }}</label>
                                <div class="flex gap-2">
                                    <select name="commission_status" class="flex-1 rounded-lg border border-slate-200 text-sm">
                                        @foreach (\App\Models\PartnerReferral::COMMISSION_STATUSES as $value)
                                            <option value="{{ $value }}" @selected($referral->commission_status === $value)>{{ (new \App\Models\PartnerReferral(['commission_status' => $value]))->commissionStatusLabel() }}</option>
                                        @endforeach
                                    </select>
                                    <button type="submit" class="rounded-lg bg-slate-800 px-3 py-1.5 text-xs font-semibold text-white hover:bg-slate-900">{{ __('Save') }}</button>
                                </div>
                            </div>
                        </form>
                    @endforeach
                </div>
            @endif
        </div>

        <div class="bg-white rounded-xl border border-slate-100">
            <div class="px-5 py-4 border-b border-slate-100">
                <h3 class="font-semibold text-slate-900">{{ __('Payout requests') }}</h3>
            </div>
            @if ($partner->payouts->isEmpty())
                <p class="px-5 py-6 text-sm text-slate-500">{{ __('No payout requests yet.') }}</p>
            @else
                <div class="divide-y divide-slate-50">
                    @foreach ($partner->payouts as $payout)
                        <form method="POST" action="{{ route('admin.partners.payouts.update', $payout) }}" class="px-5 py-4 grid gap-3 sm:grid-cols-2 lg:grid-cols-4 items-end">
                            @csrf
                            <div>
                                <div class="text-sm font-medium text-slate-800">{{ \App\Support\Money::format($payout->amount) }}</div>
                                <div class="text-xs text-slate-400">{{ $payout->requested_at?->format('Y-m-d') }}</div>
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-slate-500">{{ __('Status') }}</label>
                                <select name="status" class="w-full rounded-lg border border-slate-200 text-sm">
                                    @foreach (\App\Models\PartnerPayout::STATUSES as $value)
                                        <option value="{{ $value }}" @selected($payout->status === $value)>{{ (new \App\Models\PartnerPayout(['status' => $value]))->statusLabel() }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-slate-500">{{ __('Reference') }}</label>
                                <input type="text" name="reference" value="{{ $payout->reference }}" class="w-full rounded-lg border border-slate-200 text-sm">
                            </div>
                            <button type="submit" class="rounded-lg bg-slate-800 px-3 py-1.5 text-xs font-semibold text-white hover:bg-slate-900">{{ __('Save') }}</button>
                        </form>
                    @endforeach
                </div>
            @endif
        </div>
    </div>

    <div class="space-y-6">
        @if ($partner->status === 'pending')
            <div class="bg-white rounded-xl border border-slate-100 p-5 space-y-4">
                <h3 class="font-semibold text-slate-900">{{ __('Review application') }}</h3>
                <form method="POST" action="{{ route('admin.partners.approve', $partner) }}" class="space-y-2">
                    @csrf
                    <label class="block text-xs font-medium text-slate-500">{{ __('Partner type') }}</label>
                    <select name="partner_type_id" required class="w-full rounded-lg border border-slate-200 text-sm">
                        <option value="">{{ __('Select a type') }}</option>
                        @foreach ($partnerTypes as $type)
                            <option value="{{ $type->id }}" @selected($partner->partner_type_id === $type->id)>{{ $type->name() }} — {{ $type->commissionLabel() }}</option>
                        @endforeach
                    </select>
                    <label class="block text-xs font-medium text-slate-500 pt-1">{{ __('Commission override (optional)') }}</label>
                    <div class="flex gap-2">
                        <select name="commission_type_override" class="w-1/2 rounded-lg border border-slate-200 text-sm">
                            <option value="">{{ __('Use type default') }}</option>
                            <option value="percentage">{{ __('Percentage') }}</option>
                            <option value="fixed">{{ __('Fixed') }}</option>
                        </select>
                        <input type="number" step="0.01" min="0" name="commission_value_override" placeholder="{{ __('Value') }}" class="w-1/2 rounded-lg border border-slate-200 text-sm">
                    </div>
                    <button type="submit" class="w-full rounded-lg bg-emerald-600 px-3 py-2 text-sm font-semibold text-white hover:bg-emerald-700">{{ __('Approve & send invite') }}</button>
                </form>
                <form method="POST" action="{{ route('admin.partners.reject', $partner) }}" class="space-y-2 pt-2 border-t border-slate-100">
                    @csrf
                    <input type="text" name="rejected_reason" required placeholder="{{ __('Reason for rejection') }}" class="w-full rounded-lg border border-slate-200 text-sm">
                    <button type="submit" class="w-full rounded-lg bg-red-600 px-3 py-2 text-sm font-semibold text-white hover:bg-red-700">{{ __('Reject application') }}</button>
                </form>
            </div>
        @endif

        @if (in_array($partner->status, ['invited', 'active', 'suspended']))
            <div class="bg-white rounded-xl border border-slate-100 p-5 space-y-4">
                <h3 class="font-semibold text-slate-900">{{ __('Commission settings') }}</h3>
                <form method="POST" action="{{ route('admin.partners.commission', $partner) }}" class="space-y-2">
                    @csrf
                    <label class="block text-xs font-medium text-slate-500">{{ __('Partner type') }}</label>
                    <select name="partner_type_id" required class="w-full rounded-lg border border-slate-200 text-sm">
                        @foreach ($partnerTypes as $type)
                            <option value="{{ $type->id }}" @selected($partner->partner_type_id === $type->id)>{{ $type->name() }} — {{ $type->commissionLabel() }}</option>
                        @endforeach
                    </select>
                    <label class="block text-xs font-medium text-slate-500 pt-1">{{ __('Commission override (optional)') }}</label>
                    <div class="flex gap-2">
                        <select name="commission_type_override" class="w-1/2 rounded-lg border border-slate-200 text-sm">
                            <option value="">{{ __('Use type default') }}</option>
                            <option value="percentage" @selected($partner->commission_type_override === 'percentage')>{{ __('Percentage') }}</option>
                            <option value="fixed" @selected($partner->commission_type_override === 'fixed')>{{ __('Fixed') }}</option>
                        </select>
                        <input type="number" step="0.01" min="0" name="commission_value_override" value="{{ $partner->commission_value_override }}" placeholder="{{ __('Value') }}" class="w-1/2 rounded-lg border border-slate-200 text-sm">
                    </div>
                    <button type="submit" class="w-full rounded-lg bg-slate-800 px-3 py-2 text-sm font-semibold text-white hover:bg-slate-900">{{ __('Save commission settings') }}</button>
                </form>

                <div class="pt-2 border-t border-slate-100">
                    @if ($partner->status === 'active')
                        <form method="POST" action="{{ route('admin.partners.suspend', $partner) }}" onsubmit="return confirm('{{ __('Suspend this partner?') }}')">
                            @csrf
                            <button type="submit" class="w-full rounded-lg border border-red-200 px-3 py-2 text-sm font-semibold text-red-600 hover:bg-red-50">{{ __('Suspend partner') }}</button>
                        </form>
                    @elseif ($partner->status === 'suspended')
                        <form method="POST" action="{{ route('admin.partners.reactivate', $partner) }}">
                            @csrf
                            <button type="submit" class="w-full rounded-lg border border-emerald-200 px-3 py-2 text-sm font-semibold text-emerald-700 hover:bg-emerald-50">{{ __('Reactivate partner') }}</button>
                        </form>
                    @endif
                </div>
            </div>
        @endif

        <div class="bg-white rounded-xl border border-slate-100 p-5">
            <dl class="space-y-1.5 text-sm">
                <div class="flex justify-between"><dt class="text-slate-500">{{ __('Unpaid approved commission') }}</dt><dd class="font-medium">{{ \App\Support\Money::format($partner->unpaidApprovedBalance()) }}</dd></div>
                @if ($partner->bank_iban)
                    <div class="flex justify-between"><dt class="text-slate-500">{{ __('IBAN') }}</dt><dd class="font-medium">{{ $partner->bank_iban }}</dd></div>
                @endif
                @if ($partner->bank_account_name)
                    <div class="flex justify-between"><dt class="text-slate-500">{{ __('Account name') }}</dt><dd class="font-medium">{{ $partner->bank_account_name }}</dd></div>
                @endif
            </dl>
        </div>
    </div>
</div>
@endsection
