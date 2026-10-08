<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Models\BankAccount;
use App\Services\Features\FeatureAccessService;
use Illuminate\Support\Facades\Auth;

/**
 * "how can i track payment for him" — a company that hands cash floats to
 * site staff (an engineer who spends on-site costs, someone who runs daily
 * local errands) already has everything needed: flag their float as its
 * own BankAccount (type cash or bank) with is_personal + personal_owner_name
 * set (BankAccount::$fillable), and its own running-balance statement
 * (Project Cash Flow > that account) already shows the advance in, every
 * spend out, and what's still unaccounted with them. This page just
 * collects every such account into one glance instead of opening each
 * custodian's statement one at a time.
 */
class CashCustodyController extends Controller
{
    public function index(FeatureAccessService $featureAccess)
    {
        $accounts = BankAccount::where('is_personal', true)
            ->orderBy('personal_owner_name')
            ->orderBy('name')
            ->get();

        return view('user.cash-custody.index', [
            'accounts' => $accounts,
            'totalOutstanding' => $accounts->sum(fn (BankAccount $account) => $account->currentBalance()),
            // The per-account running-balance statement lives in the
            // separately-gated Project Cash Flow module — link to it only
            // when this company actually has access, rather than send a
            // company without that module into a 403.
            'hasProjectCashFlow' => $featureAccess->enabled(Auth::user()->company, 'project_cash_flow'),
        ]);
    }
}
