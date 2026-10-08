<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Http\Controllers\User\Concerns\ResolvesReportPeriod;
use App\Models\Client;
use App\Models\Invoice;
use App\Models\Project;
use App\Services\MpdfRenderer;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

/**
 * "Statement of Account" / كشف حساب — a client's own running ledger
 * (every invoice billed, every payment received, every credit/debit
 * note, in order, with a running balance), exportable as a bilingual,
 * branded PDF. A paid module (see FeatureRegistry's 'client_statements'
 * entry): a company requests it from Admin > Modules, a Super Admin
 * approves it (the same CompanyOverride front door every other paid
 * module here uses — see ModuleRequestController).
 *
 * Deliberately a separate feature from ReportController::accountStatement()
 * (Reports > Account Statement), which stays as the simple, always-on,
 * single-currency-table report covering both customers and suppliers.
 * This one is customer-only, richer (separate invoice/payment tables,
 * VAT numbers, an optional project/PO tag, a branded summary), and
 * reachable per-client rather than buried in a report picker — the
 * document a company actually hands to a client chasing payment, not an
 * internal report.
 */
class ClientStatementController extends Controller
{
    use ResolvesReportPeriod;

    public function index()
    {
        return view('user.client-statements.index', [
            'clients' => Client::orderBy('name')->get(),
        ]);
    }

    public function show(Client $client, Request $request)
    {
        $period = $this->resolvePeriod($request);
        $project = $request->filled('project_id') ? Project::find($request->integer('project_id')) : null;

        return view('user.client-statements.show', [
            'client' => $client,
            'period' => $period,
            'projects' => Project::whereHas('invoices', fn ($q) => $q->where('client_id', $client->id))->orderBy('name')->get(),
            'project' => $project,
            'poReference' => $request->query('po_reference'),
            'requisitionReference' => $request->query('requisition_reference'),
        ] + $this->buildStatement($client, $period, $project));
    }

