<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\MachineryHireInContract;
use App\Models\Project;
use App\Models\Supplier;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/**
 * The reverse of MachineryRentalContractController: the company HIRES
 * equipment IN from an external supplier rather than renting its own
 * equipment OUT. There is no generateInvoice() here — this side is pure
 * cost, not revenue; costs are recorded as ordinary Expenses tagged to
 * the contract (see ExpenseController, machinery_hire_in_contract_id).
 */
class MachineryHireInContractController extends Controller
{
    public function index()
    {
        $contracts = MachineryHireInContract::with('supplier')->orderByDesc('start_date')->paginate(20);

        return view('user.machinery.hire-in-contracts.index', compact('contracts'));
    }

    public function create()
    {
        return view('user.machinery.hire-in-contracts.form', [
            'contract' => new MachineryHireInContract(['start_date' => now()->toDateString()]),
            'suppliers' => Supplier::orderBy('name')->get(),
            'projects' => Project::orderBy('name')->get(),
        ]);
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);
        $data['operator_included'] = $request->boolean('operator_included');
        $company = Auth::user()->company;

        $contract = DB::transaction(fn () => MachineryHireInContract::create($data + [
            'company_id' => $company->id,
            'contract_number' => $company->nextHireInContractNumber(),
            'status' => 'active',
            'created_by' => Auth::id(),
        ]));

        AuditLog::record('machinery.hire_in.create', $contract, __('Hired in :equipment under contract :number', ['equipment' => $contract->equipment_description, 'number' => $contract->contract_number]));

        return redirect()->route('app.machinery.hire-in-contracts.show', $contract)->with('status', __('Hire-in contract created.'));
    }

    public function show(MachineryHireInContract $hireInContract)
    {
        $hireInContract->load('supplier', 'project', 'expenses', 'attachments');

        return view('user.machinery.hire-in-contracts.show', ['contract' => $hireInContract]);
    }

    public function end(Request $request, MachineryHireInContract $hireInContract)
    {
        abort_unless($hireInContract->status === 'active', 404);

        $data = $request->validate([
            'end_date' => ['required', 'date'],
            'return_condition_notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $hireInContract->update([
            'status' => 'completed',
            'end_date' => $data['end_date'],
            'return_condition_notes' => $data['return_condition_notes'] ?? null,
        ]);

        AuditLog::record('machinery.hire_in.end', $hireInContract, __('Ended hire-in contract :number', ['number' => $hireInContract->contract_number]));

        return redirect()->route('app.machinery.hire-in-contracts.show', $hireInContract)->with('status', __('Hire-in contract ended.'));
    }

    private function validated(Request $request): array
    {
        $companyId = Auth::user()->company_id;

        return $request->validate([
            'supplier_id' => ['nullable', Rule::exists('suppliers', 'id')->where('company_id', $companyId)],
            'supplier_name' => ['nullable', 'required_without:supplier_id', 'string', 'max:255'],
            'supplier_cr_number' => ['nullable', 'string', 'max:30'],
            'supplier_phone' => ['nullable', 'string', 'max:30'],
            'equipment_description' => ['required', 'string', 'max:255'],
            'equipment_category' => ['nullable', 'string', 'max:255'],
            'plate_or_chassis_number' => ['nullable', 'string', 'max:60'],
            'start_date' => ['required', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'rate' => ['required', 'numeric', 'min:0.01'],
            'rate_type' => ['required', Rule::in(['daily', 'weekly', 'monthly', 'per_unit'])],
            'rate_unit_label' => ['required_if:rate_type,per_unit', 'nullable', 'string', 'max:60'],
            'operator_included' => ['nullable', 'boolean'],
            'operator_name' => ['nullable', 'string', 'max:255'],
            'fuel_responsibility' => ['required', Rule::in(['supplier', 'company'])],
            'project_id' => ['nullable', Rule::exists('projects', 'id')->where('company_id', $companyId)],
            'delivery_condition_notes' => ['nullable', 'string', 'max:2000'],
        ]);
    }
}
