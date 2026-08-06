<?php

namespace App\Controllers\User;

use App\Core\Auth;
use App\Core\Controller;
use App\Models\Client;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Project;

class InvoiceController extends Controller
{
    public function index(): void
    {
        $invoices = Invoice::query(
            'SELECT i.*, c.name AS client_name FROM invoices i LEFT JOIN clients c ON c.id = i.client_id WHERE i.company_id = ? ORDER BY i.created_at DESC',
            [Auth::companyId()]
        )->fetchAll();
        $this->view('user/invoices/index', ['pageTitle' => 'Invoices', 'invoices' => $invoices], 'layouts/app');
    }

    public function create(): void
    {
        $companyId = Auth::companyId();
        $clients = Client::where('company_id', $companyId, 'name ASC');
        $projects = Project::where('company_id', $companyId, 'name ASC');
        $nextNumber = 'INV-' . (1000 + Invoice::count('company_id = ?', [$companyId]) + 1);
        $this->view('user/invoices/form', ['pageTitle' => 'New Invoice', 'clients' => $clients, 'projects' => $projects, 'nextNumber' => $nextNumber], 'layouts/app');
    }

    public function store(): void
    {
        $this->verifyCsrf();
        $companyId = Auth::companyId();

        $descriptions = $_POST['item_description'] ?? [];
        $qtys = $_POST['item_qty'] ?? [];
        $prices = $_POST['item_price'] ?? [];

        $total = 0;
        $items = [];
        foreach ($descriptions as $i => $desc) {
            $desc = trim((string) $desc);
            if ($desc === '') {
                continue;
            }
            $qty = (float) ($qtys[$i] ?? 1);
            $price = (float) ($prices[$i] ?? 0);
            $lineTotal = $qty * $price;
            $total += $lineTotal;
            $items[] = ['description' => $desc, 'qty' => $qty, 'unit_price' => $price, 'total' => $lineTotal];
        }

        $invoiceId = Invoice::create([
            'company_id' => $companyId,
            'project_id' => $this->input('project_id') ?: null,
            'client_id' => $this->input('client_id') ?: null,
            'invoice_number' => trim((string) $this->input('invoice_number')) ?: ('INV-' . (1000 + Invoice::count('company_id = ?', [$companyId]) + 1)),
            'status' => 'unpaid',
            'total' => $total,
            'due_date' => $this->input('due_date') ?: null,
        ]);

        foreach ($items as $item) {
            InvoiceItem::create(['invoice_id' => $invoiceId, ...$item]);
        }

        $this->flash('success', 'Invoice created.');
        self::redirect('/app/invoices/' . $invoiceId);
    }

    public function show(string $id): void
    {
        $invoice = $this->findOwned((int) $id);
        $items = InvoiceItem::where('invoice_id', $invoice['id'], 'id ASC');
        $client = $invoice['client_id'] ? Client::find((int) $invoice['client_id']) : null;
        $project = $invoice['project_id'] ? Project::find((int) $invoice['project_id']) : null;

        $this->view('user/invoices/show', [
            'pageTitle' => $invoice['invoice_number'],
            'invoice' => $invoice,
            'items' => $items,
            'client' => $client,
            'project' => $project,
        ], 'layouts/app');
    }

    public function updateStatus(string $id): void
    {
        $this->verifyCsrf();
        $invoice = $this->findOwned((int) $id);
        $status = (string) $this->input('status', 'unpaid');
        if (in_array($status, ['unpaid', 'paid', 'overdue'], true)) {
            Invoice::update($invoice['id'], ['status' => $status]);
            $this->flash('success', 'Invoice status updated.');
        }
        self::redirect('/app/invoices/' . $invoice['id']);
    }

    public function destroy(string $id): void
    {
        $this->verifyCsrf();
        $invoice = $this->findOwned((int) $id);
        Invoice::query('DELETE FROM invoice_items WHERE invoice_id = ?', [$invoice['id']]);
        Invoice::delete($invoice['id']);
        $this->flash('success', 'Invoice deleted.');
        self::redirect('/app/invoices');
    }

    private function findOwned(int $id): array
    {
        $invoice = Invoice::find($id);
        if (!$invoice || (int) $invoice['company_id'] !== Auth::companyId()) {
            http_response_code(404);
            die('Invoice not found.');
        }
        return $invoice;
    }
}
