<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Http\Controllers\User\Concerns\EnforcesStorageQuota;
use App\Models\Account;
use App\Models\Attachment;
use App\Models\AuditLog;
use App\Models\BankAccount;
use App\Models\Employee;
use App\Models\Invoice;
use App\Models\MachineryAsset;
use App\Services\Accounting\FixedAssetLifecycleService;
use App\Services\MachineryInvoiceDraftingService;
use App\Services\MpdfRenderer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/**
 * The Machinery & Equipment module's asset registry. A machine's cost,
 * depreciation, and disposal accounting are handled entirely by the
 * linked FixedAsset via FixedAssetLifecycleService (see that class'
 * docblock) — this controller only manages the machinery-specific facts
 * and the rent/deploy/sell actions that move a machine between states.
 */
class MachineryAssetController extends Controller
{
    use EnforcesStorageQuota;

    public function index()
    {
        $assets = MachineryAsset::with('fixedAsset', 'operator')->orderBy('status')->orderBy('name')->paginate(20);

        return view('user.machinery.assets.index', compact('assets'));
    }

    public function create()
    {
        return view('user.machinery.assets.form', [
            'asset' => new MachineryAsset(['status' => 'available']),
            'glAccounts' => Account::where('is_active', true)->orderBy('code')->get(),
            'bankAccounts' => BankAccount::where('is_active', true)->orderBy('name')->get(),
            'employees' => Employee::where('status', 'active')->orderBy('full_name')->get(),
        ]);
    }

    public function store(Request $request, FixedAssetLifecycleService $lifecycle)
    {
        $data = $this->validated($request);
        $company = Auth::user()->company;

        $asset = DB::transaction(function () use ($data, $company, $lifecycle) {
            $fixedAsset = $lifecycle->acquire($company, [
                'company_id' => $company->id,
                'created_by' => Auth::id(),
                'account_id' => $data['account_id'] ?? null,
                'bank_account_id' => $data['bank_account_id'] ?? null,
                'name' => $data['name'],
                'category' => $data['category'] ?? null,
                'acquisition_date' => $data['acquisition_date'],
                'acquisition_cost' => $data['acquisition_cost'],
                'salvage_value' => $data['salvage_value'] ?? 0,
                'useful_life_years' => $data['useful_life_years'],
                'notes' => $data['notes'] ?? null,
            ]);

            return MachineryAsset::create([
                'company_id' => $company->id,
                'fixed_asset_id' => $fixedAsset->id,
                'asset_code' => $company->nextMachineryNumber(),
                'name' => $data['name'],
                'name_ar' => $data['name_ar'] ?? null,
                'category' => $data['category'] ?? null,
                'make' => $data['make'] ?? null,
                'model' => $data['model'] ?? null,
                'serial_number' => $data['serial_number'] ?? null,
                'plate_or_chassis_number' => $data['plate_or_chassis_number'] ?? null,
                'year_of_manufacture' => $data['year_of_manufacture'] ?? null,
                'status' => 'available',
                'default_rental_rate' => $data['default_rental_rate'] ?? null,
                'rental_rate_type' => $data['rental_rate_type'] ?? null,
                'operator_employee_id' => $data['operator_employee_id'] ?? null,
                'registration_expiry_date' => $data['registration_expiry_date'] ?? null,
                'insurance_expiry_date' => $data['insurance_expiry_date'] ?? null,
                'notes' => $data['notes'] ?? null,
                'created_by' => Auth::id(),
            ]);
        });

        AuditLog::record('machinery.create', $asset, __('Registered machinery :code', ['code' => $asset->asset_code]));

        return redirect()->route('app.machinery.assets.show', $asset)->with('status', __('Machinery registered.'));
    }

    public function show(MachineryAsset $machineryAsset)
    {
        $machineryAsset->load(
            'fixedAsset', 'operator',
            'rentalContracts.client', 'rentalContracts.operator',
            'deployments.project', 'deployments.operator',
            'expenses.category', 'invoices', 'letters', 'attachments'
        );

        return view('user.machinery.assets.show', ['asset' => $machineryAsset]);
    }

    public function edit(MachineryAsset $machineryAsset)
    {
        return view('user.machinery.assets.form', [
            'asset' => $machineryAsset,
            'glAccounts' => Account::where('is_active', true)->orderBy('code')->get(),
            'bankAccounts' => BankAccount::where('is_active', true)->orderBy('name')->get(),
            'employees' => Employee::where('status', 'active')->orderBy('full_name')->get(),
        ]);
    }

