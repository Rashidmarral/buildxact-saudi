<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Models\BankAccount;
use App\Models\Client;
use App\Models\Invoice;
use App\Models\ReceiptVoucher;
use App\Services\Accounting\LedgerPostingService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class ReceiptVoucherController extends Controller
{
    public function index()
    {
        $vouchers = ReceiptVoucher::with('bankAccount', 'client')->latest('date')->latest('id')->paginate(20);

        return view('user.receipt-vouchers.index', compact('vouchers'));
    }

    public function create()
    {
        return view('user.receipt-vouchers.form', [
            'accounts' => BankAccount::where('is_active', true)->orderBy('name')->get(),
            'clients' => Client::orderBy('name')->get(),
        ]);
    }

    public function store(Request $request, LedgerPostingService $ledger)
    {
        $data = $this->validated($request);
        $companyId = Auth::user()->company_id;

        $voucher = DB::transaction(function () use ($data) {
            $company = Auth::user()->company;
            $invoicePaymentId = null;

            if (! empty($data['invoice_id'])) {
                $invoice = Invoice::findOrFail($data['invoice_id']);
                $payment = $invoice->invoicePayments()->create([
                    'amount' => $data['amount'],
                    'paid_at' => $data['date'],
                    'method' => $data['method'],
                    'reference' => $data['reference'] ?? null,
                ]);
                $invoice->amount_paid = $invoice->invoicePayments()->sum('amount');
                $invoice->status = $invoice->isFullyPaid() ? 'paid' : 'partially_paid';
                $invoice->save();
                $invoicePaymentId = $payment->id;
            }

            return ReceiptVoucher::create([
                'bank_account_id' => $data['bank_account_id'],
                'client_id' => $data['client_id'] ?? null,
                'invoice_id' => $data['invoice_id'] ?? null,
                'invoice_payment_id' => $invoicePaymentId,
                'created_by' => Auth::id(),
                'voucher_number' => $company->nextReceiptNumber(),
                'date' => $data['date'],
                'payer_name' => $data['payer_name'],
                'amount' => $data['amount'],
                'method' => $data['method'],
                'reference' => $data['reference'] ?? null,
                'notes' => $data['notes'] ?? null,
                'status' => 'issued',
            ]);
        });

        $ledger->postReceiptVoucher($voucher);

        return redirect()->route('app.receipt-vouchers.show', $voucher)->with('status', __('Receipt voucher created.'));
    }

    public function show(ReceiptVoucher $receiptVoucher)
    {
        $receiptVoucher->load('bankAccount', 'client', 'invoice');

        return view('user.receipt-vouchers.show', ['voucher' => $receiptVoucher]);
    }

    public function void(ReceiptVoucher $receiptVoucher, LedgerPostingService $ledger)
    {
        if ($receiptVoucher->status === 'void') {
            return back();
        }

        DB::transaction(function () use ($receiptVoucher) {
            if ($receiptVoucher->invoice_payment_id && $receiptVoucher->invoice) {
                $invoice = $receiptVoucher->invoice;
                $invoice->invoicePayments()->where('id', $receiptVoucher->invoice_payment_id)->delete();
                $invoice->amount_paid = $invoice->invoicePayments()->sum('amount');
                $invoice->status = $invoice->isFullyPaid() ? 'paid' : ($invoice->amount_paid > 0 ? 'partially_paid' : 'sent');
                $invoice->save();
            }

            $receiptVoucher->update(['status' => 'void']);
        });

        $ledger->reverse($receiptVoucher->company, 'receipt_voucher', $receiptVoucher->id, __('Receipt voucher :number voided', ['number' => $receiptVoucher->voucher_number]));

        return back()->with('status', __('Receipt voucher voided.'));
    }

    private function validated(Request $request): array
    {
        $companyId = Auth::user()->company_id;

        return $request->validate([
            'bank_account_id' => ['required', Rule::exists('bank_accounts', 'id')->where('company_id', $companyId)],
            'client_id' => ['nullable', Rule::exists('clients', 'id')->where('company_id', $companyId)],
            'invoice_id' => ['nullable', Rule::exists('invoices', 'id')->where('company_id', $companyId)],
            'date' => ['required', 'date'],
            'payer_name' => ['required', 'string', 'max:255'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'method' => ['required', 'in:cash,bank_transfer,card,cheque'],
            'reference' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);
    }
}
