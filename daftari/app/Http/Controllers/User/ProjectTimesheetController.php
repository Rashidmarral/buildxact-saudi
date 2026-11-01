<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Models\ProjectTimesheet;
use App\Services\Accounting\LedgerPostingService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class ProjectTimesheetController extends Controller
{
    public function store(Request $request, Project $project)
    {
        $companyId = Auth::user()->company_id;

        $data = $request->validate([
            'employee_id' => ['required', Rule::exists('employees', 'id')->where('company_id', $companyId)],
            'task_id' => ['nullable', Rule::exists('project_tasks', 'id')->where('project_id', $project->id)],
            'work_date' => ['required', 'date'],
            'description' => ['nullable', 'string', 'max:255'],
            'hours' => ['required', 'numeric', 'min:0.01', 'max:24'],
            'overtime_hours' => ['nullable', 'numeric', 'min:0', 'max:24'],
            'bill_rate' => ['nullable', 'numeric', 'min:0'],
            'cost_rate' => ['nullable', 'numeric', 'min:0'],
        ]);

        $project->timesheets()->create($data + [
            'billable' => $request->boolean('billable', true),
            'status' => 'draft',
            'created_by' => Auth::id(),
        ]);

        return back()->with('status', __('Timesheet entry added.'));
    }

    public function approve(Project $project, ProjectTimesheet $timesheet, LedgerPostingService $ledger)
    {
        abort_unless($timesheet->project_id === $project->id, 404);
        abort_unless($timesheet->status === 'draft', 404);

        DB::transaction(function () use ($timesheet, $ledger) {
            $timesheet->update(['status' => 'approved']);
            $timesheet->postCost();

            if ($timesheet->projectCost) {
                $ledger->postProjectCostAllocation($timesheet->projectCost);
            }
        });

        return back()->with('status', __('Timesheet approved.'));
    }

    public function destroy(Project $project, ProjectTimesheet $timesheet, LedgerPostingService $ledger)
    {
        abort_unless($timesheet->project_id === $project->id, 404);

        DB::transaction(function () use ($project, $timesheet, $ledger) {
            if ($timesheet->projectCost) {
                $ledger->deletePosting($project->company, 'project_cost', $timesheet->projectCost->id);
                $timesheet->projectCost->delete();
            }
            $timesheet->delete();
        });

        return back()->with('status', __('Timesheet entry removed.'));
    }
}