    public function pdf(Client $client, Request $request, MpdfRenderer $renderer)
    {
        $period = $this->resolvePeriod($request);
        $project = $request->filled('project_id') ? Project::find($request->integer('project_id')) : null;
        $company = Auth::user()->company;

        $data = $this->buildStatement($client, $period, $project);

        $pdf = $renderer->render('documents.print.client-statement-pdf', [
            'company' => $company,
            'client' => $client,
            'period' => $period,
            'project' => $project,
            'poReference' => $request->query('po_reference'),
            'requisitionReference' => $request->query('requisition_reference'),
            'template' => $company->defaultTemplateFor('client_statement'),
        ] + $data);

        return response($pdf, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="'.Str::slug($client->name).'-statement-of-account.pdf"',
        ]);
    }

    /**
     * @return array{openingBalance: float, closingBalance: float, totalInvoiced: float, totalReceived: float, totalCredited: float, totalDebited: float, invoiceRows: \Illuminate\Support\Collection, paymentRows: \Illuminate\Support\Collection, ledgerRows: \Illuminate\Support\Collection}
     */
    private function buildStatement(Client $client, array $period, ?Project $project): array
    {
        $invoices = $client->invoices()
            ->whereNotIn('status', ['draft', 'cancelled'])
            ->when($project, fn ($q) => $q->where('project_id', $project->id))
            ->with(['items', 'invoicePayments', 'creditNotes' => fn ($q) => $q->where('status', 'issued'), 'debitNotes' => fn ($q) => $q->where('status', 'issued')])
            ->orderBy('issue_date')
            ->get();

        // A standing adjustment carried on the client's own record (e.g.
        // migrating an existing business relationship into the system) —
        // real money owed regardless of which period is being viewed, so
        // it always seeds the opening balance rather than only applying
        // when the period happens to start "at the beginning".
        $openingBalance = (float) $client->initial_balance;
        $invoiceRows = collect();
        $paymentRows = collect();
        $ledgerRows = collect();
        $totalCredited = 0.0;
        $totalDebited = 0.0;

        foreach ($invoices as $invoice) {
            $this->foldLine($invoice->issue_date, $invoice->total, 0.0, $period, $openingBalance, $ledgerRows, [
                'reference' => $invoice->invoice_number,
                'description' => __('Invoice :number', ['number' => $invoice->invoice_number]),
            ]);

            if ($invoice->issue_date->gte($period['from']) && $invoice->issue_date->lte($period['to'])) {
                $invoiceRows->push([
                    'number' => $invoice->invoice_number,
                    'date' => $invoice->issue_date,
                    'description' => $invoice->notes ? Str::limit(explode("\n", $invoice->notes)[0], 60) : ($invoice->items->first()?->description ?? ''),
                    'status' => $invoice->status,
                    'status_label' => $this->statusLabel($invoice),
                    'total' => (float) $invoice->total,
                ]);
            }

            foreach ($invoice->invoicePayments as $payment) {
                $this->foldLine($payment->paid_at, 0.0, (float) $payment->amount, $period, $openingBalance, $ledgerRows, [
                    'reference' => $payment->reference ?: '—',
                    'description' => __('Payment received'),
                ]);

                if ($payment->paid_at->gte($period['from']) && $payment->paid_at->lte($period['to'])) {
                    $paymentRows->push([
                        'reference' => $payment->reference,
                        'date' => $payment->paid_at,
                        'method' => $payment->method,
                        'invoice_number' => $invoice->invoice_number,
                        'amount' => (float) $payment->amount,
                    ]);
                }
            }

            foreach ($invoice->creditNotes as $creditNote) {
                $this->foldLine($creditNote->issue_date, 0.0, (float) $creditNote->total, $period, $openingBalance, $ledgerRows, [
                    'reference' => $creditNote->credit_note_number,
                    'description' => __('Credit note :number', ['number' => $creditNote->credit_note_number]),
                ]);

                if ($creditNote->issue_date->gte($period['from']) && $creditNote->issue_date->lte($period['to'])) {
                    $totalCredited += (float) $creditNote->total;
                }
            }

            foreach ($invoice->debitNotes as $debitNote) {
                $this->foldLine($debitNote->issue_date, (float) $debitNote->total, 0.0, $period, $openingBalance, $ledgerRows, [
                    'reference' => $debitNote->debit_note_number,
                    'description' => __('Debit note :number', ['number' => $debitNote->debit_note_number]),
                ]);

                if ($debitNote->issue_date->gte($period['from']) && $debitNote->issue_date->lte($period['to'])) {
                    $totalDebited += (float) $debitNote->total;
                }
            }
        }

        $balance = $openingBalance;
        $ledgerRows = $ledgerRows
            ->sortBy(fn (array $row) => $row['date']->format('Y-m-d').'-'.($row['invoiced'] > 0 ? '0' : '1'))
            ->values()
            ->map(function (array $row) use (&$balance) {
                $balance += $row['invoiced'] - $row['paid'];
                $row['balance'] = $balance;

                return $row;
            });

        return [
            'openingBalance' => $openingBalance,
            'closingBalance' => $balance,
            'totalInvoiced' => (float) $invoiceRows->sum('total'),
            'totalReceived' => (float) $paymentRows->sum('amount'),
            'totalCredited' => $totalCredited,
            'totalDebited' => $totalDebited,
            'invoiceRows' => $invoiceRows,
            'paymentRows' => $paymentRows,
            'ledgerRows' => $ledgerRows,
        ];
    }

    /**
     * One ledger event (an invoice, payment, credit note, or debit
     * note): before the period, it only ever adjusts the opening
     * balance; inside the period, it becomes a visible ledger row.
     * $invoiced/$paid follow the Account Ledger's own column meaning
     * (not literal debit/credit) — a credit note is passed as a $paid
     * amount since it reduces the balance exactly like a payment does.
     */
    private function foldLine(Carbon $date, float $invoiced, float $paid, array $period, float &$openingBalance, \Illuminate\Support\Collection $ledgerRows, array $meta): void
    {
        if ($date->lt($period['from'])) {
            $openingBalance += $invoiced - $paid;

            return;
        }

        if ($date->gt($period['to'])) {
            return;
        }

        $ledgerRows->push($meta + ['date' => $date, 'invoiced' => $invoiced, 'paid' => $paid]);
    }

    private function statusLabel(Invoice $invoice): string
    {
        if ($invoice->isOverdue()) {
            return __('Overdue');
        }

        return match ($invoice->status) {
            'paid' => __('Paid'),
            'partially_paid' => __('Partially paid'),
            default => __('Unpaid'),
        };
    }
}
