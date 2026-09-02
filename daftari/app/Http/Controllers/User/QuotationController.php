<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Http\Controllers\User\Concerns\ResolvesPerPage;
use App\Http\Controllers\User\Concerns\ExportsCsv;
use App\Mail\QuotationMail;
use App\Models\Attachment;
use App\Models\AuditLog;
use App\Models\BankAccount;
use App\Models\Client;
use App\Models\Invoice;
use App\Models\Item;
use App\Models\Quotation;
use App\Models\QuotationItem;
use App\Models\Salesperson;
use App\Models\TaxRate;
use App\Models\User;
use App\Notifications\GenericNotification;
use App\Services\MpdfRenderer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class QuotationController extends Controller
{
    use ExportsCsv, ResolvesPerPage;

    public function index(Request $request)
    {
        $query = Quotation::with('client')
            ->when($request->type, fn ($q, $type) => $q->where('type', $type))
            ->when($request->status, fn ($q, $status) => $q->where('status', $status))
            ->orderByDesc('issue_date')
            ->orderByDesc('id');

        if ($request->query('export') === 'csv') {
            return $this->csvResponse('quotations.csv', $this->csvHeader(), $query->get()->map(fn ($quotation) => $this->csvRow($quotation)));
        }

        $quotations = $query->paginate($this->resolvePerPage($request))->withQueryString();

        $counts = [
            'all' => Quotation::count(),
            'draft' => Quotation::where('status', 'draft')->count(),
            'issued' => Quotation::where('status', 'issued')->count(),
            'accepted' => Quotation::where('status', 'accepted')->count(),
            'converted' => Quotation::where('status', 'converted')->count(),
            'expired' => Quotation::where('status', 'expired')->count(),
            'rejected' => Quotation::where('status', 'rejected')->count(),
        ];

        return view('user.quotations.index', compact('quotations', 'counts'));
    }

    public function create(Request $request)
    {
        $clients = Client::orderBy('name')->get();
        $items = Item::where('is_active', true)->with('baseUnit', 'itemUnits.unit')->orderBy('name')->get();
        $salespersons = Salesperson::where('is_active', true)->orderBy('name')->get();
        $bankAccounts = BankAccount::where('is_active', true)->orderBy('name')->get();
        $company = Auth::user()->company;
        $type = $request->get('type', 'quotation') === 'proforma' ? 'proforma' : 'quotation';

        return view('user.quotations.form', [
            'quotation' => new Quotation([
                'type' => $type,
                'issue_date' => now()->toDateString(),
                'expiry_date' => now()->addDays(30)->toDateString(),
            ]),
            'clients' => $clients,
            'items' => $items,
            'salespersons' => $salespersons,
            'bankAccounts' => $bankAccounts,
            'nextNumberPreview' => $type === 'proforma'
                ? $company->proforma_prefix.'-'.str_pad((string) $company->next_proforma_number, 5, '0', STR_PAD_LEFT)
                : $company->quotation_prefix.'-'.str_pad((string) $company->next_quotation_number, 5, '0', STR_PAD_LEFT),
        ]);
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);
        $sendImmediately = $request->boolean('send_immediately');

        $quotation = DB::transaction(function () use ($data) {
            $company = Auth::user()->company;

            $quotation = Quotation::create([
                'client_id' => $data['client_id'],
                'bank_account_id' => $data['bank_account_id'] ?? null,
                'branch_id' => $company->default_branch_id,
                'salesperson_id' => $data['salesperson_id'] ?? null,
                'created_by' => Auth::id(),
                'quotation_number' => $company->nextQuotationNumber($data['type']),
                'type' => $data['type'],
                'status' => 'draft',
                'issue_date' => $data['issue_date'],
                'expiry_date' => $data['expiry_date'] ?? null,
                'discount_total' => $data['discount_total'] ?? 0,
                'currency' => $company->currency,
                'notes' => $data['notes'] ?? null,
            ]);

            $this->syncItems($quotation, $data['items']);
            $quotation->recalculateTotals();

            return $quotation;
        });

        $status = __('Quotation created.');

        if ($sendImmediately) {
            $this->requestIssue($quotation);
            $status = $quotation->fresh()->status === 'pending_approval'
                ? __('Quotation created and submitted for approval.')
                : __('Quotation created and issued.');
        }

        return redirect()->route('app.quotations.show', $quotation)->with('status', $status);
    }

    public function show(Quotation $quotation)
    {
        $quotation->load('items', 'client', 'convertedInvoice', 'bankAccount', 'attachments');
        $template = $quotation->company->defaultTemplateFor($quotation->type);

        return view('user.quotations.show', compact('quotation', 'template'));
    }

    public function downloadPdf(Quotation $quotation, MpdfRenderer $renderer)
    {
        $pdf = $renderer->render('documents.print.pdf', $this->pdfData($quotation));

        return response($pdf, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="'.$quotation->quotation_number.'.pdf"',
        ]);
    }

    public function emailQuotation(Quotation $quotation, MpdfRenderer $renderer)
    {
        $recipient = $quotation->client->email;

        if (! $recipient) {
            return back()->withErrors(['quotation' => __('This client has no email address on file. Add one on the client record first.')]);
        }

        $pdfBinary = $renderer->render('documents.print.pdf', $this->pdfData($quotation));

        Mail::to($recipient)->send(new QuotationMail($quotation, $pdfBinary));

        return back()->with('status', __('Quotation emailed to :email.', ['email' => $recipient]));
    }

    private function pdfData(Quotation $quotation): array
    {
        $quotation->loadMissing('items', 'client', 'bankAccount');

        $bankAccount = $quotation->bankAccount ?? $quotation->company->defaultBankAccount();

        $doc = [
            'type_label' => $quotation->type === 'proforma' ? __('Proforma Invoice') : __('Quotation'),
            'type_label_ar' => $quotation->type === 'proforma' ? 'فاتورة أولية' : 'عرض سعر',
            'number' => $quotation->quotation_number,
            'date_label' => __('Issued'),
            'date' => $quotation->issue_date,
            'date2_label' => __('Valid until'),
            'date2_label_ar' => 'صالح حتى',
            'date2' => $quotation->expiry_date,
            'party_label' => __('To'),
            'party_label_ar' => 'العميل',
            'party' => $quotation->client,
            'lines' => $quotation->items,
            'subtotal' => $quotation->subtotal,
            'discount_total' => $quotation->discount_total,
            'vat_total' => $quotation->vat_total,
            'total' => $quotation->total,
            'bank_account' => $bankAccount,
            'salesperson' => $quotation->salesperson,
            'notes' => $quotation->notes,
        ];

        return [
            'doc' => $doc,
            'company' => $quotation->company,
            'template' => $quotation->company->defaultTemplateFor($quotation->type),
        ];
    }

    public function edit(Quotation $quotation)
    {
        $quotation->load('items');
        $clients = Client::orderBy('name')->get();
        $items = Item::where('is_active', true)->with('baseUnit', 'itemUnits.unit')->orderBy('name')->get();
        $salespersons = Salesperson::where('is_active', true)->orderBy('name')->get();
        $bankAccounts = BankAccount::where('is_active', true)->orderBy('name')->get();

        return view('user.quotations.form', compact('quotation', 'clients', 'items', 'salespersons', 'bankAccounts'));
    }

    public function update(Request $request, Quotation $quotation)
    {
        $data = $this->validated($request);

        DB::transaction(function () use ($quotation, $data) {
            $quotation->update([
                'client_id' => $data['client_id'],
                'bank_account_id' => $data['bank_account_id'] ?? null,
                'salesperson_id' => $data['salesperson_id'] ?? null,
                'issue_date' => $data['issue_date'],
                'expiry_date' => $data['expiry_date'] ?? null,
                'discount_total' => $data['discount_total'] ?? 0,
                'notes' => $data['notes'] ?? null,
            ]);

            $quotation->items()->delete();
            $this->syncItems($quotation, $data['items']);
            $quotation->recalculateTotals();
        });

        return redirect()->route('app.quotations.show', $quotation)->with('status', __('Quotation updated.'));
    }

    public function storeAttachment(Request $request, Quotation $quotation)
    {
        $request->validate(['file' => ['required', 'file', 'mimes:pdf,jpg,jpeg,png,gif,webp,doc,docx,xls,xlsx,csv,txt', 'max:10240']]);

        $file = $request->file('file');

        $quotation->attachments()->create([
            'company_id' => $quotation->company_id,
            'uploaded_by' => Auth::id(),
            'original_name' => $file->getClientOriginalName(),
            'path' => $file->store('quotation-attachments', 'public'),
            'size' => $file->getSize(),
            'mime_type' => $file->getMimeType(),
        ]);

        return back()->with('status', __('File attached.'));
    }

    public function destroyAttachment(Quotation $quotation, Attachment $attachment)
    {
        abort_unless($attachment->attachable_type === Quotation::class && $attachment->attachable_id === $quotation->id, 404);

        Storage::disk('public')->delete($attachment->path);
        $attachment->delete();

        return back()->with('status', __('Attachment removed.'));
    }

    public function destroy(Quotation $quotation)
    {
        $quotation->delete();

        return redirect()->route('app.quotations.index')->with('status', __('Quotation deleted.'));
    }

    /**
     * Audit finding MEDIUM-15: no list page could act on more than one
     * record at a time. Exports exactly the checked rows, independent of
     * the current filter.
     */
    public function bulkExport(Request $request)
    {
        $ids = $this->validatedBulkIds($request);
        $quotations = Quotation::with('client')->whereIn('id', $ids)->orderByDesc('issue_date')->orderByDesc('id')->get();

        return $this->csvResponse('quotations-selected.csv', $this->csvHeader(), $quotations->map(fn ($quotation) => $this->csvRow($quotation)));
    }

    /**
     * Unlike the single-row destroy() above (which deletes unconditionally,
     * even a converted quotation), bulk delete adds the one guard that was
     * missing: a converted quotation already has a real invoice generated
     * from it and stays as the sales record for that conversion.
     */
    public function bulkDestroy(Request $request)
    {
        $ids = $this->validatedBulkIds($request);
        $quotations = Quotation::whereIn('id', $ids)->get();

        $deleted = 0;

        foreach ($quotations as $quotation) {
            if ($quotation->status === 'converted') {
                continue;
            }

            $quotation->delete();
            $deleted++;
        }

        $skipped = $quotations->count() - $deleted;

        return back()->with('status', $skipped > 0
            ? __(':deleted deleted, :skipped skipped — converted quotations cannot be bulk deleted.', ['deleted' => $deleted, 'skipped' => $skipped])
            : __(':deleted quotation(s) deleted.', ['deleted' => $deleted]));
    }

    private function validatedBulkIds(Request $request): array
    {
        return $request->validate(['ids' => ['required', 'array', 'min:1'], 'ids.*' => ['integer']])['ids'];
    }

    private function csvHeader(): array
    {
        return [__('Number'), __('Client'), __('Type'), __('Issue date'), __('Total'), __('Status')];
    }

    private function csvRow(Quotation $quotation): array
    {
        return [
            $quotation->quotation_number,
            $quotation->client->name,
            $quotation->type === 'proforma' ? __('Proforma Invoice') : __('Quotation'),
            $quotation->issue_date->format('Y-m-d'),
            number_format($quotation->total, 2),
            $quotation->status,
        ];
    }

    public function send(Quotation $quotation)
    {
        $this->requestIssue($quotation);

        return back()->with('status', $quotation->fresh()->status === 'pending_approval'
            ? __('Quotation submitted for approval.')
            : __('Quotation marked as issued.'));
    }

    public function approveIssuance(Quotation $quotation)
    {
        abort_unless(Auth::user()->hasPermission('approvals'), 403);
        abort_unless($quotation->status === 'pending_approval', 404);

        $quotation->update(['status' => 'issued', 'approved_by' => Auth::id(), 'approved_at' => now()]);

        AuditLog::record('quotation.approve', $quotation, __('Approved quotation :number', ['number' => $quotation->quotation_number]));

        return back()->with('status', __('Quotation approved and issued.'));
    }

    public function rejectIssuance(Request $request, Quotation $quotation)
    {
        abort_unless(Auth::user()->hasPermission('approvals'), 403);
        abort_unless($quotation->status === 'pending_approval', 404);

        $data = $request->validate(['approval_rejection_reason' => ['nullable', 'string', 'max:1000']]);

        $quotation->update([
            'status' => 'draft',
            'approved_by' => Auth::id(),
            'approved_at' => now(),
            'approval_rejection_reason' => $data['approval_rejection_reason'] ?? null,
        ]);

        AuditLog::record('quotation.reject', $quotation, __('Rejected quotation :number', ['number' => $quotation->quotation_number]));

        if ($quotation->created_by) {
            User::find($quotation->created_by)?->notify(new GenericNotification(
                title: __('Quotation rejected'),
                body: __('Quotation :number was returned to draft for revision.', ['number' => $quotation->quotation_number]),
                url: route('app.quotations.show', $quotation),
                icon: 'quotations',
            ));
        }

        return back()->with('status', __('Quotation rejected — returned to draft.'));
    }

    public function accept(Quotation $quotation)
    {
        if (in_array($quotation->status, ['issued', 'draft'])) {
            $quotation->update(['status' => 'accepted', 'accepted_at' => now(), 'accepted_by_name' => Auth::user()->name]);
        }

        return back()->with('status', __('Quotation marked as accepted.'));
    }

    public function reject(Quotation $quotation)
    {
        if (! in_array($quotation->status, ['converted'])) {
            $quotation->update(['status' => 'rejected']);
        }

        return back()->with('status', __('Quotation marked as rejected.'));
    }

    public function convertToInvoice(Quotation $quotation)
    {
        if ($quotation->status === 'converted') {
            return back()->withErrors(['quotation' => __('This quotation has already been converted.')]);
        }

        $invoice = DB::transaction(function () use ($quotation) {
            $company = Auth::user()->company;

            $invoice = Invoice::create([
                'client_id' => $quotation->client_id,
                'branch_id' => $quotation->branch_id ?? $company->default_branch_id,
                'created_by' => Auth::id(),
                'invoice_number' => $company->nextInvoiceNumber(),
                'type' => 'standard',
                'status' => 'draft',
                'issue_date' => now()->toDateString(),
                'due_date' => now()->addDays(30)->toDateString(),
                'discount_total' => $quotation->discount_total,
                'currency' => $quotation->currency,
                'notes' => $quotation->notes,
            ]);

            foreach ($quotation->items as $sort => $qItem) {
                $invoice->items()->create([
                    'item_id' => $qItem->item_id,
                    'unit_id' => $qItem->unit_id,
                    'description' => $qItem->description,
                    'quantity' => $qItem->quantity,
                    'unit_price' => $qItem->unit_price,
                    'vat_rate' => $qItem->vat_rate,
                    'vat_amount' => $qItem->vat_amount,
                    'line_total' => $qItem->line_total,
                    'sort_order' => $sort,
                ]);
            }

            $invoice->recalculateTotals();

            $quotation->update(['status' => 'converted', 'converted_invoice_id' => $invoice->id]);

            return $invoice;
        });

        return redirect()->route('app.invoices.show', $invoice)->with('status', __('Quotation converted to invoice.'));
    }

    /**
     * The gate every "issue this quotation" path funnels through: above
     * the company's quotation approval threshold, this parks it in
     * pending_approval and notifies approvers instead of issuing it.
     */
    private function requestIssue(Quotation $quotation): void
    {
        if ($quotation->status !== 'draft') {
            return;
        }

        if ($quotation->company->quotationRequiresApproval((float) $quotation->total)) {
            $quotation->update(['status' => 'pending_approval']);
            $this->notifyApprovers($quotation);

            return;
        }

        $quotation->update(['status' => 'issued']);
    }

    private function notifyApprovers(Quotation $quotation): void
    {
        User::where('company_id', $quotation->company_id)
            ->get()
            ->filter(fn (User $user) => $user->hasPermission('approvals'))
            ->each(fn (User $user) => $user->notify(new GenericNotification(
                title: __('Quotation awaiting approval'),
                body: __(':amount :currency for :client', ['amount' => number_format($quotation->total, 2), 'currency' => $quotation->currency, 'client' => $quotation->client->name]),
                url: route('app.quotations.show', $quotation),
                icon: 'quotations',
            )));
    }

    private function syncItems(Quotation $quotation, array $items): void
    {
        foreach ($items as $sort => $row) {
            $line = new QuotationItem([
                'quotation_id' => $quotation->id,
                'item_id' => $row['item_id'] ?? null,
                'unit_id' => $row['unit_id'] ?? null,
                'description' => $row['description'],
                'quantity' => $row['quantity'],
                'unit_price' => $row['unit_price'],
                'vat_rate' => $row['vat_rate'] ?? TaxRate::defaultRate($quotation->company_id),
                'sort_order' => $sort,
            ]);
            $line->recalculate();
            $line->save();
        }
    }

    private function validated(Request $request): array
    {
        $companyId = Auth::user()->company_id;

        return $request->validate([
            'client_id' => ['required', Rule::exists('clients', 'id')->where('company_id', $companyId)],
            'bank_account_id' => ['nullable', Rule::exists('bank_accounts', 'id')->where('company_id', $companyId)],
            'salesperson_id' => ['nullable', Rule::exists('salespersons', 'id')->where('company_id', $companyId)],
            'type' => ['required', 'in:quotation,proforma'],
            'issue_date' => ['required', 'date'],
            'expiry_date' => ['nullable', 'date', 'after_or_equal:issue_date'],
            'discount_total' => ['nullable', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.item_id' => ['nullable', Rule::exists('items', 'id')->where('company_id', $companyId)],
            'items.*.unit_id' => ['nullable', Rule::exists('units', 'id')->where('company_id', $companyId)],
            'items.*.description' => ['required', 'string', 'max:255'],
            'items.*.quantity' => ['required', 'numeric', 'min:0.01'],
            'items.*.unit_price' => ['required', 'numeric', 'min:0'],
            'items.*.vat_rate' => ['required', 'numeric', 'min:0', 'max:100'],
        ]);
    }
}
