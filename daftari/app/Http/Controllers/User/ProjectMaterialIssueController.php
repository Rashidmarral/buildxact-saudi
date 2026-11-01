<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Models\Item;
use App\Models\ItemStock;
use App\Models\Project;
use App\Models\ProjectMaterialIssue;
use App\Services\Accounting\LedgerPostingService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/**
 * Hamly's project "Materials" tab: issuing stock to a project reduces
 * on-hand quantity and posts a ProjectCost (cost_head 'materials') for
 * margin tracking, plus a real Dr COGS / Cr Inventory journal entry via
 * LedgerPostingService::postProjectMaterialIssue() — genuine stock
 * consumption, unlike equipment/labour costs, which allocate an expense
 * already posted elsewhere (see postProjectCostAllocation()'s docblock).
 * The GL entry is keyed to the Issue itself, not the linked ProjectCost
 * row, so postProjectCostAllocation() is never also called for it —
 * that would double-post the same economic event.
 */
class ProjectMaterialIssueController extends Controller
{
    public function store(Request $request, Project $project, LedgerPostingService $ledger)
    {
        $companyId = Auth::user()->company_id;

        $data = $request->validate([
            'item_id' => ['required', Rule::exists('items', 'id')->where('company_id', $companyId)],
            'warehouse_id' => ['nullable', Rule::exists('warehouses', 'id')->where('company_id', $companyId)],
            'quantity' => ['required', 'numeric', 'min:0.01'],
            'issued_to' => ['nullable', 'string', 'max:255'],
            'issued_at' => ['required', 'date'],
        ]);

        DB::transaction(function () use ($data, $project, $ledger) {
            $item = Item::findOrFail($data['item_id']);
            $unitCost = (float) ($item->purchase_price ?? 0);
            $total = round($unitCost * (float) $data['quantity'], 2);

            $issue = $project->materialIssues()->create($data + [
                'unit_cost' => $unitCost,
                'total' => $total,
                'created_by' => Auth::id(),
            ]);

            if ($data['warehouse_id'] ?? null) {
                $stock = ItemStock::firstOrCreate(
                    ['item_id' => $item->id, 'warehouse_id' => $data['warehouse_id']],
                    ['quantity' => 0]
                );
                $stock->decrement('quantity', $data['quantity']);
            }

            $cost = $project->projectCosts()->create([
                'cost_head' => 'materials',
                'cost_date' => $data['issued_at'],
                'description' => __('Material issue: :item x:qty', ['item' => $item->name, 'qty' => $data['quantity']]),
                'quantity' => $data['quantity'],
                'unit_cost' => $unitCost,
                'amount' => $total,
                'billable' => true,
                'created_by' => Auth::id(),
            ]);

            $issue->update(['project_cost_id' => $cost->id]);

            $ledger->postProjectMaterialIssue($issue);
        });

        return back()->with('status', __('Material issued.'));
    }

    public function destroy(Project $project, ProjectMaterialIssue $materialIssue, LedgerPostingService $ledger)
    {
        abort_unless($materialIssue->project_id === $project->id, 404);

        DB::transaction(function () use ($project, $materialIssue, $ledger) {
            if ($materialIssue->warehouse_id) {
                $stock = ItemStock::firstOrCreate(
                    ['item_id' => $materialIssue->item_id, 'warehouse_id' => $materialIssue->warehouse_id],
                    ['quantity' => 0]
                );
                $stock->increment('quantity', $materialIssue->quantity);
            }

            $ledger->deletePosting($project->company, 'project_material_issue', $materialIssue->id);
            $materialIssue->projectCost?->delete();
            $materialIssue->delete();
        });

        return back()->with('status', __('Material issue removed and stock restored.'));
    }
}
