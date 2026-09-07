<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Http\Controllers\User\Concerns\ResolvesPerPage;
use App\Models\Account;
use App\Models\AccountMapping;
use App\Models\AuditLog;
use App\Models\Branch;
use App\Models\Employee;
use App\Models\EndOfServiceSettlement;
use App\Services\Accounting\LedgerPostingService;
use App\Services\Payroll\EndOfServiceCalculator;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class EmployeeController extends Controller
{
    use ResolvesPerPage;

    public function index(Request $request)
    {
        $query = Employee::with('branch')->orderBy('full_name');

        if ($request->filled('status')) {
            $query->where('status', $request->query('status'));
        }

        $employees = $query->paginate($this->resolvePerPage($request))->withQueryString();

        return view('user.employees.index', compact('employees'));
    }

    public function create()
    {
        return view('user.employees.form', [
            'employee' => new Employee,
            'branches' => Branch::orderBy('name')->get(),
        ]);
    }

    public function store(Request $request)
    {
        $company = Auth::user()->company;

        // Guarantees the payroll system accounts (and their mappings)
        // exist even for a company created before this feature shipped —
        // new companies already get them for free via
        // Account::seedSystemAccounts() at signup.
        Account::seedSystemAccounts($company->id);
        AccountMapping::seedDefaults($company->id);

        $data = $this->validated($request);
        $data['employee_number'] = $company->nextEmployeeNumber();

        $employee = Employee::create($data);

        AuditLog::record('employee.create', $employee, __('Added employee :name', ['name' => $employee->full_name]));

        return redirect()->route('app.employees.index')->with('status', __('Employee added.'));
    }

    public function edit(Employee $employee)
    {
        return view('user.employees.form', [
            'employee' => $employee,
            'branches' => Branch::orderBy('name')->get(),
        ]);
    }

    public function update(Request $request, Employee $employee)
    {
        $employee->update($this->validated($request, $employee));

        AuditLog::record('employee.update', $employee, __('Updated employee :name', ['name' => $employee->full_name]));

        return redirect()->route('app.employees.index')->with('status', __('Employee updated.'));
    }

    public function destroy(Employee $employee)
    {
        if ($employee->payrollRunItems()->exists()) {
            return back()->withErrors(['employee' => __('This employee has payroll history and cannot be deleted. Mark them terminated instead.')]);
        }

        $name = $employee->full_name;
        $employee->delete();

        AuditLog::record('employee.delete', null, __('Deleted employee :name', ['name' => $name]));

        return redirect()->route('app.employees.index')->with('status', __('Employee deleted.'));
    }

    /**
     * Terminating an employee also calculates their end-of-service
     * gratuity right away — HR needs that figure to plan the final
     * settlement, and it's the natural moment to record it (see
     * EndOfServiceCalculator for the Saudi Labor Law basis).
     */
    public function terminate(Request $request, Employee $employee, LedgerPostingService $ledger)
    {
        $data = $request->validate([
            'termination_date' => ['required', 'date', 'after_or_equal:'.$employee->hire_date->toDateString()],
            'reason' => ['required', 'in:resignation,termination,end_of_contract,retirement,death,disability'],
        ]);

        $employee->update([
            'status' => 'terminated',
            'termination_date' => $data['termination_date'],
        ]);

        $calculator = new EndOfServiceCalculator;
        $result = $calculator->calculate(
            (float) $employee->basic_salary,
            $employee->hire_date,
            \Illuminate\Support\Carbon::parse($data['termination_date']),
            $data['reason']
        );

        $settlement = EndOfServiceSettlement::create([
            'employee_id' => $employee->id,
            'hire_date' => $employee->hire_date,
            'termination_date' => $data['termination_date'],
            'reason' => $data['reason'],
            'years_of_service' => $result['years_of_service'],
            'last_basic_salary' => $employee->basic_salary,
            'gratuity_days' => $result['gratuity_days'],
            'gratuity_amount' => $result['gratuity_amount'],
            'entitlement_fraction' => $result['entitlement_fraction'],
            'created_by' => Auth::id(),
        ]);

        if ($settlement->gratuity_amount > 0) {
            $ledger->postEndOfServiceSettlement($settlement);
        }

        AuditLog::record('employee.terminate', $employee, __('Terminated employee :name, gratuity :amount', ['name' => $employee->full_name, 'amount' => number_format($settlement->gratuity_amount, 2)]));

        return redirect()->route('app.employees.index')->with('status', __('Employee terminated. End-of-service settlement calculated.'));
    }

    private function validated(Request $request, ?Employee $employee = null): array
    {
        $data = $request->validate([
            'branch_id' => ['nullable', 'exists:branches,id'],
            'full_name' => ['required', 'string', 'max:255'],
            'full_name_ar' => ['nullable', 'string', 'max:255'],
            'national_id' => ['nullable', 'string', 'max:20'],
            'nationality' => ['nullable', 'string', 'max:100'],
            'date_of_birth' => ['nullable', 'date'],
            'gender' => ['nullable', 'in:male,female'],
            'mobile' => ['nullable', 'string', 'max:30'],
            'email' => ['nullable', 'email', 'max:255'],
            'address' => ['nullable', 'string', 'max:255'],
            'job_title' => ['nullable', 'string', 'max:255'],
            'department' => ['nullable', 'string', 'max:255'],
            'hire_date' => ['required', 'date'],
            'iban' => ['nullable', 'string', 'max:34'],
            'bank_name' => ['nullable', 'string', 'max:255'],
            'gosi_subscription_number' => ['nullable', 'string', 'max:20'],
            'basic_salary' => ['required', 'numeric', 'min:0'],
            'housing_allowance' => ['nullable', 'numeric', 'min:0'],
            'transport_allowance' => ['nullable', 'numeric', 'min:0'],
            'other_allowance' => ['nullable', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $data['is_saudi'] = $request->boolean('is_saudi', true);

        return $data;
    }
}
