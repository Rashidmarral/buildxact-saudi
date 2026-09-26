<?php

namespace App\Services\Payroll;

use App\Models\Employee;
use App\Models\LeaveRequest;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * The persisted half of leave tracking: LeaveAccrualCalculator says how
 * many days an employee has earned as of any date; this turns that into
 * an actual balance (earned minus approved annual leave already taken)
 * and enforces it — an employee can't request, and an approver can't
 * approve, more annual leave than the balance actually covers.
 */
class LeaveRequestService
{
    public function __construct(private LeaveAccrualCalculator $calculator) {}

    /**
     * @return array{accrued: float, taken: float, balance: float}
     */
    public function balance(Employee $employee, ?Carbon $asOf = null): array
    {
        $asOf ??= now();
        $accrued = $this->calculator->accruedDays($employee->hire_date, $asOf);

        $taken = (float) LeaveRequest::where('employee_id', $employee->id)
            ->where('type', 'annual')
            ->where('status', 'approved')
            ->where('start_date', '<=', $asOf)
            ->sum('days');

        return ['accrued' => $accrued, 'taken' => $taken, 'balance' => round($accrued - $taken, 2)];
    }

    /**
     * @param  array{type: string, start_date: string, end_date: string, reason?: string|null}  $data
     */
    public function createRequest(Employee $employee, array $data): LeaveRequest
    {
        $start = Carbon::parse($data['start_date']);
        $end = Carbon::parse($data['end_date']);

        if ($end->lessThan($start)) {
            throw new RuntimeException(__('The end date must be on or after the start date.'));
        }

        $days = $start->diffInDays($end) + 1;

        if ($data['type'] === 'annual') {
            $balance = $this->balance($employee, $start)['balance'];

            if ($days > $balance) {
                throw new RuntimeException(__('This request (:days days) exceeds :name\'s available annual leave balance (:balance days).', [
                    'days' => $days, 'name' => $employee->full_name, 'balance' => $balance,
                ]));
            }
        }

        return LeaveRequest::create([
            'company_id' => $employee->company_id,
            'employee_id' => $employee->id,
            'type' => $data['type'],
            'start_date' => $start,
            'end_date' => $end,
            'days' => $days,
            'status' => 'pending',
            'reason' => $data['reason'] ?? null,
            'created_by' => Auth::id(),
        ]);
    }

    public function approve(LeaveRequest $request, ?int $approverId): void
    {
        DB::transaction(function () use ($request, $approverId) {
            // Re-checks against the balance under a lock — a second
            // pending request approved in between could otherwise let two
            // approvals together overdraw a balance neither one alone
            // would have (same race-condition discipline as this
            // session's checkout row-locking fix).
            $request = LeaveRequest::whereKey($request->id)->lockForUpdate()->firstOrFail();

            if (! $request->isPending()) {
                throw new RuntimeException(__('This request has already been reviewed.'));
            }

            if ($request->type === 'annual') {
                $balance = $this->balance($request->employee, $request->start_date)['balance'];

                if ((float) $request->days > $balance) {
                    throw new RuntimeException(__('Approving this would exceed the employee\'s available annual leave balance (:balance days).', ['balance' => $balance]));
                }
            }

            $request->update(['status' => 'approved', 'approved_by' => $approverId, 'approved_at' => now()]);
        });
    }

    public function reject(LeaveRequest $request): void
    {
        if (! $request->isPending()) {
            throw new RuntimeException(__('This request has already been reviewed.'));
        }

        $request->update(['status' => 'rejected', 'approved_by' => Auth::id(), 'approved_at' => now()]);
    }
}
