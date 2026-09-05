<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Models\BankAccount;
use App\Models\Client;
use App\Models\Invoice;
use App\Models\ReceiptVoucher;
use App\Models\Supplier;
use App\Services\Accounting\LedgerPostingService;
use App\Services\MpdfRenderer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class ReceiptVoucherController extends Controller
{
    public function downloadPdf(ReceiptVoucher $receiptVoucher, MpdfRenderer $renderer)
    {
        $receiptVoucher->loadMissing('bankAccount', 'invoice.items', 'counterAccount');

        $pdf = $renderer->render('documents.print.voucher-pdf', [
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
        $clients = Client::orderBy('name')->get();
        $suppliers = Supplier::orderBy('name')->get();

        return view('user.receipt-vouchers.form', [
            'accounts' => BankAccount::where('is_active', true)->orderBy('name')->get(),
            'clients' => $clients,
            'suppliers' => $suppliers,
            'glAccounts' => $company->accounts()->where('is_active', true)->orderBy('code')->get(),
            'clientsData' => $clients->mapWithKeys(fn (Client $c) => [$c->id => [
                'name_ar' => $c->name_ar, 'name' => $c->name, 'vat_number' => $c->vat_number,
                'phone' => $c->phone, 'email' => $c->email, 'address' => $c->fullAddress(),
            ]]),
            'suppliersData' => $suppliers->mapWithKeys(fn (Supplier $s) => [$s->id => [
                'name_ar' => $s->name_ar, 'name' => $s->name, 'vat_number' => $s->vat_number,
                'phone' => $s->phone, 'email' => $s->email, 'address' => $s->fullAddress(),
            ]]),
        ]);
    }

    public function store(Request $request, LedgerPostingService $ledger)
    {
        $data = $this->validated($request);

        $voucher = DB::transaction(function () use ($data, $ledger) {
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

            $voucher = ReceiptVoucher::create([
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

            $ledger->postReceiptVoucher($voucher);

            return $voucher;
        });

        return redirect()->route('app.receipt-vouchers.show', $voucher)->with('status', __('Receipt voucher created.'));
    }

    public function show(ReceiptVoucher $receiptVoucher)
    {
        $receiptVoucher->load('bankAccount', 'client', 'supplier', 'invoice.items', 'counterAccount');

        $template = $receiptVoucher->company->defaultTemplateFor('receipt_voucher');

        return view('user.receipt-vouchers.show', ['voucher' => $receiptVoucher, 'template' => $template]);
    }

    public function edit(ReceiptVoucher $receiptVoucher)
    {
        if ($receiptVoucher->status === 'void') {
            return redirect()->route('app.receipt-vouchers.show', $receiptVoucher)->with('status', __('A voided voucher cannot be edited.'));
        }

        $receiptVoucher->load('invoice.items');

        $company = Auth::user()->company;
        $clients = Client::orderBy('name')->get();
        $suppliers = Supplier::orderBy('name')->get();

        return view('user.receipt-vouchers.form', [
            'voucher' => $receiptVoucher,
            'accounts' => BankAccount::where('is_active', true)->orderBy('name')->get(),
            'clients' => $clients,
            'suppliers' => $suppliers,
            'glAccounts' => $company->accounts()->where('is_active', true)->orderBy('code')->get(),
            'clientsData' => $clients->mapWithKeys(fn (Client $c) => [$c->id => [
                'name_ar' => $c->name_ar, 'name' => $c->name, 'vat_number' => $c->vat_number,
                'phone' => $c->phone, 'email' => $c->email, 'address' => $c->fullAddress(),
            ]]),
            'suppliersData' => $suppliers->mapWithKeys(fn (Supplier $s) => [$s->id => [
                'name_ar' => $s->name_ar, 'name' => $s->name, 'vat_number' => $s->vat_number,
                'phone' => $s->phone, 'email' => $s->email, 'address' => $s->fullAddress(),
            ]]),
            // The invoice this voucher is already linked to may no longer
            // be "outstanding" (this very voucher's payment may have fully
            // settled it) and so would be missing from the fetched
            // outstanding-invoices list — pass it separately so the
            // dropdown always keeps the currently linked invoice selectable.
            'currentInvoice' => $receiptVoucher->invoice ? [
                'id' => $receiptVoucher->invoice->id,
                'invoice_number' => $receiptVoucher->invoice->invoice_number,
                'date' => $receiptVoucher->invoice->issue_date->format('Y-m-d'),
                'total' => number_format((float) $receiptVoucher->invoice->total, 2),
                'balance' => number_format($receiptVoucher->invoice->balanceDue(), 2),
                'items' => $receiptVoucher->invoice->items->map(fn ($line) => [
                    'description' => $line->description,
                    'quantity' => rtrim(rtrim(number_format((float) $line->quantity, 2), '0'), '.'),
                ])->values(),
            ] : null,
        ]);
    }

    public function update(Request $request, ReceiptVoucher $receiptVoucher, LedgerPostingService $ledger)
    {
        if ($receiptVoucher->status === 'void') {
            return back()->with('status', __('A voided voucher cannot be edited.'));
        }

        $data = $this->validated($request);

        DB::transaction(function () use ($data, $receiptVoucher, $ledger) {
            // Undo the previous invoice linkage's side effects, mirroring
            // void() — amount_paid/status must reflect whatever the
            // voucher ends up looking like after this edit, not what it
            // looked like when it was first created.
            if ($receiptVoucher->invoice_payment_id && $receiptVoucher->invoice) {
                $oldInvoice = $receiptVoucher->invoice;
                $oldInvoice->invoicePayments()->where('id', $receiptVoucher->invoice_payment_id)->delete();
                $oldInvoice->amount_paid = $oldInvoice->invoicePayments()->sum('amount');
                $oldInvoice->status = $oldInvoice->isFullyPaid() ? 'paid' : ($oldInvoice->amount_paid > 0 ? 'partially_paid' : 'sent');
                $oldInvoice->save();
            }

            // Rebuild the ledger posting from scratch rather than adjusting
            // it in place — postReceiptVoucher() derives every line from
            // the voucher's current state, so deleting the old entry first
            // is the only way to avoid double-posting or stale amounts.
            $ledger->deletePosting($receiptVoucher->company, 'receipt_voucher', $receiptVoucher->id);

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

            $receiptVoucher->update([
                'bank_account_id' => $data['bank_account_id'],
                'party_type' => $data['party_type'],
                'client_id' => $data['party_type'] === 'customer' ? $data['client_id'] : null,
                'supplier_id' => $data['party_type'] === 'supplier' ? $data['supplier_id'] : null,
                'counter_account_id' => $data['counter_account_id'] ?? null,
                'invoice_id' => $data['invoice_id'] ?? null,
                'invoice_payment_id' => $invoicePaymentId,
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
            ]);

            $ledger->postReceiptVoucher($receiptVoucher->fresh(['bankAccount', 'counterAccount', 'invoice']));
        });

        return redirect()->route('app.receipt-vouchers.show', $receiptVoucher)->with('status', __('Receipt voucher updated.'));
    }

    public function destroy(ReceiptVoucher $receiptVoucher)
    {
        if ($receiptVoucher->status !== 'void') {
            return back()->with('status', __('Void this voucher first so its ledger entries and any linked invoice payment are safely reversed, then delete it.'));
        }

        $receiptVoucher->delete();

        return redirect()->route('app.receipt-vouchers.index')->with('status', __('Receipt voucher deleted.'));
    }

    public function void(ReceiptVoucher $receiptVoucher, LedgerPostingService $ledger)
    {
        if ($receiptVoucher->status === 'void') {
            return back();
        }

        DB::transaction(function () use ($receiptVoucher, $ledger) {
            if ($receiptVoucher->invoice_payment_id && $receiptVoucher->invoice) {
                $invoice = $receiptVoucher->invoice;
                $invoice->invoicePayments()->where('id', $receiptVoucher->invoice_payment_id)->delete();
                $invoice->amount_paid = $invoice->invoicePayments()->sum('amount');
                $invoice->status = $invoice->isFullyPaid() ? 'paid' : ($invoice->amount_paid > 0 ? 'partially_paid' : 'sent');
                $invoice->save();
            }

            $receiptVoucher->update(['status' => 'void']);

            $ledger->reverse($receiptVoucher->company, 'receipt_voucher', $receiptVoucher->id, __('Receipt voucher :number voided', ['number' => $receiptVoucher->voucher_number]));
        });

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
