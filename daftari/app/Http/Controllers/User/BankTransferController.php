<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Models\BankAccount;
use App\Models\BankTransfer;
use App\Services\Accounting\LedgerPostingService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class BankTransferController extends Controller
{
    public function index()
    {
        $transfers = BankTransfer::with('fromAccount', 'toAccount')->latest('date')->latest('id')->paginate(20);

        return view('user.bank-transfers.index', compact('transfers'));
    }

    public function create()
    {
        return view('user.bank-transfers.form', [
            'accounts' => BankAccount::where('is_active', true)->orderBy('name')->get(),
        ]);
    }

    public function store(Request $request, LedgerPostingService $ledger)
    {
        $companyId = Auth::user()->company_id;

        $data = $request->validate([
            'from_bank_account_id' => ['required', 'different:to_bank_account_id', Rule::exists('bank_accounts', 'id')->where('company_id', $companyId)],
            'to_bank_account_id' => ['required', Rule::exists('bank_accounts', 'id')->where('company_id', $companyId)],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'date' => ['required', 'date'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $transfer = BankTransfer::create($data + ['created_by' => Auth::id()]);
        $ledger->postBankTransfer($transfer);

        return redirect()->route('app.bank-transfers.index')->with('status', __('Transfer recorded.'));
    }
}
