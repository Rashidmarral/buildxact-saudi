@extends('layouts.partner')

@section('title', __('Partner Dashboard'))

@section('content')
<div class="grid grid-cols-2 gap-4 sm:gap-5 lg:grid-cols-4">
    @foreach ([
        ['label' => __('Total referrals'), 'value' => number_format($stats['total_referrals'])],
        ['label' => __('Converted'), 'value' => number_format($stats['converted'])],
        ['label' => __('Unpaid approved commission'), 'value' => \App\Support\Money::format($stats['unpaid_balance'])],
        ['label' => __('Lifetime paid'), 'value' => \App\Support\Money::format($stats['lifetime_paid'])],
    ] as $card)
        <div class="rounded-2xl border border-slate-100 bg-white p-5 shadow-card">
            <span class="text-xs font-medium text-slate-500">{{ $card['label'] }}</span>
            <div class="mt-2 text-2xl font-bold text-slate-900">{{ $card['value'] }}</div>
        </div>
    @endforeach
</div>

@if ($referralUrl)
    <div class="mt-6 rounded-2xl border border-brand-100 bg-brand-50 p-5">
        <div class="text-sm font-semibold text-brand-800">{{ __('Your referral link') }}</div>
        <div class="mt-2 flex flex-wrap items-center gap-3">
            <code class="rounded-lg bg-white px-3 py-2 text-sm text-slate-700 border border-brand-100">{{ $referralUrl }}</code>
        </div>
        <p class="mt-2 text-xs text-brand-700">{{ __('Share this link — anyone who signs up through it is tracked as your referral.') }}</p>
    </div>
@endif

<div class="mt-6 grid lg:grid-cols-3 gap-6">
    <div class="lg:col-span-2 space-y-6">
        <div class="bg-white rounded-xl border border-slate-100">
            <div class="px-5 py-4 border-b border-slate-100">
                <h3 class="font-semibold text-slate-900">{{ __('Your referrals') }}</h3>
            </div>
            @if ($partner->referrals->isEmpty())
                <p class="px-5 py-6 text-sm text-slate-500">{{ __('No referrals yet — share your link above to get started.') }}</p>
            @else
                <table class="w-full text-sm">
                    <thead>
                        <tr class="text-left text-slate-500 border-b border-slate-100">
                            <th class="px-5 py-2 font-medium">{{ __('Lead') }}</th>
                            <th class="px-5 py-2 font-medium">{{ __('Status') }}</th>
                            <th class="px-5 py-2 font-medium">{{ __('Commission') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($partner->referrals as $referral)
                            <tr class="border-b border-slate-50 last:border-0">
                                <td class="px-5 py-3">{{ $referral->lead?->name ?? __('—') }}</td>
                                <td class="px-5 py-3"><span class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-semibold {{ $referral->statusBadgeClasses() }}">{{ $referral->statusLabel() }}</span></td>
                                <td class="px-5 py-3">
                                    @if ($referral->commission_amount)
                                        {{ \App\Support\Money::format($referral->commission_amount) }}
                                        <span class="ms-1 inline-flex items-center rounded-full px-2 py-0.5 text-xs font-semibold {{ $referral->commissionStatusBadgeClasses() }}">{{ $referral->commissionStatusLabel() }}</span>
                                    @else
                                        —
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif
        </div>

        <div class="bg-white rounded-xl border border-slate-100">
            <div class="px-5 py-4 border-b border-slate-100">
                <h3 class="font-semibold text-slate-900">{{ __('Payout history') }}</h3>
            </div>
            @if ($partner->payouts->isEmpty())
                <p class="px-5 py-6 text-sm text-slate-500">{{ __('No payout requests yet.') }}</p>
            @else
                <table class="w-full text-sm">
                    <thead>
                        <tr class="text-left text-slate-500 border-b border-slate-100">
                            <th class="px-5 py-2 font-medium">{{ __('Amount') }}</th>
                            <th class="px-5 py-2 font-medium">{{ __('Status') }}</th>
                            <th class="px-5 py-2 font-medium">{{ __('Requested') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($partner->payouts as $payout)
                            <tr class="border-b border-slate-50 last:border-0">
                                <td class="px-5 py-3">{{ \App\Support\Money::format($payout->amount) }}</td>
                                <td class="px-5 py-3"><span class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-semibold {{ $payout->statusBadgeClasses() }}">{{ $payout->statusLabel() }}</span></td>
                                <td class="px-5 py-3 text-slate-500">{{ $payout->requested_at?->format('Y-m-d') }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif
        </div>
    </div>

    <div class="space-y-6">
        <div class="bg-white rounded-xl border border-slate-100 p-5 space-y-3">
            <h3 class="font-semibold text-slate-900">{{ __('Request a payout') }}</h3>
            <p class="text-sm text-slate-500">{{ __('Unpaid approved balance') }}: <span class="font-semibold text-slate-800">{{ \App\Support\Money::format($stats['unpaid_balance']) }}</span></p>
            <form method="POST" action="{{ route('partner.payouts.request') }}">
                @csrf
                <button type="submit" @disabled($stats['unpaid_balance'] <= 0) class="w-full rounded-lg bg-brand-600 px-4 py-2 text-sm font-semibold text-white hover:bg-brand-700 disabled:opacity-40 disabled:cursor-not-allowed">{{ __('Request payout') }}</button>
            </form>
        </div>

        <div class="bg-white rounded-xl border border-slate-100 p-5 space-y-3">
            <h3 class="font-semibold text-slate-900">{{ __('Payout details') }}</h3>
            <form method="POST" action="{{ route('partner.payout-method.update') }}" class="space-y-3">
                @csrf
                <div>
                    <label class="block text-xs font-medium text-slate-500">{{ __('Payout method') }}</label>
                    <select name="payout_method" class="mt-1 w-full rounded-lg border border-slate-200 text-sm">
                        <option value="">{{ __('Not set') }}</option>
                        <option value="bank_transfer" @selected($partner->payout_method === 'bank_transfer')>{{ __('Bank transfer') }}</option>
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-medium text-slate-500">{{ __('IBAN') }}</label>
                    <input type="text" name="bank_iban" value="{{ old('bank_iban', $partner->bank_iban) }}" class="mt-1 w-full rounded-lg border border-slate-200 text-sm">
                </div>
                <div>
                    <label class="block text-xs font-medium text-slate-500">{{ __('Account holder name') }}</label>
                    <input type="text" name="bank_account_name" value="{{ old('bank_account_name', $partner->bank_account_name) }}" class="mt-1 w-full rounded-lg border border-slate-200 text-sm">
                </div>
                <button type="submit" class="w-full rounded-lg bg-slate-800 px-4 py-2 text-sm font-semibold text-white hover:bg-slate-900">{{ __('Save details') }}</button>
            </form>
        </div>
    </div>
</div>
@endsection
