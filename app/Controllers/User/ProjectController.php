<?php

namespace App\Controllers\User;

use App\Core\Auth;
use App\Core\Controller;
use App\Models\Client;
use App\Models\Estimate;
use App\Models\Invoice;
use App\Models\Project;
use App\Models\Task;

class ProjectController extends Controller
{
    public function index(): void
    {
        $companyId = Auth::companyId();
        $projects = Project::query(
            'SELECT p.*, c.name AS client_name FROM projects p LEFT JOIN clients c ON c.id = p.client_id WHERE p.company_id = ? ORDER BY p.created_at DESC',
            [$companyId]
        )->fetchAll();

        $this->view('user/projects/index', ['pageTitle' => 'Projects', 'projects' => $projects], 'layouts/app');
    }

    public function create(): void
    {
        $companyId = Auth::companyId();
        $clients = Client::where('company_id', $companyId, 'name ASC');
        $this->view('user/projects/form', ['pageTitle' => 'New Project', 'clients' => $clients, 'project' => null], 'layouts/app');
    }

    public function store(): void
    {
        $this->verifyCsrf();
        $companyId = Auth::companyId();

        $name = trim((string) $this->input('name'));
        if ($name === '') {
            $this->flash('error', 'Project name is required.');
            self::redirect('/app/projects/create');
        }

        $id = Project::create([
            'company_id' => $companyId,
            'client_id' => $this->input('client_id') ?: null,
            'name' => $name,
            'description' => $this->input('description', ''),
            'status' => $this->input('status', 'planning'),
            'budget' => (float) $this->input('budget', 0),
            'start_date' => $this->input('start_date') ?: null,
            'end_date' => $this->input('end_date') ?: null,
        ]);

        $this->flash('success', 'Project created.');
        self::redirect('/app/projects/' . $id);
    }

    public function show(string $id): void
    {
        $project = $this->findOwned((int) $id);
        $client = $project['client_id'] ? Client::find((int) $project['client_id']) : null;
        $estimates = Estimate::where('project_id', $project['id']);
        $invoices = Invoice::where('project_id', $project['id']);
        $tasks = Task::query('SELECT * FROM schedule_tasks WHERE project_id = ? ORDER BY start_date ASC', [$project['id']])->fetchAll();

        $this->view('user/projects/show', [
            'pageTitle' => $project['name'],
            'project' => $project,
            'client' => $client,
            'estimates' => $estimates,
            'invoices' => $invoices,
            'tasks' => $tasks,
        ], 'layouts/app');
    }

    public function edit(string $id): void
    {
        $project = $this->findOwned((int) $id);
        $clients = Client::where('company_id', Auth::companyId(), 'name ASC');
        $this->view('user/projects/form', ['pageTitle' => 'Edit Project', 'clients' => $clients, 'project' => $project], 'layouts/app');
    }

    public function update(string $id): void
    {
        $this->verifyCsrf();
        $project = $this->findOwned((int) $id);

        Project::update($project['id'], [
            'client_id' => $this->input('client_id') ?: null,
            'name' => trim((string) $this->input('name')),
            'description' => $this->input('description', ''),
            'status' => $this->input('status', 'planning'),
            'budget' => (float) $this->input('budget', 0),
            'start_date' => $this->input('start_date') ?: null,
            'end_date' => $this->input('end_date') ?: null,
        ]);

        $this->flash('success', 'Project updated.');
        self::redirect('/app/projects/' . $project['id']);
    }

    public function destroy(string $id): void
    {
        $this->verifyCsrf();
        $project = $this->findOwned((int) $id);
        Project::delete($project['id']);
        $this->flash('success', 'Project deleted.');
        self::redirect('/app/projects');
    }

    private function findOwned(int $id): array
    {
        $project = Project::find($id);
        if (!$project || (int) $project['company_id'] !== Auth::companyId()) {
            http_response_code(404);
            die('Project not found.');
        }
        return $project;
    }
}
