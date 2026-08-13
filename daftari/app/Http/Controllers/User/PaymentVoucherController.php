<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Models\BankAccount;
use App\Models\Expense;
use App\Models\PaymentVoucher;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class PaymentVoucherController extends Controller
{
    public function index()
    {
        $vouchers = PaymentVoucher::with('bankAccount')->latest('date')->latest('id')->paginate(20);

        return view('user.payment-vouchers.index', compact('vouchers'));
    }

    public function create()
    {
        return view('user.payment-vouchers.form', [
            'accounts' => BankAccount::where('is_active', true)->orderBy('name')->get(),
            'expenses' => Expense::latest('expense_date')->take(100)->get(),
        ]);
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);
        $company = Auth::user()->company;

        $voucher = PaymentVoucher::create([
            'bank_account_id' => $data['bank_account_id'],
            'expense_id' => $data['expense_id'] ?? null,
            'created_by' => Auth::id(),
            'voucher_number' => $company->nextPaymentVoucherNumber(),
            'date' => $data['date'],
            'payee_name' => $data['payee_name'],
            'amount' => $data['amount'],
            'method' => $data['method'],
            'reference' => $data['reference'] ?? null,
            'notes' => $data['notes'] ?? null,
            'status' => 'issued',
        ]);

        return redirect()->route('app.payment-vouchers.show', $voucher)->with('status', __('Payment voucher created.'));
    }

    public function show(PaymentVoucher $paymentVoucher)
    {
        $paymentVoucher->load('bankAccount', 'expense');

        return view('user.payment-vouchers.show', ['voucher' => $paymentVoucher]);
    }

    public function void(PaymentVoucher $paymentVoucher)
    {
        $paymentVoucher->update(['status' => 'void']);

        return back()->with('status', __('Payment voucher voided.'));
    }

    private function validated(Request $request): array
    {
        $companyId = Auth::user()->company_id;

        return $request->validate([
            'bank_account_id' => ['required', Rule::exists('bank_accounts', 'id')->where('company_id', $companyId)],
            'expense_id' => ['nullable', Rule::exists('expenses', 'id')->where('company_id', $companyId)],
            'date' => ['required', 'date'],
            'payee_name' => ['required', 'string', 'max:255'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'method' => ['required', 'in:cash,bank_transfer,card,cheque'],
            'reference' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);
    }
}
