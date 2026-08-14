<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Models\Project;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class ProjectController extends Controller
{
    public function index(Request $request)
    {
        $projects = Project::with('client')
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->query('status')))
            ->orderByDesc('id')
            ->get();

        return view('user.projects.index', compact('projects'));
    }

    public function create()
    {
        $company = Auth::user()->company;

        return view('user.projects.form', [
            'project' => new Project(['status' => 'active']),
            'clients' => Client::orderBy('name')->get(),
            'nextCodePreview' => $company->project_prefix.'_'.str_pad((string) $company->next_project_number, 6, '0', STR_PAD_LEFT),
        ]);
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);
        $company = Auth::user()->company;

        $project = $company->projects()->create($data + [
            'code' => ($data['code'] ?? null) ?: $company->nextProjectCode(),
            'created_by' => Auth::id(),
        ]);

        return redirect()->route('app.projects.show', $project)->with('status', __('Project created.'));
    }

    public function show(Project $project)
    {
        $project->load('client', 'invoices.client', 'expenses.category');

        return view('user.projects.show', compact('project'));
    }

    public function edit(Project $project)
    {
        return view('user.projects.form', [
            'project' => $project,
            'clients' => Client::orderBy('name')->get(),
            'nextCodePreview' => null,
        ]);
    }

    public function update(Request $request, Project $project)
    {
        $data = $this->validated($request, $project->id);
        unset($data['code']);

        $project->update($data);

        return redirect()->route('app.projects.show', $project)->with('status', __('Project updated.'));
    }

    public function destroy(Project $project)
    {
        if ($project->invoices()->exists() || $project->expenses()->exists()) {
            return back()->withErrors(['project' => __('This project has linked invoices or expenses and cannot be deleted.')]);
        }

        $project->delete();

        return redirect()->route('app.projects.index')->with('status', __('Project deleted.'));
    }

    private function validated(Request $request, ?int $ignoreId = null): array
    {
        $companyId = Auth::user()->company_id;

        return $request->validate([
            'code' => [
                'nullable', 'string', 'max:30', 'regex:/^[A-Z0-9_-]+$/',
                Rule::unique('projects')->where('company_id', $companyId)->ignore($ignoreId),
            ],
            'name' => ['required', 'string', 'max:255'],
            'name_ar' => ['nullable', 'string', 'max:255'],
            'status' => ['required', 'in:active,on_hold,completed,cancelled'],
            'client_id' => ['nullable', Rule::exists('clients', 'id')->where('company_id', $companyId)],
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'target_revenue' => ['nullable', 'numeric', 'min:0'],
            'cost_ceiling' => ['nullable', 'numeric', 'min:0'],
        ]);
    }
}
