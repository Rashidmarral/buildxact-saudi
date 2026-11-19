<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Models\ProjectChangeOrder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ProjectChangeOrderController extends Controller
{
    public function store(Request $request, Project $project)
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'amount' => ['required', 'numeric'],
            'cost_impact' => ['nullable', 'numeric'],
            'days_impact' => ['nullable', 'integer'],
        ]);

        $company = Auth::user()->company;

        $project->changeOrders()->create($data + [
            'change_order_number' => $company->nextChangeOrderNumber(),
            'status' => 'draft',
            'created_by' => Auth::id(),
        ]);

        return back()->with('status', __('Change order added.'));
    }

    public function approve(Project $project, ProjectChangeOrder $changeOrder)
    {
        abort_unless($changeOrder->project_id === $project->id, 404);
        abort_unless($changeOrder->status === 'draft', 404);

        $changeOrder->update(['status' => 'approved', 'reviewed_by' => Auth::id(), 'reviewed_at' => now()]);

        return back()->with('status', __('Change order approved.'));
    }

    public function reject(Project $project, ProjectChangeOrder $changeOrder)
    {
        abort_unless($changeOrder->project_id === $project->id, 404);
        abort_unless($changeOrder->status === 'draft', 404);

        $changeOrder->update(['status' => 'rejected', 'reviewed_by' => Auth::id(), 'reviewed_at' => now()]);

        return back()->with('status', __('Change order rejected.'));
    }

    public function destroy(Project $project, ProjectChangeOrder $changeOrder)
    {
        abort_unless($changeOrder->project_id === $project->id, 404);
        abort_unless($changeOrder->status === 'draft', 404);

        $changeOrder->delete();

        return back()->with('status', __('Change order removed.'));
    }
}