    /**
     * Only the machinery-specific facts are editable here — the linked
     * FixedAsset's cost/depreciation/disposal keeps going through its own
     * dedicated Fixed Assets screen, so this never touches the ledger.
     */
    public function update(Request $request, MachineryAsset $machineryAsset)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'name_ar' => ['nullable', 'string', 'max:255'],
            'category' => ['nullable', 'string', 'max:100'],
            'make' => ['nullable', 'string', 'max:100'],
            'model' => ['nullable', 'string', 'max:100'],
            'serial_number' => ['nullable', 'string', 'max:100'],
            'plate_or_chassis_number' => ['nullable', 'string', 'max:100'],
            'year_of_manufacture' => ['nullable', 'integer', 'min:1950', 'max:'.(now()->year + 1)],
            'default_rental_rate' => ['nullable', 'numeric', 'min:0'],
            'rental_rate_type' => ['nullable', Rule::in(['daily', 'weekly', 'monthly'])],
            'operator_employee_id' => ['nullable', Rule::exists('employees', 'id')->where('company_id', Auth::user()->company_id)],
            'registration_expiry_date' => ['nullable', 'date'],
            'insurance_expiry_date' => ['nullable', 'date'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $machineryAsset->update($data);

        AuditLog::record('machinery.update', $machineryAsset, __('Updated machinery :code', ['code' => $machineryAsset->asset_code]));

        return redirect()->route('app.machinery.assets.show', $machineryAsset)->with('status', __('Machinery updated.'));
    }

    public function destroy(MachineryAsset $machineryAsset)
    {
        if ($machineryAsset->rentalContracts()->exists() || $machineryAsset->deployments()->exists()
            || $machineryAsset->expenses()->exists() || $machineryAsset->invoices()->exists()) {
            return back()->withErrors(['machinery' => __('This machine has rental, deployment, expense, or invoice history and cannot be deleted.')]);
        }

        $machineryAsset->delete();

        AuditLog::record('machinery.delete', $machineryAsset, __('Deleted machinery :code', ['code' => $machineryAsset->asset_code]));

        return redirect()->route('app.machinery.assets.index')->with('status', __('Machinery deleted.'));
    }

    /**
     * Sells the machine: disposes the linked FixedAsset (reuses the exact
     * gain/loss GL posting used by the standalone Fixed Assets screen),
     * marks the machine sold, and optionally raises a draft sale Invoice
     * tagged to it — left as a draft so the ordinary Invoice screen's own
     * send/approval/ZATCA flow handles everything from there.
     */
    public function sell(Request $request, MachineryAsset $machineryAsset, FixedAssetLifecycleService $lifecycle, MachineryInvoiceDraftingService $invoicing)
    {
        abort_unless($machineryAsset->status !== 'sold', 404);

        $companyId = Auth::user()->company_id;

        $data = $request->validate([
            'sold_at' => ['required', 'date'],
            'sale_price' => ['required', 'numeric', 'min:0'],
            'client_id' => ['nullable', Rule::exists('clients', 'id')->where('company_id', $companyId)],
            'also_invoice' => ['nullable', 'boolean'],
        ]);

        $error = null;

        DB::transaction(function () use ($machineryAsset, $data, $lifecycle, $invoicing, &$error) {
            if ($machineryAsset->fixedAsset && $machineryAsset->fixedAsset->status === 'active') {
                $error = $lifecycle->dispose($machineryAsset->fixedAsset, (float) $data['sale_price'], new \DateTime($data['sold_at']));
            }

            if ($error) {
                return;
            }

            $machineryAsset->update(['status' => 'sold']);

            if ($data['also_invoice'] ?? false) {
                $invoicing->draftLine(
                    $machineryAsset,
                    $data['client_id'] ?? null,
                    __('Sale of machinery — :name (:code)', ['name' => $machineryAsset->name, 'code' => $machineryAsset->asset_code]),
                    (float) $data['sale_price'],
                    $data['sold_at']
                );
            }
        });

        if ($error) {
            return back()->withErrors(['disposal' => $error]);
        }

        AuditLog::record('machinery.sell', $machineryAsset, __('Sold machinery :code', ['code' => $machineryAsset->asset_code]));

        return redirect()->route('app.machinery.assets.show', $machineryAsset)->with('status', __('Machinery marked as sold.'));
    }

