<?php

namespace App\Controllers\User;

use App\Core\Auth;
use App\Core\Controller;
use App\Models\Client;
use App\Models\Estimate;
use App\Models\EstimateItem;
use App\Models\Project;

class EstimateController extends Controller
{
    public function index(): void
    {
        $estimates = Estimate::query(
            'SELECT e.*, c.name AS client_name FROM estimates e LEFT JOIN clients c ON c.id = e.client_id WHERE e.company_id = ? ORDER BY e.created_at DESC',
            [Auth::companyId()]
        )->fetchAll();
        $this->view('user/estimates/index', ['pageTitle' => 'Estimates', 'estimates' => $estimates], 'layouts/app');
    }

    public function create(): void
    {
        $companyId = Auth::companyId();
        $clients = Client::where('company_id', $companyId, 'name ASC');
        $projects = Project::where('company_id', $companyId, 'name ASC');
        $this->view('user/estimates/form', ['pageTitle' => 'New Estimate', 'clients' => $clients, 'projects' => $projects], 'layouts/app');
    }

    public function store(): void
    {
        $this->verifyCsrf();
        $companyId = Auth::companyId();
        $title = trim((string) $this->input('title'));

        if ($title === '') {
            $this->flash('error', 'Estimate title is required.');
            self::redirect('/app/estimates/create');
        }

        $descriptions = $_POST['item_description'] ?? [];
        $qtys = $_POST['item_qty'] ?? [];
        $costs = $_POST['item_cost'] ?? [];

        $total = 0;
        $items = [];
        foreach ($descriptions as $i => $desc) {
            $desc = trim((string) $desc);
            if ($desc === '') {
                continue;
            }
            $qty = (float) ($qtys[$i] ?? 1);
            $cost = (float) ($costs[$i] ?? 0);
            $lineTotal = $qty * $cost;
            $total += $lineTotal;
            $items[] = ['description' => $desc, 'qty' => $qty, 'unit_cost' => $cost, 'total' => $lineTotal];
        }

        $estimateId = Estimate::create([
            'company_id' => $companyId,
            'project_id' => $this->input('project_id') ?: null,
            'client_id' => $this->input('client_id') ?: null,
            'title' => $title,
            'status' => 'draft',
            'total' => $total,
        ]);

        foreach ($items as $item) {
            EstimateItem::create(['estimate_id' => $estimateId, ...$item]);
        }

        $this->flash('success', 'Estimate created.');
        self::redirect('/app/estimates/' . $estimateId);
    }

    public function show(string $id): void
    {
        $estimate = $this->findOwned((int) $id);
        $items = EstimateItem::where('estimate_id', $estimate['id'], 'id ASC');
        $client = $estimate['client_id'] ? Client::find((int) $estimate['client_id']) : null;
        $project = $estimate['project_id'] ? Project::find((int) $estimate['project_id']) : null;

        $this->view('user/estimates/show', [
            'pageTitle' => $estimate['title'],
            'estimate' => $estimate,
            'items' => $items,
            'client' => $client,
            'project' => $project,
        ], 'layouts/app');
    }

    public function updateStatus(string $id): void
    {
        $this->verifyCsrf();
        $estimate = $this->findOwned((int) $id);
        $status = (string) $this->input('status', 'draft');
        if (in_array($status, ['draft', 'sent', 'accepted', 'declined'], true)) {
            Estimate::update($estimate['id'], ['status' => $status]);
            $this->flash('success', 'Estimate status updated.');
        }
        self::redirect('/app/estimates/' . $estimate['id']);
    }

    public function destroy(string $id): void
    {
        $this->verifyCsrf();
        $estimate = $this->findOwned((int) $id);
        Estimate::query('DELETE FROM estimate_items WHERE estimate_id = ?', [$estimate['id']]);
        Estimate::delete($estimate['id']);
        $this->flash('success', 'Estimate deleted.');
        self::redirect('/app/estimates');
    }

    private function findOwned(int $id): array
    {
        $estimate = Estimate::find($id);
        if (!$estimate || (int) $estimate['company_id'] !== Auth::companyId()) {
            http_response_code(404);
            die('Estimate not found.');
        }
        return $estimate;
    }
}
