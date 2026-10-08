<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Http\Controllers\User\Concerns\EnforcesStorageQuota;
use App\Models\Attachment;
use App\Models\BankAccount;
use App\Models\Bill;
use App\Models\Client;
use App\Models\Expense;
use App\Models\PaymentVoucher;
use App\Models\Project;
use App\Models\Supplier;
use App\Services\Accounting\LedgerPostingService;
use App\Services\MpdfRenderer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class PaymentVoucherController extends Controller
{
    use EnforcesStorageQuota;

    /**
     * "there is no option to get total amount paid" for a supplier across
     * however many separate vouchers were issued to them — adds
     * supplier/client/date filters plus a total over exactly what's
     * filtered (status='issued' only, matching every other total in this
     * app — a voided voucher never counted as real money paid).
     */
    public function index(Request $request)
    {
        $query = PaymentVoucher::with('bankAccount', 'client', 'supplier')
            ->when($request->filled('supplier_id'), fn ($q) => $q->where('supplier_id', $request->integer('supplier_id')))
            ->when($request->filled('client_id'), fn ($q) => $q->where('client_id', $request->integer('client_id')))
            ->when($request->filled('from'), fn ($q) => $q->whereDate('date', '>=', $request->query('from')))
            ->when($request->filled('to'), fn ($q) => $q->whereDate('date', '<=', $request->query('to')));

        $total = (clone $query)->where('status', 'issued')->sum('amount');

        $vouchers = $query->latest('date')->latest('id')->paginate(20)->withQueryString();

        return view('user.payment-vouchers.index', [
            'vouchers' => $vouchers,
            'total' => $total,
            'suppliers' => Supplier::orderBy('name')->get(),
            'clients' => Client::orderBy('name')->get(),
            'filters' => $request->only('supplier_id', 'client_id', 'from', 'to'),
        ]);
    }

    /**
     * "there is option to get 1 single payment voucher for whole amount"
     * — a company that paid a supplier across several separate vouchers
     * (a subcontractor's progress payments, say) can bundle exactly the
     * filtered set into one branded PDF: every voucher listed with its
     * own date/reference/amount, plus a grand total — the document to
     * hand the supplier as a consolidated payment summary, without
     * re-entering anything.
     */
    public function summaryPdf(Request $request, MpdfRenderer $renderer)
    {
        abort_unless($request->filled('supplier_id') || $request->filled('client_id'), 404);

        $company = Auth::user()->company;
        $vouchers = PaymentVoucher::with('bankAccount')
            ->where('status', 'issued')
            ->when($request->filled('supplier_id'), fn ($q) => $q->where('supplier_id', $request->integer('supplier_id')))
            ->when($request->filled('client_id'), fn ($q) => $q->where('client_id', $request->integer('client_id')))
            ->when($request->filled('from'), fn ($q) => $q->whereDate('date', '>=', $request->query('from')))
            ->when($request->filled('to'), fn ($q) => $q->whereDate('date', '<=', $request->query('to')))
            ->orderBy('date')
            ->get();

        $party = $request->filled('supplier_id') ? Supplier::find($request->integer('supplier_id')) : Client::find($request->integer('client_id'));

        $pdf = $renderer->render('documents.print.voucher-summary-pdf', [
            'type' => 'payment',
            'company' => $company,
            'party' => $party,
            'vouchers' => $vouchers,
            'total' => (float) $vouchers->sum('amount'),
            'from' => $request->query('from'),
            'to' => $request->query('to'),
            'template' => $company->defaultTemplateFor('voucher_summary'),
        ]);

        return response($pdf, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="'.\Illuminate\Support\Str::slug($party?->name ?: 'payment-summary').'-payment-summary.pdf"',
        ]);
    }

    public function downloadPdf(PaymentVoucher $paymentVoucher, MpdfRenderer $renderer)
    {
        $paymentVoucher->loadMissing('bankAccount', 'bill.items', 'expense', 'counterAccount');

        $pdf = $renderer->render('documents.print.voucher-pdf', [
            'voucher' => $paymentVoucher,
            'type' => 'payment',
            'company' => $paymentVoucher->company,
            'template' => $paymentVoucher->company->defaultTemplateFor('payment_voucher'),
        ]);

        return response($pdf, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="'.$paymentVoucher->voucher_number.'.pdf"',
        ]);
    }

    public function create()
    {
        $company = Auth::user()->company;
        $suppliers = Supplier::orderBy('name')->get();
        $clients = Client::orderBy('name')->get();

        return view('user.payment-vouchers.form', [
            'accounts' => BankAccount::where('is_active', true)->orderBy('name')->get(),
            'expenses' => Expense::whereNull('bank_account_id')->latest('expense_date')->take(100)->get(),
            'suppliers' => $suppliers,
            'clients' => $clients,
            'glAccounts' => $company->accounts()->where('is_active', true)->orderBy('code')->get(),
            'projects' => Project::orderBy('name')->get(),
            'suppliersData' => $suppliers->mapWithKeys(fn (Supplier $s) => [$s->id => [
                'name_ar' => $s->name_ar, 'name' => $s->name, 'vat_number' => $s->vat_number,
                'phone' => $s->phone, 'email' => $s->email, 'address' => $s->fullAddress(),
            ]]),
            'clientsData' => $clients->mapWithKeys(fn (Client $c) => [$c->id => [
                'name_ar' => $c->name_ar, 'name' => $c->name, 'vat_number' => $c->vat_number,
                'phone' => $c->phone, 'email' => $c->email, 'address' => $c->fullAddress(),
            ]]),
        ]);
    }

    public function store(Request $request, LedgerPostingService $ledger)
    {
        $data = $this->validated($request);

        $voucher = DB::transaction(function () use ($data, $ledger) {
            $company = Auth::user()->company;
            $billPaymentId = null;
            $whtAmount = 0.0;

            if (! empty($data['bill_id'])) {
                $bill = Bill::findOrFail($data['bill_id']);

                // Withholding is applied once, on the first voucher paid
                // against a WHT-bearing bill — the tax due on the full
                // bill is deducted from the supplier's cash then, rather
                // than being prorated across installment payments.
                if ((float) $bill->wht_amount > 0 && ! $bill->wht_withheld) {
                    $whtAmount = (float) $bill->wht_amount;
                    $bill->wht_withheld = true;
                }

                $payment = $bill->billPayments()->create([
                    'amount' => $data['amount'] + $whtAmount,
                    'paid_at' => $data['date'],
                    'method' => $data['method'],
                    'reference' => $data['reference'] ?? null,
                ]);
                $bill->amount_paid = $bill->billPayments()->sum('amount');
                $bill->save();
                $billPaymentId = $payment->id;
            }

            $voucher = PaymentVoucher::create([
                'bank_account_id' => $data['bank_account_id'],
                'project_id' => $data['project_id'] ?? null,
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
                'wht_amount' => $whtAmount,
                'method' => $data['method'],
                'reference' => $data['reference'] ?? null,
                'notes' => $data['notes'] ?? null,
                'notes_ar' => $data['notes_ar'] ?? null,
                'status' => 'issued',
            ]);

            $ledger->postPaymentVoucher($voucher);

            return $voucher;
        });

        return redirect()->route('app.payment-vouchers.show', $voucher)->with('status', __('Payment voucher created.'));
    }

    public function show(PaymentVoucher $paymentVoucher)
    {
        $paymentVoucher->load('bankAccount', 'expense', 'bill.items', 'client', 'supplier', 'counterAccount', 'project', 'attachments');

        $template = $paymentVoucher->company->defaultTemplateFor('payment_voucher');

        return view('user.payment-vouchers.show', ['voucher' => $paymentVoucher, 'template' => $template]);
    }

    public function edit(PaymentVoucher $paymentVoucher)
    {
        if ($paymentVoucher->status === 'void') {
            return redirect()->route('app.payment-vouchers.show', $paymentVoucher)->with('status', __('A voided voucher cannot be edited.'));
        }

        $paymentVoucher->load('bill.items', 'expense');

        $company = Auth::user()->company;
        $suppliers = Supplier::orderBy('name')->get();
        $clients = Client::orderBy('name')->get();

        $expenses = Expense::whereNull('bank_account_id')->latest('expense_date')->take(100)->get();
        if ($paymentVoucher->expense && ! $expenses->contains('id', $paymentVoucher->expense_id)) {
            $expenses->push($paymentVoucher->expense);
        }

        return view('user.payment-vouchers.form', [
            'voucher' => $paymentVoucher,
            'accounts' => BankAccount::where('is_active', true)->orderBy('name')->get(),
            'expenses' => $expenses,
            'suppliers' => $suppliers,
            'clients' => $clients,
            'glAccounts' => $company->accounts()->where('is_active', true)->orderBy('code')->get(),
            'projects' => Project::orderBy('name')->get(),
            'suppliersData' => $suppliers->mapWithKeys(fn (Supplier $s) => [$s->id => [
                'name_ar' => $s->name_ar, 'name' => $s->name, 'vat_number' => $s->vat_number,
                'phone' => $s->phone, 'email' => $s->email, 'address' => $s->fullAddress(),
            ]]),
            'clientsData' => $clients->mapWithKeys(fn (Client $c) => [$c->id => [
                'name_ar' => $c->name_ar, 'name' => $c->name, 'vat_number' => $c->vat_number,
                'phone' => $c->phone, 'email' => $c->email, 'address' => $c->fullAddress(),
            ]]),
            // The bill this voucher is already linked to may no longer be
            // "outstanding" (this very voucher's payment may have fully
            // settled it) and so would be missing from the fetched
            // outstanding-bills list — pass it separately so the dropdown
            // always keeps the currently linked bill selectable.
            'currentBill' => $paymentVoucher->bill ? [
                'id' => $paymentVoucher->bill->id,
                'bill_number' => $paymentVoucher->bill->bill_number,
                'date' => $paymentVoucher->bill->bill_date->format('Y-m-d'),
                'total' => number_format((float) $paymentVoucher->bill->total, 2),
                'balance' => number_format($paymentVoucher->bill->balanceDue(), 2),
                'project_id' => $paymentVoucher->bill->project_id,
                'items' => $paymentVoucher->bill->items->map(fn ($line) => [
                    'description' => $line->description,
                    'quantity' => rtrim(rtrim(number_format((float) $line->quantity, 2), '0'), '.'),
                ])->values(),
            ] : null,
        ]);
    }

    public function update(Request $request, PaymentVoucher $paymentVoucher, LedgerPostingService $ledger)
    {
        if ($paymentVoucher->status === 'void') {
            return back()->with('status', __('A voided voucher cannot be edited.'));
        }

        $data = $this->validated($request);

        DB::transaction(function () use ($data, $paymentVoucher, $ledger) {
            // Undo the previous bill linkage's side effects, mirroring
            // void() — the bill's amount_paid/wht_withheld must reflect
            // whatever the voucher ends up looking like after this edit,
            // not what it looked like when it was first created.
            if ($paymentVoucher->bill_payment_id && $paymentVoucher->bill) {
                $oldBill = $paymentVoucher->bill;
                $oldBill->billPayments()->where('id', $paymentVoucher->bill_payment_id)->delete();
                $oldBill->amount_paid = $oldBill->billPayments()->sum('amount');

                if ((float) $paymentVoucher->wht_amount > 0) {
                    $oldBill->wht_withheld = false;
                }

                $oldBill->save();
            }

            // Rebuild the ledger posting from scratch rather than adjusting
            // it in place — postPaymentVoucher() derives every line from
            // the voucher's current state, so deleting the old entry first
            // is the only way to avoid double-posting or stale amounts.
            $ledger->deletePosting($paymentVoucher->company, 'payment_voucher', $paymentVoucher->id);

            $billPaymentId = null;
            $whtAmount = 0.0;

            if (! empty($data['bill_id'])) {
                $bill = Bill::findOrFail($data['bill_id']);

                if ((float) $bill->wht_amount > 0 && ! $bill->wht_withheld) {
                    $whtAmount = (float) $bill->wht_amount;
                    $bill->wht_withheld = true;
                }

                $payment = $bill->billPayments()->create([
                    'amount' => $data['amount'] + $whtAmount,
                    'paid_at' => $data['date'],
                    'method' => $data['method'],
                    'reference' => $data['reference'] ?? null,
                ]);
                $bill->amount_paid = $bill->billPayments()->sum('amount');
                $bill->save();
                $billPaymentId = $payment->id;
            }

            $paymentVoucher->update([
                'bank_account_id' => $data['bank_account_id'],
                'project_id' => $data['project_id'] ?? null,
                'party_type' => $data['party_type'],
                'client_id' => $data['party_type'] === 'customer' ? $data['client_id'] : null,
                'supplier_id' => $data['party_type'] === 'supplier' ? $data['supplier_id'] : null,
                'counter_account_id' => $data['counter_account_id'] ?? null,
                'expense_id' => $data['expense_id'] ?? null,
                'bill_id' => $data['bill_id'] ?? null,
                'bill_payment_id' => $billPaymentId,
                'date' => $data['date'],
                'payee_name' => $data['payee_name'],
                'party_name_ar' => $data['party_name_ar'] ?? null,
                'party_vat_number' => $data['party_vat_number'] ?? null,
                'party_phone' => $data['party_phone'] ?? null,
                'party_email' => $data['party_email'] ?? null,
                'party_address' => $data['party_address'] ?? null,
                'amount' => $data['amount'],
                'wht_amount' => $whtAmount,
                'method' => $data['method'],
                'reference' => $data['reference'] ?? null,
                'notes' => $data['notes'] ?? null,
                'notes_ar' => $data['notes_ar'] ?? null,
            ]);

            $ledger->postPaymentVoucher($paymentVoucher->fresh(['bankAccount', 'counterAccount', 'bill', 'expense']));
        });

        return redirect()->route('app.payment-vouchers.show', $paymentVoucher)->with('status', __('Payment voucher updated.'));
    }

    public function destroy(PaymentVoucher $paymentVoucher)
    {
        if ($paymentVoucher->status !== 'void') {
            return back()->with('status', __('Void this voucher first so its ledger entries and any linked bill payment are safely reversed, then delete it.'));
        }

        $paymentVoucher->delete();

        return redirect()->route('app.payment-vouchers.index')->with('status', __('Payment voucher deleted.'));
    }

    public function void(PaymentVoucher $paymentVoucher, LedgerPostingService $ledger)
    {
        if ($paymentVoucher->status === 'void') {
            return back();
        }

        DB::transaction(function () use ($paymentVoucher, $ledger) {
            if ($paymentVoucher->bill_payment_id && $paymentVoucher->bill) {
                $bill = $paymentVoucher->bill;
                $bill->billPayments()->where('id', $paymentVoucher->bill_payment_id)->delete();
                $bill->amount_paid = $bill->billPayments()->sum('amount');

                if ((float) $paymentVoucher->wht_amount > 0) {
                    $bill->wht_withheld = false;
                }

                $bill->save();
            }

            $paymentVoucher->update(['status' => 'void']);

            $ledger->reverse($paymentVoucher->company, 'payment_voucher', $paymentVoucher->id, __('Payment voucher :number voided', ['number' => $paymentVoucher->voucher_number]));
        });

        return back()->with('status', __('Payment voucher voided.'));
    }

    public function storeAttachment(Request $request, PaymentVoucher $paymentVoucher)
    {
        if ($rejected = $this->rejectIfStorageQuotaReached($paymentVoucher->company)) {
            return $rejected;
        }

        $request->validate(['file' => ['required', 'file', 'mimes:pdf,jpg,jpeg,png,gif,webp,doc,docx,xls,xlsx,csv,txt', 'max:10240']]);

        $file = $request->file('file');

        $paymentVoucher->attachments()->create([
            'company_id' => $paymentVoucher->company_id,
            'uploaded_by' => Auth::id(),
            'original_name' => $file->getClientOriginalName(),
            'path' => $file->store('payment-voucher-attachments', 'public'),
            'size' => $file->getSize(),
            'mime_type' => $file->getMimeType(),
        ]);

        return back()->with('status', __('File attached.'));
    }

    public function destroyAttachment(PaymentVoucher $paymentVoucher, Attachment $attachment)
    {
        abort_unless($attachment->attachable_type === PaymentVoucher::class && $attachment->attachable_id === $paymentVoucher->id, 404);

        Storage::disk('public')->delete($attachment->path);
        $attachment->delete();

        return back()->with('status', __('Attachment removed.'));
    }

    private function validated(Request $request): array
    {
        $companyId = Auth::user()->company_id;

        return $request->validate([
            'bank_account_id' => ['required', Rule::exists('bank_accounts', 'id')->where('company_id', $companyId)],
            'party_type' => ['required', 'in:manual,customer,supplier'],
            'client_id' => ['nullable', 'required_if:party_type,customer', Rule::exists('clients', 'id')->where('company_id', $companyId)],
            'supplier_id' => ['nullable', 'required_if:party_type,supplier', Rule::exists('suppliers', 'id')->where('company_id', $companyId)],
            'counter_account_id' => ['nullable', Rule::exists('accounts', 'id')->where('company_id', $companyId)->where('is_active', true)],
            'expense_id' => ['nullable', Rule::exists('expenses', 'id')->where('company_id', $companyId)],
            'bill_id' => ['nullable', Rule::exists('bills', 'id')->where('company_id', $companyId)],
            'project_id' => ['nullable', Rule::exists('projects', 'id')->where('company_id', $companyId)],
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
            'notes_ar' => ['nullable', 'string', 'max:2000'],
        ]);
    }
}