    public function storeAttachment(Request $request, MachineryAsset $machineryAsset)
    {
        if ($rejected = $this->rejectIfStorageQuotaReached($machineryAsset->company)) {
            return $rejected;
        }

        $request->validate(['file' => ['required', 'file', 'mimes:pdf,jpg,jpeg,png,gif,webp,doc,docx,xls,xlsx,csv,txt', 'max:10240']]);

        $file = $request->file('file');
        $machineryAsset->attachments()->create([
            'company_id' => $machineryAsset->company_id,
            'uploaded_by' => Auth::id(),
            'original_name' => $file->getClientOriginalName(),
            'path' => $file->store('machinery-attachments', 'public'),
            'size' => $file->getSize(),
            'mime_type' => $file->getMimeType(),
        ]);

        return back()->with('status', __('File attached.'));
    }

    public function destroyAttachment(MachineryAsset $machineryAsset, Attachment $attachment)
    {
        abort_unless($attachment->attachable_type === MachineryAsset::class && $attachment->attachable_id === $machineryAsset->id, 404);

        Storage::disk('public')->delete($attachment->path);
        $attachment->delete();

        return back()->with('status', __('Attachment removed.'));
    }

    public function statementPdf(MachineryAsset $machineryAsset, MpdfRenderer $renderer)
    {
        $machineryAsset->load('expenses.category', 'invoices');

        $rows = collect()
            ->concat($machineryAsset->invoices->whereNotIn('status', ['draft', 'cancelled'])->map(fn (Invoice $i) => [
                'date' => $i->issue_date, 'type' => 'revenue', 'number' => $i->invoice_number,
                'party' => $i->client?->display_name, 'in_amount' => (float) $i->total, 'out_amount' => 0.0,
            ]))
            ->concat($machineryAsset->expenses->where('status', 'approved')->map(fn ($e) => [
                'date' => $e->expense_date, 'type' => 'expense', 'number' => $e->reference,
                'party' => $e->vendor_name ?: $e->category?->name, 'in_amount' => 0.0, 'out_amount' => (float) $e->gross_amount,
            ]))
            ->sortBy(fn ($row) => $row['date']->format('Y-m-d'))
            ->values();

        $balance = 0.0;
        $rows = $rows->map(function ($row) use (&$balance) {
            $balance += $row['in_amount'] - $row['out_amount'];
            $row['balance_after'] = $balance;

            return $row;
        });

        $pdf = $renderer->render('documents.print.machinery-statement-pdf', [
            'title' => __('Machinery Statement'),
            'subject' => $machineryAsset->name.' ('.$machineryAsset->asset_code.')',
            'company' => $machineryAsset->company,
            'template' => $machineryAsset->company->defaultTemplateFor('machinery_statement'),
            'summary' => [
                'revenue' => $machineryAsset->totalRevenue(),
                'cost' => $machineryAsset->totalRunningCost(),
                'net' => $machineryAsset->netResult(),
            ],
            'rows' => $rows,
        ]);

        return response($pdf, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="'.Str::slug($machineryAsset->asset_code).'-statement.pdf"',
        ]);
    }

    private function validated(Request $request): array
    {
        $companyId = Auth::user()->company_id;

        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'name_ar' => ['nullable', 'string', 'max:255'],
            'category' => ['nullable', 'string', 'max:100'],
            'make' => ['nullable', 'string', 'max:100'],
            'model' => ['nullable', 'string', 'max:100'],
            'serial_number' => ['nullable', 'string', 'max:100'],
            'plate_or_chassis_number' => ['nullable', 'string', 'max:100'],
            'year_of_manufacture' => ['nullable', 'integer', 'min:1950', 'max:'.(now()->year + 1)],
            'default_rental_rate' => ['nullable', 'numeric', 'min:0'],
            'rental_rate_type' => ['nullable', Rule::in(['daily', 'weekly', 'monthly'])],
            'operator_employee_id' => ['nullable', Rule::exists('employees', 'id')->where('company_id', $companyId)],
            'registration_expiry_date' => ['nullable', 'date'],
            'insurance_expiry_date' => ['nullable', 'date'],
            'account_id' => ['nullable', Rule::exists('accounts', 'id')->where('company_id', $companyId)->where('is_active', true)],
            'bank_account_id' => ['nullable', Rule::exists('bank_accounts', 'id')->where('company_id', $companyId)],
            'acquisition_date' => ['required', 'date'],
            'acquisition_cost' => ['required', 'numeric', 'min:0.01'],
            'salvage_value' => ['nullable', 'numeric', 'min:0'],
            'useful_life_years' => ['required', 'integer', 'min:1', 'max:100'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);
    }
}
