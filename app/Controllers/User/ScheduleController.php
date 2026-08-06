<?php

namespace App\Controllers\User;

use App\Core\Auth;
use App\Core\Controller;
use App\Models\Project;
use App\Models\Task;

class ScheduleController extends Controller
{
    public function index(): void
    {
        $companyId = Auth::companyId();
        $tasks = Task::query(
            'SELECT t.*, p.name AS project_name FROM schedule_tasks t JOIN projects p ON p.id = t.project_id WHERE t.company_id = ? ORDER BY t.start_date ASC',
            [$companyId]
        )->fetchAll();
        $projects = Project::where('company_id', $companyId, 'name ASC');
        $this->view('user/schedule/index', ['pageTitle' => 'Schedule', 'tasks' => $tasks, 'projects' => $projects], 'layouts/app');
    }

    public function store(): void
    {
        $this->verifyCsrf();
        $companyId = Auth::companyId();
        $title = trim((string) $this->input('title'));
        $projectId = (int) $this->input('project_id');

        if ($title === '' || !$projectId) {
            $this->flash('error', 'Task title and project are required.');
            self::redirect('/app/schedule');
        }

        $project = Project::find($projectId);
        if (!$project || (int) $project['company_id'] !== $companyId) {
            http_response_code(404);
            die('Project not found.');
        }

        Task::create([
            'company_id' => $companyId,
            'project_id' => $projectId,
            'title' => $title,
            'start_date' => $this->input('start_date') ?: null,
            'end_date' => $this->input('end_date') ?: null,
            'status' => 'pending',
        ]);

        $this->flash('success', 'Task added to schedule.');
        self::redirect('/app/schedule');
    }

    public function updateStatus(string $id): void
    {
        $this->verifyCsrf();
        $task = $this->findOwned((int) $id);
        $status = (string) $this->input('status', 'pending');
        if (in_array($status, ['pending', 'in_progress', 'done'], true)) {
            Task::update($task['id'], ['status' => $status]);
        }
        self::redirect('/app/schedule');
    }

    public function destroy(string $id): void
    {
        $this->verifyCsrf();
        $task = $this->findOwned((int) $id);
        Task::delete($task['id']);
        $this->flash('success', 'Task removed.');
        self::redirect('/app/schedule');
    }

    private function findOwned(int $id): array
    {
        $task = Task::find($id);
        if (!$task || (int) $task['company_id'] !== Auth::companyId()) {
            http_response_code(404);
            die('Task not found.');
        }
        return $task;
    }
}
