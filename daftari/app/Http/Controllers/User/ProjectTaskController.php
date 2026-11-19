<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\Project;
use App\Models\ProjectTask;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class ProjectTaskController extends Controller
{
    public function store(Request $request, Project $project)
    {
        $companyId = Auth::user()->company_id;

        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'assignee_employee_id' => ['nullable', Rule::exists('employees', 'id')->where('company_id', $companyId)],
            'due_date' => ['nullable', 'date'],
        ]);

        $project->tasks()->create($data + [
            'status' => 'open',
            'created_by' => Auth::id(),
            'sort_order' => $project->tasks()->count(),
        ]);

        return back()->with('status', __('Task added.'));
    }

    public function update(Request $request, Project $project, ProjectTask $task)
    {
        abort_unless($task->project_id === $project->id, 404);

        $data = $request->validate([
            'status' => ['required', Rule::in(ProjectTask::STATUSES)],
        ]);

        $task->update($data + [
            'completed_at' => $data['status'] === 'completed' ? now() : null,
        ]);

        return back()->with('status', __('Task updated.'));
    }

    public function destroy(Project $project, ProjectTask $task)
    {
        abort_unless($task->project_id === $project->id, 404);

        $task->delete();

        return back()->with('status', __('Task removed.'));
    }
}
