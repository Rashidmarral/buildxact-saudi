<?php

namespace App\Http\Controllers\Partner;

use App\Http\Controllers\Controller;
use App\Models\PartnerPayout;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * The logged-in partner's own dashboard: their referral link, pipeline
 * stats, commission balance, and payout requests. Scoped entirely to
 * Auth::user()->partner — a partner never sees another partner's data.
 */
class DashboardController extends Controller
{
    public function index()
    {
        $partner = Auth::user()->partner()->with([
            'partnerType',
            'referrals' => fn ($q) => $q->with(['lead:id,name,status'])->latest('id'),
            'payouts' => fn ($q) => $q->latest('id'),
        ])->firstOrFail();

        $stats = [
            'total_referrals' => $partner->referrals->count(),
            'converted' => $partner->referrals->where('status', 'converted')->count(),
            'unpaid_balance' => $partner->unpaidApprovedBalance(),
            'lifetime_paid' => $partner->referrals->where('commission_status', 'paid')->sum('commission_amount'),
        ];

        $referralUrl = $partner->referral_code ? url('/r/'.$partner->referral_code) : null;

        return view('partner.dashboard', compact('partner', 'stats', 'referralUrl'));
    }

    public function updatePayoutMethod(Request $request)
    {
        $partner = Auth::user()->partner()->firstOrFail();

        $data = $request->validate([
            'payout_method' => ['nullable', 'string', 'max:30'],
            'bank_iban' => ['nullable', 'string', 'max:40'],
            'bank_account_name' => ['nullable', 'string', 'max:255'],
        ]);

        $partner->update($data);

        return back()->with('status', __('Payout details saved.'));
    }

    public function requestPayout()
    {
        $partner = Auth::user()->partner()->firstOrFail();
        $balance = $partner->unpaidApprovedBalance();

        if ($balance <= 0) {
            return back()->withErrors(['payout' => __('You have no approved commission balance to request a payout for.')]);
        }

        PartnerPayout::create([
            'partner_id' => $partner->id,
            'amount' => $balance,
            'method' => $partner->payout_method,
            'status' => 'requested',
            'requested_at' => now(),
        ]);

        return back()->with('status', __('Payout requested. Our team will process it shortly.'));
    }
}
