<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Models\Account;
use App\Models\AccountMapping;
use App\Models\AuditLog;
use App\Models\Employee;
use App\Models\PayrollRun;
use App\Models\PayrollRunItem;
use App\Services\Accounting\LedgerPostingService;
use App\Services\MpdfRenderer;
use App\Services\Payroll\GosiCalculator;
use App\Services\Payroll\WpsSifExporter;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class PayrollRunController extends Controller
{
    public function index()
    {
        $payrollRuns = PayrollRun::orderByDesc('period_year')->orderByDesc('period_month')->paginate(20);

        return view('user.payroll.index', compact('payrollRuns'));
    }

    public function create()
    {
        $activeEmployeeCount = Employee::where('status', 'active')->count();

        return view('user.payroll.create', [
            'activeEmployeeCount' => $activeEmployeeCount,
            'nextMonth' => now()->month,
            'nextYear' => now()->year,
        ]);
    }

    /**
     * Snapshots every active employee's current salary into a new draft
     * run — a later salary change must not silently rewrite a run
     * that's already been generated (see payroll_run_items' own
     * docblock in its migration).
     */
    public function store(Request $request, GosiCalculator $gosiCalculator)
    {
        $company = Auth::user()->company;

        Account::seedSystemAccounts($company->id);
        AccountMapping::seedDefaults($company->id);

        $data = $request->validate([
            'period_month' => ['required', 'integer', 'min:1', 'max:12'],
            'period_year' => ['required', 'integer', 'min:2000', 'max:2100'],
            'pay_date' => ['required', 'date'],
        ]);

        $exists = PayrollRun::where('period_month', $data['period_month'])
            ->where('period_year', $data['period_year'])
            ->whereIn('status', ['draft', 'approved', 'paid'])
            ->exists();

        if ($exists) {
            return back()->withErrors(['period_month' => __('A payroll run already exists for this period.')])->withInput();
        }

        $employees = Employee::where('status', 'active')->get();

        if ($employees->isEmpty()) {
            return back()->withErrors(['period_month' => __('There are no active employees to run payroll for.')])->withInput();
        }

        $payrollRun = DB::transaction(function () use ($company, $data, $employees, $gosiCalculator) {
            $run = PayrollRun::create([
                'run_number' => $company->nextPayrollRunNumber(),
                'period_month' => $data['period_month'],
                'period_year' => $data['period_year'],
                'pay_date' => $data['pay_date'],
                'status' => 'draft',
                'created_by' => Auth::id(),
            ]);

            foreach ($employees as $employee) {
                $gosi = $gosiCalculator->calculate($employee);
                $gross = $employee->grossSalary();
                $net = round($gross - $gosi['employee_contribution'], 2);

                PayrollRunItem::create([
                    'payroll_run_id' => $run->id,
                    'employee_id' => $employee->id,
                    'basic_salary' => $employee->basic_salary,
                    'housing_allowance' => $employee->housing_allowance,
                    'transport_allowance' => $employee->transport_allowance,
                    'other_allowance' => $employee->other_allowance,
                    'gross_salary' => $gross,
                    'gosi_employee_contribution' => $gosi['employee_contribution'],
                    'gosi_employer_contribution' => $gosi['employer_contribution'],
                    'other_deductions' => 0,
                    'net_salary' => $net,
                    'days_worked' => 30,
                ]);
            }

            $run->recalculateTotals();

            return $run;
        });

        AuditLog::record('payroll_run.create', $payrollRun, __('Generated payroll run :number for :count employees', ['number' => $payrollRun->run_number, 'count' => $employees->count()]));

        return redirect()->route('app.payroll.show', $payrollRun)->with('status', __('Payroll run generated as a draft. Review it, then approve.'));
    }

    public function show(PayrollRun $payrollRun)
    {
        $payrollRun->load('items.employee', 'creator', 'approver');

        return view('user.payroll.show', compact('payrollRun'));
    }

    /**
     * Updates one payslip's deductions/days worked before approval —
     * the only field a draft run's items should still change (salary
     * components themselves are the employee's own snapshot at
     * generation time).
     */
    public function updateItem(Request $request, PayrollRun $payrollRun, PayrollRunItem $item)
    {
        abort_unless($item->payroll_run_id === $payrollRun->id, 404);

        if ($payrollRun->status !== 'draft') {
            return back()->withErrors(['payroll_run' => __('Only a draft run can be edited.')]);
        }

        $data = $request->validate([
            'other_deductions' => ['required', 'numeric', 'min:0'],
            'days_worked' => ['required', 'integer', 'min:0', 'max:31'],
        ]);

        $data['net_salary'] = round((float) $item->gross_salary - (float) $item->gosi_employee_contribution - $data['other_deductions'], 2);

        if ($data['net_salary'] < 0) {
            return back()->withErrors(['other_deductions' => __('Deductions cannot exceed gross salary.')]);
        }

        $item->update($data);
        $payrollRun->recalculateTotals();

        return back()->with('status', __('Payslip updated.'));
    }

    public function approve(PayrollRun $payrollRun, LedgerPostingService $ledger)
    {
        if ($payrollRun->status !== 'draft') {
            return back()->withErrors(['payroll_run' => __('Only a draft run can be approved.')]);
        }

        DB::transaction(function () use ($payrollRun, $ledger) {
            $payrollRun->update([
                'status' => 'approved',
                'approved_by' => Auth::id(),
                'approved_at' => now(),
            ]);

            $ledger->postPayrollRun($payrollRun);
        });

        AuditLog::record('payroll_run.approve', $payrollRun, __('Approved payroll run :number', ['number' => $payrollRun->run_number]));

        return back()->with('status', __('Payroll run approved and posted to the ledger.'));
    }

    public function markPaid(PayrollRun $payrollRun)
    {
        if ($payrollRun->status !== 'approved') {
            return back()->withErrors(['payroll_run' => __('Only an approved run can be marked as paid.')]);
        }

        $payrollRun->update(['status' => 'paid', 'paid_at' => now()]);

        AuditLog::record('payroll_run.paid', $payrollRun, __('Marked payroll run :number as paid', ['number' => $payrollRun->run_number]));

        return back()->with('status', __('Payroll run marked as paid.'));
    }

    public function cancel(PayrollRun $payrollRun, LedgerPostingService $ledger)
    {
        if (! in_array($payrollRun->status, ['draft', 'approved'], true)) {
            return back()->withErrors(['payroll_run' => __('A paid run cannot be cancelled.')]);
        }

        DB::transaction(function () use ($payrollRun, $ledger) {
            if ($payrollRun->status === 'approved') {
                $ledger->reverse($payrollRun->company, 'payroll_run', $payrollRun->id, __('Payroll run :number cancelled', ['number' => $payrollRun->run_number]));
            }

            $payrollRun->update(['status' => 'cancelled']);
        });

        AuditLog::record('payroll_run.cancel', $payrollRun, __('Cancelled payroll run :number', ['number' => $payrollRun->run_number]));

        return redirect()->route('app.payroll.index')->with('status', __('Payroll run cancelled.'));
    }

    public function downloadWps(PayrollRun $payrollRun, WpsSifExporter $exporter)
    {
        $sif = $exporter->export($payrollRun->company, $payrollRun);

        return response($sif, 200, [
            'Content-Type' => 'text/plain',
            'Content-Disposition' => 'attachment; filename="WPS-'.$payrollRun->run_number.'.sif"',
        ]);
    }

    public function payslipPdf(PayrollRun $payrollRun, PayrollRunItem $item, MpdfRenderer $renderer)
    {
        abort_unless($item->payroll_run_id === $payrollRun->id, 404);

        $item->loadMissing('employee');

        $pdf = $renderer->render('user.payroll.payslip-pdf', [
            'payrollRun' => $payrollRun,
            'item' => $item,
            'company' => $payrollRun->company,
        ]);

        return response($pdf, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="Payslip-'.$item->employee->employee_number.'-'.$payrollRun->run_number.'.pdf"',
        ]);
    }
}
