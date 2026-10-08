<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Http\Controllers\User\Concerns\ResolvesPerPage;
use App\Models\AuditLog;
use App\Models\Employee;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * A short, purpose-built way to add an equipment operator/driver — a
 * thin wrapper around Employee rather than a duplicate HR form: creates
 * an Employee flagged is_operator=true with the few fields that matter
 * for a driver (license, contact), so they're immediately selectable on
 * machinery assets and rental contracts. Anything beyond the basics
 * (salary, GOSI, bank) is edited through the real Employee form, which
 * already carries the same is_operator toggle (see EmployeeController) —
 * so nothing here duplicates that form or its validation surface.
 */
class MachineryOperatorController extends Controller
{
    use ResolvesPerPage;

    public function index(Request $request)
    {
        $operators = Employee::where('is_operator', true)->orderBy('full_name')->paginate($this->resolvePerPage($request))->withQueryString();

        return view('user.machinery.operators.index', compact('operators'));
    }

    public function create()
    {
        return view('user.machinery.operators.form');
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'full_name' => ['required', 'string', 'max:255'],
            'full_name_ar' => ['nullable', 'string', 'max:255'],
            'national_id' => ['nullable', 'string', 'max:20'],
            'mobile' => ['nullable', 'string', 'max:30'],
            'license_number' => ['nullable', 'string', 'max:30'],
            'license_expiry_date' => ['nullable', 'date'],
            'hire_date' => ['required', 'date'],
        ]);

        $company = Auth::user()->company;

        $employee = Employee::create($data + [
            'company_id' => $company->id,
            'employee_number' => $company->nextEmployeeNumber(),
            'job_title' => 'Equipment Operator',
            'is_operator' => true,
            'basic_salary' => 0,
            'status' => 'active',
        ]);

        AuditLog::record('employee.create', $employee, __('Added operator :name', ['name' => $employee->full_name]));

        return redirect()->route('app.machinery.operators.index')->with('status', __('Operator added. Edit from Employees to add salary/GOSI details if needed.'));
    }
}
