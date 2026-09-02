<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\BankAccount;
use App\Models\Client;
use App\Models\Item;
use App\Models\Project;
use App\Models\RecurringInvoice;
use App\Models\RecurringInvoiceItem;
use App\Models\Salesperson;
use App\Models\TaxRate;
use App\Models\Unit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class RecurringInvoiceController extends Controller
{
    public function index()
    {
        $recurringInvoices = RecurringInvoice::with('client')
            ->orderByDesc('id')
            ->paginate(20);

        return view('user.recurring-invoices.index', compact('recurringInvoices'));
    }

    public function create()
    {
        return view('user.recurring-invoices.form', [
            'recurringInvoice' => new RecurringInvoice(['start_date' => now()->toDateString(), 'due_days' => 30]),
            'clients' => Client::orderBy('name')->get(),
            'items' => Item::where('is_active', true)->with('baseUnit', 'itemUnits.unit')->orderBy('name')->get(),
            'units' => Unit::orderBy('name')->get(),
            'salespersons' => Salesperson::where('is_active', true)->orderBy('name')->get(),
            'projects' => Project::orderBy('name')->get(),
            'bankAccounts' => BankAccount::where('is_active', true)->orderBy('name')->get(),
        ]);
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);

        $recurringInvoice = DB::transaction(function () use ($data) {
            $company = Auth::user()->company;

            $recurringInvoice = RecurringInvoice::create([
                'client_id' => $data['client_id'],
                'branch_id' => $company->default_branch_id,
                'salesperson_id' => $data['salesperson_id'] ?? null,
                'project_id' => $data['project_id'] ?? null,
                'bank_account_id' => $data['bank_account_id'] ?? null,
                'created_by' => Auth::id(),
                'title' => $data['title'],
                'type' => $data['type'],
                'frequency' => $data['frequency'],
                'due_days' => $data['due_days'],
                'start_date' => $data['start_date'],
                'next_run_date' => $data['start_date'],
                'end_date' => $data['end_date'] ?? null,
                'discount_total' => $data['discount_total'] ?? 0,
                'retention_rate' => $data['retention_rate'] ?? 0,
                'notes' => $data['notes'] ?? null,
            ]);

            $this->syncItems($recurringInvoice, $data['items']);

            return $recurringInvoice;
        });

        AuditLog::record('recurring_invoice.create', $recurringInvoice, __('Created recurring invoice ":title"', ['title' => $recurringInvoice->title]));

        return redirect()->route('app.recurring-invoices.index')->with('status', __('Recurring invoice created.'));
    }

    public function edit(RecurringInvoice $recurringInvoice)
    {
        return view('user.recurring-invoices.form', [
            'recurringInvoice' => $recurringInvoice,
            'clients' => Client::orderBy('name')->get(),
            'items' => Item::where('is_active', true)->with('baseUnit', 'itemUnits.unit')->orderBy('name')->get(),
            'units' => Unit::orderBy('name')->get(),
            'salespersons' => Salesperson::where('is_active', true)->orderBy('name')->get(),
            'projects' => Project::orderBy('name')->get(),
            'bankAccounts' => BankAccount::where('is_active', true)->orderBy('name')->get(),
        ]);
    }

    public function update(Request $request, RecurringInvoice $recurringInvoice)
    {
        $data = $this->validated($request);

        DB::transaction(function () use ($recurringInvoice, $data) {
            $recurringInvoice->update([
                'client_id' => $data['client_id'],
                'salesperson_id' => $data['salesperson_id'] ?? null,
                'project_id' => $data['project_id'] ?? null,
                'bank_account_id' => $data['bank_account_id'] ?? null,
                'title' => $data['title'],
                'type' => $data['type'],
                'frequency' => $data['frequency'],
                'due_days' => $data['due_days'],
                'end_date' => $data['end_date'] ?? null,
                'discount_total' => $data['discount_total'] ?? 0,
                'retention_rate' => $data['retention_rate'] ?? 0,
                'notes' => $data['notes'] ?? null,
            ]);

            $recurringInvoice->items()->delete();
            $this->syncItems($recurringInvoice, $data['items']);
        });

        AuditLog::record('recurring_invoice.update', $recurringInvoice, __('Updated recurring invoice ":title"', ['title' => $recurringInvoice->title]));

        return redirect()->route('app.recurring-invoices.index')->with('status', __('Recurring invoice updated.'));
    }

    public function pause(RecurringInvoice $recurringInvoice)
    {
        $recurringInvoice->update(['status' => 'paused']);

        AuditLog::record('recurring_invoice.pause', $recurringInvoice, __('Paused recurring invoice ":title"', ['title' => $recurringInvoice->title]));

        return back()->with('status', __('Recurring invoice paused.'));
    }

    public function resume(RecurringInvoice $recurringInvoice)
    {
        $recurringInvoice->update([
            'status' => 'active',
            'next_run_date' => $recurringInvoice->next_run_date->isPast() ? now()->toDateString() : $recurringInvoice->next_run_date,
        ]);

        AuditLog::record('recurring_invoice.resume', $recurringInvoice, __('Resumed recurring invoice ":title"', ['title' => $recurringInvoice->title]));

        return back()->with('status', __('Recurring invoice resumed.'));
    }

    public function destroy(RecurringInvoice $recurringInvoice)
    {
        $title = $recurringInvoice->title;
        $recurringInvoice->delete();

        AuditLog::record('recurring_invoice.delete', null, __('Deleted recurring invoice ":title"', ['title' => $title]));

        return redirect()->route('app.recurring-invoices.index')->with('status', __('Recurring invoice deleted.'));
    }

    private function syncItems(RecurringInvoice $recurringInvoice, array $items): void
    {
        foreach ($items as $sort => $row) {
            RecurringInvoiceItem::create([
                'recurring_invoice_id' => $recurringInvoice->id,
                'item_id' => $row['item_id'] ?? null,
                'unit_id' => $row['unit_id'] ?? null,
                'description' => $row['description'],
                'quantity' => $row['quantity'],
                'unit_price' => $row['unit_price'],
                'vat_rate' => $row['vat_rate'] ?? TaxRate::defaultRate($recurringInvoice->company_id),
                'sort_order' => $sort,
            ]);
        }
    }

    private function validated(Request $request): array
    {
        $companyId = Auth::user()->company_id;

        return $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'client_id' => ['required', Rule::exists('clients', 'id')->where('company_id', $companyId)],
            'bank_account_id' => ['nullable', Rule::exists('bank_accounts', 'id')->where('company_id', $companyId)],
            'salesperson_id' => ['nullable', Rule::exists('salespersons', 'id')->where('company_id', $companyId)],
            'project_id' => ['nullable', Rule::exists('projects', 'id')->where('company_id', $companyId)],
            'type' => ['required', 'in:standard,simplified'],
            'frequency' => ['required', Rule::in(RecurringInvoice::FREQUENCIES)],
            'due_days' => ['required', 'integer', 'min:0', 'max:365'],
            'start_date' => ['required', 'date'],
            'end_date' => ['nullable', 'date', 'after:start_date'],
            'discount_total' => ['nullable', 'numeric', 'min:0'],
            'retention_rate' => ['nullable', 'numeric', 'min:0', 'max:100'],
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
