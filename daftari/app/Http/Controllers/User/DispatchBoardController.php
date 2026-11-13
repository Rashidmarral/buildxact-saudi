<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\ProjectWorkOrder;
use Carbon\Carbon;
use Illuminate\Http\Request;

/**
 * Hamly's Dispatch Board: a week grid of Technician x Day scheduling
 * Work Orders. Cross-project by design (a dispatcher schedules across
 * every project's work orders at once), unlike every other Phase 0-5
 * piece of the Projects module, which is scoped to one project's own
 * show page.
 */
class DispatchBoardController extends Controller
{
    public function index(Request $request)
    {
        $weekStart = $request->filled('week')
            ? Carbon::parse($request->query('week'))->startOfWeek(Carbon::MONDAY)
            : now()->startOfWeek(Carbon::MONDAY);
        $weekEnd = $weekStart->copy()->addDays(6);

        $days = collect(range(0, 6))->map(fn ($i) => $weekStart->copy()->addDays($i));

        $workOrders = ProjectWorkOrder::with('project', 'technician', 'machinery')
            ->whereBetween('scheduled_at', [$weekStart->copy()->startOfDay(), $weekEnd->copy()->endOfDay()])
            ->orderBy('scheduled_at')
            ->get();

        $technicians = Employee::where('status', 'active')->orderBy('full_name')->get();

        $unassigned = ProjectWorkOrder::with('project', 'machinery')
            ->whereNull('technician_employee_id')
            ->whereNotIn('status', ['completed', 'closed', 'cancelled'])
            ->orderBy('scheduled_at')
            ->get();

        return view('user.dispatch-board.index', [
            'weekStart' => $weekStart,
            'weekEnd' => $weekEnd,
            'days' => $days,
            'workOrders' => $workOrders,
            'technicians' => $technicians,
            'unassigned' => $unassigned,
        ]);
    }
}
