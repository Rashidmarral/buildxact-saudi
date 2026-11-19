<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Project;
use App\Models\TaxRate;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Hamly's project Billing tab: "certified/amount due/retention to
 * collect/advance to deduct; issues invoices, advance invoices,
 * retention." Branches by the project's billing_type (set in Phase 0)
 * — progress billing reads BOQ items (Phase 1), time & material reads
 * approved billable timesheets and project costs (Phase 2). Every
 * invoice this creates is left as a DRAFT: the user reviews and sends
 * it themselves through the existing Invoice page, same as every other
 * real financial document this engagement has ever produced
 * automatically.
 *
 * "Global Billing: cross-project filtered view of project invoices ...
 * no new data, just a filtered index" — satisfied by adding a
 * project_id filter to the existing Invoices index (InvoiceController)
 * rather than building a second invoice list page.
 */
class ProjectBillingController extends Controller
{
    public function createProgress(Project $project)
    {
        abort_unless(in_array($project->billing_type, ['milestones', 'progress_retention', 'per_unit_sale'], true), 404);
        abort_if(! $project->client_id, 422, __('This project has no client to bill.'));

        $items = $project->boqItems()->get()->filter(fn ($item) => $item->remainingToBill() > 0.01);
        abort_if($items->isEmpty(), 422, __('Nothing is due to be billed — no BOQ item has unbilled progress.'));

        $invoice = DB::transaction(function () use ($project, $items) {
            $invoice = $this->newDraftInvoice($project);

            foreach ($items as $boqItem) {
                $remainingQty = round((float) $boqItem->executed_quantity - (float) $boqItem->billed_quantity, 2);

                $this->addInvoiceLine($invoice, $boqItem->description, $remainingQty, (float) $boqItem->rate);

                $boqItem->update(['billed_quantity' => $boqItem->billed_quantity + $remainingQty]);
            }

            if ($project->billing_type === 'progress_retention' && $project->retention_percent) {
                $this->applyRetention($invoice, $project->retention_percent);
            }

            $invoice->recalculateTotals();

            return $invoice;
        });

        return redirect()->route('app.invoices.show', $invoice)->with('status', __('Draft invoice created for the billable progress — review and send it when ready.'));
    }

    public function createTimeAndMaterial(Project $project)
    {
        abort_unless($project->billing_type === 'time_and_material', 404);
        abort_if(! $project->client_id, 422, __('This project has no client to bill.'));

        $timesheets = $project->uninvoicedBillableTimesheets()->get();
        $costs = $project->uninvoicedBillableCosts()->get();
        abort_if($timesheets->isEmpty() && $costs->isEmpty(), 422, __('Nothing billable is outstanding — every approved timesheet and cost is already invoiced.'));

        $invoice = DB::transaction(function () use ($project, $timesheets, $costs) {
            $invoice = $this->newDraftInvoice($project);

            foreach ($timesheets as $timesheet) {
                $this->addInvoiceLine(
                    $invoice,
                    __('Labour — :employee (:date)', ['employee' => $timesheet->employee?->full_name ?? __('Unassigned'), 'date' => $timesheet->work_date->format('Y-m-d')]),
                    $timesheet->totalHours(),
                    (float) ($timesheet->bill_rate ?? 0)
                );
                $timesheet->update(['invoice_id' => $invoice->id, 'invoiced_at' => now()]);
            }

            foreach ($costs as $cost) {
                $this->addInvoiceLine($invoice, $cost->description, 1, (float) $cost->amount);
                $cost->update(['invoice_id' => $invoice->id, 'invoiced_at' => now()]);
            }

            $invoice->recalculateTotals();

            return $invoice;
        });

        return redirect()->route('app.invoices.show', $invoice)->with('status', __('Draft invoice created from outstanding timesheets and costs — review and send it when ready.'));
    }

    public function createFixed(Project $project)
    {
        abort_unless(in_array($project->billing_type, ['fixed_price', 'recurring_amc'], true), 404);
        abort_if(! $project->client_id, 422, __('This project has no client to bill.'));

        $remaining = $project->remainingContractBalance();
        abort_if($remaining <= 0, 422, __('Nothing remains to bill against this project\'s contract value.'));

        $invoice = DB::transaction(function () use ($project, $remaining) {
            $invoice = $this->newDraftInvoice($project);
            $this->addInvoiceLine($invoice, __('Contract value — :project', ['project' => $project->name]), 1, $remaining);

            if ($project->retention_percent) {
                $this->applyRetention($invoice, $project->retention_percent);
            }

            $invoice->recalculateTotals();

            return $invoice;
        });

        return redirect()->route('app.invoices.show', $invoice)->with('status', __('Draft invoice created for the remaining contract balance — review and send it when ready.'));
    }

    public function createAdvance(Request $request, Project $project)
    {
        abort_unless($project->billing_type !== 'no_billing', 404);
        abort_if(! $project->client_id, 422, __('This project has no client to bill.'));

        $data = $request->validate(['amount' => ['required', 'numeric', 'min:0.01']]);

        $invoice = DB::transaction(function () use ($project, $data) {
            $invoice = $this->newDraftInvoice($project);
            $this->addInvoiceLine($invoice, __('Advance payment — :project', ['project' => $project->name]), 1, $data['amount']);
            $invoice->recalculateTotals();

            return $invoice;
        });

        return redirect()->route('app.invoices.show', $invoice)->with('status', __('Draft advance invoice created — review and send it when ready.'));
    }

    private function newDraftInvoice(Project $project): Invoice
    {
        $company = Auth::user()->company;

        return Invoice::create([
            'client_id' => $project->client_id,
            'project_id' => $project->id,
            'branch_id' => $company->default_branch_id,
            'created_by' => Auth::id(),
            'invoice_number' => $company->nextInvoiceNumber(),
            'type' => 'standard',
            'status' => 'draft',
            'issue_date' => now()->toDateString(),
            'due_date' => now()->addDays($company->default_payment_terms_days ?? 30)->toDateString(),
            'currency' => $company->currency,
            'exchange_rate' => 1,
            'notes' => __('Generated from project billing: :project', ['project' => $project->name]),
        ]);
    }

    private function addInvoiceLine(Invoice $invoice, string $description, float $quantity, float $unitPrice): void
    {
        $line = new InvoiceItem([
            'invoice_id' => $invoice->id,
            'description' => $description,
            'quantity' => $quantity,
            'unit_price' => $unitPrice,
            'vat_rate' => $invoice->project?->default_tax_rate_id
                ? TaxRate::find($invoice->project->default_tax_rate_id)?->rate
                : TaxRate::defaultRate($invoice->company_id),
            'sort_order' => $invoice->items()->count(),
        ]);
        $line->recalculate();
        $line->save();
    }

    private function applyRetention(Invoice $invoice, float $retentionPercent): void
    {
        $subtotal = (float) $invoice->items()->get()->sum(fn (InvoiceItem $item) => $item->quantity * $item->unit_price);

        $invoice->update([
            'retention_rate' => $retentionPercent,
            'retention_amount' => round($subtotal * ($retentionPercent / 100), 2),
        ]);
    }
}
