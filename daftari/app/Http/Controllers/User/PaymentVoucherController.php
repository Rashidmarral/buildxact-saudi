<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Models\BankAccount;
use App\Models\Bill;
use App\Models\Client;
use App\Models\Expense;
use App\Models\PaymentVoucher;
use App\Models\Supplier;
use App\Services\Accounting\LedgerPostingService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class PaymentVoucherController extends Controller
{
    public function index()
    {
        $vouchers = PaymentVoucher::with('bankAccount', 'client', 'supplier')->latest('date')->latest('id')->paginate(20);

        return view('user.payment-vouchers.index', compact('vouchers'));
    }

    public function create()
    {
        $company = Auth::user()->company;

        return view('user.payment-vouchers.form', [
            'accounts' => BankAccount::where('is_active', true)->orderBy('name')->get(),
            'expenses' => Expense::latest('expense_date')->take(100)->get(),
            'suppliers' => Supplier::orderBy('name')->get(),
            'clients' => Client::orderBy('name')->get(),
            'glAccounts' => $company->accounts()->where('is_active', true)->orderBy('code')->get(),
        ]);
    }

    public function store(Request $request, LedgerPostingService $ledger)
    {
        $data = $this->validated($request);

        $voucher = DB::transaction(function () use ($data) {
            $company = Auth::user()->company;
            $billPaymentId = null;

            if (! empty($data['bill_id'])) {
                $bill = Bill::findOrFail($data['bill_id']);
                $payment = $bill->billPayments()->create([
                    'amount' => $data['amount'],
                    'paid_at' => $data['date'],
                    'method' => $data['method'],
                    'reference' => $data['reference'] ?? null,
                ]);
                $bill->amount_paid = $bill->billPayments()->sum('amount');
                $bill->save();
                $billPaymentId = $payment->id;
            }

            return PaymentVoucher::create([
                'bank_account_id' => $data['bank_account_id'],
                'party_type' => $data['party_type'],
                'client_id' => $data['party_type'] === 'customer' ? $data['client_id'] : null,
                'supplier_id' => $data['party_type'] === 'supplier' ? $data['supplier_id'] : null,
                'counter_account_id' => $data['counter_account_id'] ?? null,
                'expense_id' => $data['expense_id'] ?? null,
                'bill_id' => $data['bill_id'] ?? null,
                'bill_payment_id' => $billPaymentId,
                'created_by' => Auth::id(),
                'voucher_number' => $company->nextPaymentVoucherNumber(),
                'date' => $data['date'],
                'payee_name' => $data['payee_name'],
                'party_name_ar' => $data['party_name_ar'] ?? null,
                'party_vat_number' => $data['party_vat_number'] ?? null,
                'party_phone' => $data['party_phone'] ?? null,
                'party_email' => $data['party_email'] ?? null,
                'party_address' => $data['party_address'] ?? null,
                'amount' => $data['amount'],
                'method' => $data['method'],
                'reference' => $data['reference'] ?? null,
                'notes' => $data['notes'] ?? null,
                'status' => 'issued',
            ]);
        });

        $ledger->postPaymentVoucher($voucher);

        return redirect()->route('app.payment-vouchers.show', $voucher)->with('status', __('Payment voucher created.'));
    }

    public function show(PaymentVoucher $paymentVoucher)
    {
        $paymentVoucher->load('bankAccount', 'expense', 'bill', 'client', 'supplier', 'counterAccount');

        return view('user.payment-vouchers.show', ['voucher' => $paymentVoucher]);
    }

    public function void(PaymentVoucher $paymentVoucher, LedgerPostingService $ledger)
    {
        if ($paymentVoucher->status === 'void') {
            return back();
        }

        DB::transaction(function () use ($paymentVoucher) {
            if ($paymentVoucher->bill_payment_id && $paymentVoucher->bill) {
                $bill = $paymentVoucher->bill;
                $bill->billPayments()->where('id', $paymentVoucher->bill_payment_id)->delete();
                $bill->amount_paid = $bill->billPayments()->sum('amount');
                $bill->save();
            }

            $paymentVoucher->update(['status' => 'void']);
        });

        $ledger->reverse($paymentVoucher->company, 'payment_voucher', $paymentVoucher->id, __('Payment voucher :number voided', ['number' => $paymentVoucher->voucher_number]));

        return back()->with('status', __('Payment voucher voided.'));
    }

    private function validated(Request $request): array
    {
        $companyId = Auth::user()->company_id;

        return $request->validate([
            'bank_account_id' => ['required', Rule::exists('bank_accounts', 'id')->where('company_id', $companyId)],
            'party_type' => ['required', 'in:manual,customer,supplier'],
            'client_id' => ['nullable', 'required_if:party_type,customer', Rule::exists('clients', 'id')->where('company_id', $companyId)],
            'supplier_id' => ['nullable', 'required_if:party_type,supplier', Rule::exists('suppliers', 'id')->where('company_id', $companyId)],
            'counter_account_id' => ['nullable', Rule::exists('accounts', 'id')->where('company_id', $companyId)],
            'expense_id' => ['nullable', Rule::exists('expenses', 'id')->where('company_id', $companyId)],
            'bill_id' => ['nullable', Rule::exists('bills', 'id')->where('company_id', $companyId)],
            'date' => ['required', 'date'],
            'payee_name' => ['required', 'string', 'max:255'],
            'party_name_ar' => ['nullable', 'string', 'max:255'],
            'party_vat_number' => ['nullable', 'string', 'max:32'],
            'party_phone' => ['nullable', 'string', 'max:30'],
            'party_email' => ['nullable', 'email', 'max:255'],
            'party_address' => ['nullable', 'string', 'max:255'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'method' => ['required', 'in:cash,bank_transfer,card,cheque'],
            'reference' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);
    }
}
