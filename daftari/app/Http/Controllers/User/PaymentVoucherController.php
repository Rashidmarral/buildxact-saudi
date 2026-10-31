<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Http\Controllers\User\Concerns\EnforcesApprovalSeparation;
use App\Http\Controllers\User\Concerns\EnforcesStorageQuota;
use App\Http\Controllers\User\Concerns\GroupsVouchersByPayee;
use App\Http\Controllers\User\Concerns\ResolvesPerPage;
use App\Models\Attachment;
use App\Models\AuditLog;
use App\Models\BankAccount;
use App\Models\Bill;
use App\Models\Client;
use App\Models\Employee;
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
    use EnforcesApprovalSeparation, EnforcesStorageQuota, GroupsVouchersByPayee, ResolvesPerPage;

    /**
     * "there is no option to get total amount paid" for a supplier across
     * however many separate vouchers were issued to them — adds
     * supplier/client/date filters plus a total over exactly what's
     * filtered (status='issued' only, matching every other total in this
     * app — a voided voucher never counted as real money paid).
     */
    public function index(Request $request)
    {
        $search = trim((string) $request->query('q'));

        $query = PaymentVoucher::with('bankAccount', 'client', 'supplier')
            ->when($request->filled('supplier_id'), fn ($q) => $q->where('supplier_id', $request->integer('supplier_id')))
            ->when($request->filled('client_id'), fn ($q) => $q->where('client_id', $request->integer('client_id')))
            ->when($request->filled('from'), fn ($q) => $q->whereDate('date', '>=', $request->query('from')))
            ->when($request->filled('to'), fn ($q) => $q->whereDate('date', '<=', $request->query('to')))
            ->when($search !== '', fn ($q) => $this->applyPayeeSearch($q, $search));

        $total = (clone $query)->where('status', 'issued')->sum('amount');

        $vouchers = $query->latest('date')->latest('id')->paginate($this->resolvePerPage($request))->withQueryString();

        return view('user.payment-vouchers.index', [
            'vouchers' => $vouchers,
            'total' => $total,
            'suppliers' => Supplier::orderBy('name')->get(),
            'clients' => Client::orderBy('name')->get(),
            'filters' => $request->only('supplier_id', 'client_id', 'from', 'to', 'q'),
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
        $search = trim((string) $request->query('q'));
        $searchLabel = trim((string) $request->query('label')) ?: $search;

        $vouchers = PaymentVoucher::with('bankAccount')
            ->where('status', 'issued')
            ->when($request->filled('supplier_id'), fn ($q) => $q->where('supplier_id', $request->integer('supplier_id')))
            ->when($request->filled('client_id'), fn ($q) => $q->where('client_id', $request->integer('client_id')))
            ->when($request->filled('from'), fn ($q) => $q->whereDate('date', '>=', $request->query('from')))
            ->when($request->filled('to'), fn ($q) => $q->whereDate('date', '<=', $request->query('to')))
            ->when($search !== '', fn ($q) => $this->applyPayeeSearch($q, $search))
            ->orderBy('date')
            ->get();

        $party = $request->filled('supplier_id') ? Supplier::find($request->integer('supplier_id')) : Client::find($request->integer('client_id'));
        $groups = $this->buildVoucherGroups($request, $vouchers);

        $pdf = $renderer->render('documents.print.voucher-summary-pdf', [
            'type' => 'payment',
            'company' => $company,
            'party' => $party,
            'vouchers' => $vouchers,
            'total' => (float) $vouchers->sum('amount'),
            'from' => $request->query('from'),
            'to' => $request->query('to'),
            'search' => $searchLabel,
            'groups' => $groups,
            'template' => $company->defaultTemplateFor('voucher_summary'),
        ]);

        $filenameSuffix = $search !== '' ? '-'.\Illuminate\Support\Str::slug($searchLabel) : ($groups !== null ? '-grouped' : '');

        return response($pdf, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="'.\Illuminate\Support\Str::slug($party?->name ?: 'payment-summary').$filenameSuffix.'-payment-summary.pdf"',
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

    public function create(Request $request)
    {
        $company = Auth::user()->company;
        $suppliers = Supplier::orderBy('name')->get();
        $clients = Client::orderBy('name')->get();
        $employees = Employee::where('status', 'active')->orderBy('full_name')->get();

        // Arriving from the Owner Reimbursements "Settle" link: prefill the
        // voucher from the chosen (still-unpaid) expense, the same way
        // selecting a bill above already fills in its amount. Deliberately
        // NOT passed as 'voucher' — the form uses isset($voucher) to tell
        // create and edit apart (an edit posts to .update with a real,
        // persisted voucher's route key), so a prefill has to ride in its
        // own variable instead.
        $prefillExpense = $request->filled('expense_id')
            ? Expense::whereNull('bank_account_id')->find($request->integer('expense_id'))
            : null;

        return view('user.payment-vouchers.form', [
            'prefillExpense' => $prefillExpense,
            'accounts' => BankAccount::where('is_active', true)->orderBy('name')->get(),
            'expenses' => Expense::whereNull('bank_account_id')->latest('expense_date')->take(100)->get(),
            'suppliers' => $suppliers,
            'clients' => $clients,
            'employees' => $employees,
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
            'employeesData' => $employees->mapWithKeys(fn (Employee $e) => [$e->id => [
                'name_ar' => $e->full_name_ar, 'name' => $e->full_name, 'vat_number' => null,
                'phone' => $e->mobile, 'email' => $e->email, 'address' => $e->address,
            ]]),
        ]);
    }

    public function store(Request $request, LedgerPostingService $ledger)
    {
        $data = $this->validated($request);
        $company = Auth::user()->company;

        // Audit finding CASH-06: at/above the threshold, nothing here
        // happens yet — no bill payment, no WHT withholding, no GL
        // posting — until approvePending() explicitly runs it. Computed
        // before the transaction so the decision can't change partway
        // through creating the voucher row.
        $requiresApproval = $company->paymentVoucherRequiresApproval((float) $data['amount']);

        $voucher = DB::transaction(function () use ($data, $ledger, $company, $requiresApproval) {
            $voucher = PaymentVoucher::create([
                'bank_account_id' => $data['bank_account_id'],
                'project_id' => $data['project_id'] ?? null,
                'party_type' => $data['party_type'],
                'client_id' => $data['party_type'] === 'customer' ? $data['client_id'] : null,
                'supplier_id' => $data['party_type'] === 'supplier' ? $data['supplier_id'] : null,
                'employee_id' => $data['party_type'] === 'employee' ? $data['employee_id'] : null,
                'counter_account_id' => $data['counter_account_id'] ?? null,
                'expense_id' => $data['expense_id'] ?? null,
                'bill_id' => $data['bill_id'] ?? null,
                'bill_payment_id' => null,
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
                'wht_amount' => 0,
                'method' => $data['method'],
                'reference' => $data['reference'] ?? null,
                'notes' => $data['notes'] ?? null,
                'notes_ar' => $data['notes_ar'] ?? null,
                'status' => $requiresApproval ? 'pending_approval' : 'issued',
            ]);

            if ($requiresApproval) {
                AuditLog::record('payment_voucher.create_pending', $voucher, __('Submitted payment voucher :number for approval (amount :amount)', ['number' => $voucher->voucher_number, 'amount' => $voucher->amount]));
            } else {
                $this->settleBillAndPost($voucher, $ledger);
            }

            return $voucher;
        });

        $status = $requiresApproval
            ? __('This payment is :amount, at or above your approval threshold — it has been submitted for approval instead of posted.', ['amount' => $voucher->amount])
            : __('Payment voucher created.');

        return redirect()->route('app.payment-vouchers.show', $voucher)->with('status', $status);
    }

    /**
     * The side effects that used to happen unconditionally inside
     * store() — WHT withholding, the linked bill's own BillPayment, and
     * the GL posting itself. Shared with approvePending() so a
     * threshold-gated voucher gets exactly the same treatment once
     * approved as an immediate one gets today, just deferred.
     */
    private function settleBillAndPost(PaymentVoucher $voucher, LedgerPostingService $ledger): void
    {
        if ($voucher->bill_id && ! $voucher->bill_payment_id) {
            $bill = Bill::findOrFail($voucher->bill_id);

            // Withholding is applied once, on the first voucher paid
            // against a WHT-bearing bill — the tax due on the full bill
            // is deducted from the supplier's cash then, rather than
            // being prorated across installment payments.
            $whtAmount = 0.0;
            if ((float) $bill->wht_amount > 0 && ! $bill->wht_withheld) {
                $whtAmount = (float) $bill->wht_amount;
                $bill->wht_withheld = true;
            }

            $payment = $bill->billPayments()->create([
                'amount' => $voucher->amount + $whtAmount,
                'paid_at' => $voucher->date,
                'method' => $voucher->method,
                'reference' => $voucher->reference,
            ]);
            $bill->amount_paid = $bill->billPayments()->sum('amount');
            $bill->save();

            $voucher->bill_payment_id = $payment->id;
            $voucher->wht_amount = $whtAmount;
        }

        $voucher->status = 'issued';
        $voucher->save();

        $ledger->postPaymentVoucher($voucher->fresh(['bankAccount', 'counterAccount', 'bill', 'expense']));
    }

    public function approvePending(PaymentVoucher $paymentVoucher, LedgerPostingService $ledger)
    {
        abort_unless($paymentVoucher->status === 'pending_approval', 404);

        // Audit finding SEC-06: the preparer of this voucher must not also
        // be its approver — see EnforcesApprovalSeparation.
        if ($this->approvalBlockedBySelfApproval($paymentVoucher->created_by, $paymentVoucher->company_id)) {
            return back()->withErrors(['approve' => __('You submitted this voucher — it must be approved by a different user.')]);
        }

        DB::transaction(function () use ($paymentVoucher, $ledger) {
            $this->settleBillAndPost($paymentVoucher, $ledger);
            $paymentVoucher->update(['approved_by' => Auth::id(), 'approved_at' => now()]);
        });

        AuditLog::record('payment_voucher.approve_pending', $paymentVoucher, __('Approved and posted payment voucher :number', ['number' => $paymentVoucher->voucher_number]));

        return redirect()->route('app.payment-vouchers.show', $paymentVoucher)->with('status', __('Payment voucher approved and posted.'));
    }

    public function rejectPending(Request $request, PaymentVoucher $paymentVoucher)
    {
        abort_unless($paymentVoucher->status === 'pending_approval', 404);

        $data = $request->validate(['rejection_reason' => ['nullable', 'string', 'max:1000']]);

        $paymentVoucher->update([
            'status' => 'rejected', 'approved_by' => Auth::id(), 'approved_at' => now(),
            'rejection_reason' => $data['rejection_reason'] ?? null,
        ]);

        AuditLog::record('payment_voucher.reject_pending', $paymentVoucher, __('Rejected pending payment voucher :number', ['number' => $paymentVoucher->voucher_number]));

        return redirect()->route('app.payment-vouchers.show', $paymentVoucher)->with('status', __('Payment voucher rejected.'));
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

        if (in_array($paymentVoucher->status, ['pending_approval', 'rejected'], true)) {
            return redirect()->route('app.payment-vouchers.show', $paymentVoucher)->with('status', __('A voucher that is awaiting approval or was rejected cannot be edited.'));
        }

        $paymentVoucher->load('bill.items', 'expense');

        $company = Auth::user()->company;
        $suppliers = Supplier::orderBy('name')->get();
        $clients = Client::orderBy('name')->get();
        $employees = Employee::where('status', 'active')->orderBy('full_name')->get();
        if ($paymentVoucher->employee && ! $employees->contains('id', $paymentVoucher->employee_id)) {
            $employees->push($paymentVoucher->employee);
        }

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
            'employees' => $employees,
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
            'employeesData' => $employees->mapWithKeys(fn (Employee $e) => [$e->id => [
                'name_ar' => $e->full_name_ar, 'name' => $e->full_name, 'vat_number' => null,
                'phone' => $e->mobile, 'email' => $e->email, 'address' => $e->address,
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

        if (in_array($paymentVoucher->status, ['pending_approval', 'rejected'], true)) {
            return back()->with('status', __('A voucher that is awaiting approval or was rejected cannot be edited.'));
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
                'employee_id' => $data['party_type'] === 'employee' ? $data['employee_id'] : null,
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
        if (! in_array($paymentVoucher->status, ['void', 'rejected'], true)) {
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
            'party_type' => ['required', 'in:manual,customer,supplier,employee'],
            'client_id' => ['nullable', 'required_if:party_type,customer', Rule::exists('clients', 'id')->where('company_id', $companyId)],
            'supplier_id' => ['nullable', 'required_if:party_type,supplier', Rule::exists('suppliers', 'id')->where('company_id', $companyId)],
            'employee_id' => ['nullable', 'required_if:party_type,employee', Rule::exists('employees', 'id')->where('company_id', $companyId)],
            'counter_account_id' => ['nullable', Rule::exists('accounts', 'id')->where('company_id', $companyId)->where('is_active', true)],
            'expense_id' => ['nullable', Rule::exists('expenses', 'id')->where('company_id', $companyId)],
            // Audit finding GL-05: a PaymentVoucher has no currency of its
            // own — ->amount is always a base-currency figure, matching
            // exactly what postPaymentVoucher() posts to the GL with no
            // conversion. settleBillAndPost() below increments the
            // linked bill's amount_paid by that same base-currency
            // number directly, which is only correct when the bill is
            // ALSO in the base currency — a foreign-currency bill's
            // amount_paid (compared against its own foreign-currency
            // total) would otherwise be silently polluted with a number
            // in the wrong currency entirely. Rejected here, the same way
            // ZatcaSyncService already restricts e-invoicing sync to
            // base-currency documents only, rather than guessing at a
            // conversion.
            'bill_id' => ['nullable', Rule::exists('bills', 'id')->where('company_id', $companyId), function ($attribute, $value, $fail) use ($companyId) {
                if (! $value) {
                    return;
                }
                $bill = Bill::where('company_id', $companyId)->find($value);
                if ($bill && $bill->currency !== Auth::user()->company->currency) {
                    $fail(__('This bill is in a foreign currency (:currency) — payment vouchers can only settle bills in your base currency. Record this payment directly against the bill instead.', ['currency' => $bill->currency]));
                }
            }],
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
