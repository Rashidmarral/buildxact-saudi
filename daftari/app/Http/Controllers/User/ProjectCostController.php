<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Models\ProjectCost;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class ProjectCostController extends Controller
{
    public function store(Request $request, Project $project)
    {
        $data = $request->validate([
            'cost_head' => ['required', Rule::in(Project::COST_HEADS)],
            'cost_date' => ['required', 'date'],
            'description' => ['required', 'string', 'max:255'],
            'quantity' => ['nullable', 'numeric', 'min:0'],
            'unit_cost' => ['nullable', 'numeric', 'min:0'],
            'amount' => ['required', 'numeric', 'min:0'],
            'billable' => ['nullable', 'boolean'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $project->projectCosts()->create($data + [
            'billable' => $request->boolean('billable', true),
            'created_by' => Auth::id(),
        ]);

        return back()->with('status', __('Cost added.'));
    }

    public function destroy(Project $project, ProjectCost $cost)
    {
        abort_unless($cost->project_id === $project->id, 404);

        $cost->delete();

        return back()->with('status', __('Cost removed.'));
    }
}
