<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Employee;
use App\Models\LeaveRequest;
use App\Services\Payroll\LeaveRequestService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use RuntimeException;

class LeaveRequestController extends Controller
{
    public function index(LeaveRequestService $service)
    {
        $employees = Employee::where('status', 'active')->orderBy('full_name')->get();
        $balances = $employees->mapWithKeys(fn (Employee $e) => [$e->id => $service->balance($e)]);
        $requests = LeaveRequest::with(['employee', 'approver'])->latest('start_date')->get();

        return view('user.leave-requests.index', compact('employees', 'balances', 'requests'));
    }

    public function store(Request $request, LeaveRequestService $service)
    {
        $companyId = Auth::user()->company_id;

        $data = $request->validate([
            'employee_id' => ['required', Rule::exists('employees', 'id')->where('company_id', $companyId)],
            'type' => ['required', 'in:annual,sick,unpaid'],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
            'reason' => ['nullable', 'string', 'max:255'],
        ]);

        $employee = Employee::findOrFail($data['employee_id']);

        try {
            $service->createRequest($employee, $data);
        } catch (RuntimeException $e) {
            return back()->withErrors(['leave' => $e->getMessage()]);
        }

        return back()->with('status', __('Leave request submitted.'));
    }

    public function approve(LeaveRequest $leaveRequest, LeaveRequestService $service)
    {
        try {
            $service->approve($leaveRequest, Auth::id());
        } catch (RuntimeException $e) {
            return back()->withErrors(['leave' => $e->getMessage()]);
        }

        AuditLog::record('leave_request.approve', $leaveRequest, __('Approved :days day(s) :type leave for :name', [
            'days' => $leaveRequest->days, 'type' => $leaveRequest->type, 'name' => $leaveRequest->employee->full_name,
        ]));

        return back()->with('status', __('Leave request approved.'));
    }

    public function reject(LeaveRequest $leaveRequest, LeaveRequestService $service)
    {
        try {
            $service->reject($leaveRequest);
        } catch (RuntimeException $e) {
            return back()->withErrors(['leave' => $e->getMessage()]);
        }

        AuditLog::record('leave_request.reject', $leaveRequest, __('Rejected :days day(s) :type leave for :name', [
            'days' => $leaveRequest->days, 'type' => $leaveRequest->type, 'name' => $leaveRequest->employee->full_name,
        ]));

        return back()->with('status', __('Leave request rejected.'));
    }
}
