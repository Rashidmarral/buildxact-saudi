<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Models\ProjectWorkOrder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class ProjectWorkOrderController extends Controller
{
    public function store(Request $request, Project $project)
    {
        $companyId = Auth::user()->company_id;

        $data = $request->validate([
            'kind' => ['required', Rule::in(ProjectWorkOrder::KINDS)],
            'priority' => ['required', Rule::in(ProjectWorkOrder::PRIORITIES)],
            'title' => ['required', 'string', 'max:255'],
            'machinery_asset_id' => ['nullable', Rule::exists('machinery_assets', 'id')->where('company_id', $companyId)],
            'description' => ['nullable', 'string', 'max:2000'],
            'raised_date' => ['required', 'date'],
            'scheduled_at' => ['nullable', 'date'],
            'duration_minutes' => ['nullable', 'integer', 'min:1'],
            'technician_employee_id' => ['nullable', Rule::exists('employees', 'id')->where('company_id', $companyId)],
            'site_address' => ['nullable', 'string', 'max:255'],
            'bill_amount' => ['nullable', 'numeric', 'min:0'],
        ]);

        $company = Auth::user()->company;

        $project->workOrders()->create($data + [
            'company_id' => $company->id,
            'work_order_number' => $company->nextWoNumber(),
            'billable' => $request->boolean('billable'),
            'status' => ! empty($data['technician_employee_id']) ? 'assigned' : 'open',
            'created_by' => Auth::id(),
        ]);

        return back()->with('status', __('Work order created.'));
    }

    public function update(Request $request, Project $project, ProjectWorkOrder $workOrder)
    {
        abort_unless($workOrder->project_id === $project->id, 404);

        $companyId = Auth::user()->company_id;

        $data = $request->validate([
            'scheduled_at' => ['nullable', 'date'],
            'technician_employee_id' => ['nullable', Rule::exists('employees', 'id')->where('company_id', $companyId)],
            'status' => ['required', Rule::in(ProjectWorkOrder::STATUSES)],
        ]);

        if (! empty($data['technician_employee_id']) && $workOrder->status === 'open' && $data['status'] === 'open') {
            $data['status'] = 'assigned';
        }

        $workOrder->update($data);

        return back()->with('status', __('Work order updated.'));
    }

    public function destroy(Project $project, ProjectWorkOrder $workOrder)
    {
        abort_unless($workOrder->project_id === $project->id, 404);

        $workOrder->delete();

        return back()->with('status', __('Work order removed.'));
    }
}
