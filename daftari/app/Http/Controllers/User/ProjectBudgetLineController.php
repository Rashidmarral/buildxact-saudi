<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Models\ProjectBudgetLine;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class ProjectBudgetLineController extends Controller
{
    public function store(Request $request, Project $project)
    {
        $data = $request->validate([
            'cost_head' => ['required', Rule::in(Project::COST_HEADS)],
            'description' => ['nullable', 'string', 'max:255'],
            'budgeted_amount' => ['required', 'numeric', 'min:0'],
        ]);

        $project->budgetLines()->create($data + [
            'status' => 'draft',
            'created_by' => Auth::id(),
            'sort_order' => $project->budgetLines()->count(),
        ]);

        return back()->with('status', __('Budget line added.'));
    }

    public function approve(Project $project, ProjectBudgetLine $budgetLine)
    {
        abort_unless($budgetLine->project_id === $project->id, 404);

        $budgetLine->update(['status' => 'approved']);

        return back()->with('status', __('Budget line approved.'));
    }

    public function destroy(Project $project, ProjectBudgetLine $budgetLine)
    {
        abort_unless($budgetLine->project_id === $project->id, 404);

        $budgetLine->delete();

        return back()->with('status', __('Budget line removed.'));
    }
}
