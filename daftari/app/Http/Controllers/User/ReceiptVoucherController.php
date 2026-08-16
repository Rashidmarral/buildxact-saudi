<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Models\BankAccount;
use App\Models\Client;
use App\Models\Invoice;
use App\Models\ReceiptVoucher;
use App\Models\Supplier;
use App\Services\Accounting\LedgerPostingService;
use App\Services\ChromiumPdfRenderer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class ReceiptVoucherController extends Controller
{
    public function downloadPdf(ReceiptVoucher $receiptVoucher, ChromiumPdfRenderer $renderer)
    {
        $receiptVoucher->loadMissing('bankAccount', 'invoice', 'counterAccount');

        $pdf = $renderer->renderDocument('documents.print.voucher-standalone', [
            'voucher' => $receiptVoucher,
            'type' => 'receipt',
            'company' => $receiptVoucher->company,
            'template' => $receiptVoucher->company->defaultTemplateFor('receipt_voucher'),
        ]);

        return response($pdf, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="'.$receiptVoucher->voucher_number.'.pdf"',
        ]);
    }

    public function index()
    {
        $vouchers = ReceiptVoucher::with('bankAccount', 'client', 'supplier')->latest('date')->latest('id')->paginate(20);

        return view('user.receipt-vouchers.index', compact('vouchers'));
    }

    public function create()
    {
        $company = Auth::user()->company;

        return view('user.receipt-vouchers.form', [
            'accounts' => BankAccount::where('is_active', true)->orderBy('name')->get(),
            'clients' => Client::orderBy('name')->get(),
            'suppliers' => Supplier::orderBy('name')->get(),
            'glAccounts' => $company->accounts()->where('is_active', true)->orderBy('code')->get(),
        ]);
    }

    public function store(Request $request, LedgerPostingService $ledger)
    {
        $data = $this->validated($request);

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
                'party_type' => $data['party_type'],
                'client_id' => $data['party_type'] === 'customer' ? $data['client_id'] : null,
                'supplier_id' => $data['party_type'] === 'supplier' ? $data['supplier_id'] : null,
                'counter_account_id' => $data['counter_account_id'] ?? null,
                'invoice_id' => $data['invoice_id'] ?? null,
                'invoice_payment_id' => $invoicePaymentId,
                'created_by' => Auth::id(),
                'voucher_number' => $company->nextReceiptNumber(),
                'date' => $data['date'],
                'payer_name' => $data['payer_name'],
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

        $ledger->postReceiptVoucher($voucher);

        return redirect()->route('app.receipt-vouchers.show', $voucher)->with('status', __('Receipt voucher created.'));
    }

    public function show(ReceiptVoucher $receiptVoucher)
    {
        $receiptVoucher->load('bankAccount', 'client', 'supplier', 'invoice', 'counterAccount');

        $template = $receiptVoucher->company->defaultTemplateFor('receipt_voucher');

        return view('user.receipt-vouchers.show', ['voucher' => $receiptVoucher, 'template' => $template]);
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
            'party_type' => ['required', 'in:manual,customer,supplier'],
            'client_id' => ['nullable', 'required_if:party_type,customer', Rule::exists('clients', 'id')->where('company_id', $companyId)],
            'supplier_id' => ['nullable', 'required_if:party_type,supplier', Rule::exists('suppliers', 'id')->where('company_id', $companyId)],
            'counter_account_id' => ['nullable', Rule::exists('accounts', 'id')->where('company_id', $companyId)],
            'invoice_id' => ['nullable', Rule::exists('invoices', 'id')->where('company_id', $companyId)],
            'date' => ['required', 'date'],
            'payer_name' => ['required', 'string', 'max:255'],
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
