<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Client;
use App\Models\Employee;
use App\Models\MachineryAsset;
use App\Models\MachineryRentalContract;
use App\Models\Project;
use App\Services\MachineryInvoiceDraftingService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class MachineryRentalContractController extends Controller
{
    public function index()
    {
        $contracts = MachineryRentalContract::with('machinery', 'client')->orderByDesc('start_date')->paginate(20);

        return view('user.machinery.rental-contracts.index', compact('contracts'));
    }

    public function create(Request $request)
    {
        $machinery = MachineryAsset::where('status', 'available')->orderBy('name')->get();
        $selected = $request->integer('machinery_asset_id') ?: null;

        return view('user.machinery.rental-contracts.form', [
            'contract' => new MachineryRentalContract(['start_date' => now()->toDateString()]),
            'machinery' => $machinery,
            'selectedMachineryId' => $selected,
            'clients' => Client::orderBy('name')->get(),
            'employees' => Employee::where('status', 'active')->orderBy('full_name')->get(),
            'projects' => Project::orderBy('name')->get(),
        ]);
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);
        $company = Auth::user()->company;

        $machinery = MachineryAsset::findOrFail($data['machinery_asset_id']);
        abort_unless($machinery->status === 'available', 422, __('This machine is not available to rent out.'));

        $contract = DB::transaction(function () use ($data, $company, $machinery) {
            $contract = MachineryRentalContract::create($data + [
                'company_id' => $company->id,
                'contract_number' => $company->nextRentalContractNumber(),
                'status' => 'active',
                'created_by' => Auth::id(),
            ]);

            $machinery->update(['status' => 'rented_out']);

            return $contract;
        });

        AuditLog::record('machinery.rental.create', $contract, __('Rented out :code under contract :number', ['code' => $machinery->asset_code, 'number' => $contract->contract_number]));

        return redirect()->route('app.machinery.rental-contracts.show', $contract)->with('status', __('Rental contract created.'));
    }

    public function show(MachineryRentalContract $rentalContract)
    {
        $rentalContract->load('machinery.fixedAsset', 'client', 'operator', 'project', 'attachments');

        return view('user.machinery.rental-contracts.show', ['contract' => $rentalContract]);
    }

    /**
     * Raises a draft Invoice for one billing period of this contract — a
     * free-text line item (no Item record needed), tagged to the machine.
     * Left as a draft: the ordinary Invoice show page's own send/
     * approval/ZATCA flow takes it from there.
     */
    public function generateInvoice(Request $request, MachineryRentalContract $rentalContract, MachineryInvoiceDraftingService $invoicing)
    {
        $data = $request->validate([
            'period_start' => ['required', 'date'],
            'period_end' => ['required', 'date', 'after_or_equal:period_start'],
            'units' => ['required', 'numeric', 'min:0.01'],
        ]);

        $amount = round((float) $data['units'] * (float) $rentalContract->rate, 2);
        $rateTypeLabel = __(':type', ['type' => ucfirst($rentalContract->rate_type)]);

        $invoice = $invoicing->draftLine(
            $rentalContract->machinery,
            $rentalContract->client_id,
            __('Equipment rental — :name (:code) — :rate — :from to :to', [
                'name' => $rentalContract->machinery->name,
                'code' => $rentalContract->machinery->asset_code,
                'rate' => $rateTypeLabel,
                'from' => $data['period_start'],
                'to' => $data['period_end'],
            ]),
            $amount,
            $data['period_end']
        );

        AuditLog::record('machinery.rental.invoice', $rentalContract, __('Raised rental invoice :number for contract :contract', ['number' => $invoice->invoice_number, 'contract' => $rentalContract->contract_number]));

        return redirect()->route('app.invoices.show', $invoice)->with('status', __('Draft rental invoice created.'));
    }

    public function end(Request $request, MachineryRentalContract $rentalContract)
    {
        abort_unless($rentalContract->status === 'active', 404);

        $data = $request->validate([
            'end_date' => ['required', 'date'],
            'return_condition_notes' => ['nullable', 'string', 'max:2000'],
        ]);

        DB::transaction(function () use ($rentalContract, $data) {
            $rentalContract->update([
                'status' => 'completed',
                'end_date' => $data['end_date'],
                'return_condition_notes' => $data['return_condition_notes'] ?? null,
            ]);

            $rentalContract->machinery->update(['status' => 'available']);
        });

        AuditLog::record('machinery.rental.end', $rentalContract, __('Ended rental contract :number', ['number' => $rentalContract->contract_number]));

        return redirect()->route('app.machinery.rental-contracts.show', $rentalContract)->with('status', __('Rental contract ended — machine is available again.'));
    }

    private function validated(Request $request): array
    {
        $companyId = Auth::user()->company_id;

        return $request->validate([
            'machinery_asset_id' => ['required', Rule::exists('machinery_assets', 'id')->where('company_id', $companyId)],
            'client_id' => ['nullable', Rule::exists('clients', 'id')->where('company_id', $companyId)],
            'renter_name' => ['nullable', 'required_without:client_id', 'string', 'max:255'],
            'renter_phone' => ['nullable', 'string', 'max:30'],
            'start_date' => ['required', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'rate' => ['required', 'numeric', 'min:0.01'],
            'rate_type' => ['required', Rule::in(['daily', 'weekly', 'monthly'])],
            'deposit_amount' => ['nullable', 'numeric', 'min:0'],
            'operator_included' => ['nullable', 'boolean'],
            'operator_employee_id' => ['nullable', Rule::exists('employees', 'id')->where('company_id', $companyId)],
            'fuel_responsibility' => ['required', Rule::in(['owner', 'renter'])],
            'project_id' => ['nullable', Rule::exists('projects', 'id')->where('company_id', $companyId)],
            'delivery_condition_notes' => ['nullable', 'string', 'max:2000'],
        ]);
    }
}
