<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Http\Controllers\User\Concerns\ResolvesPerPage;
use App\Models\Item;
use App\Models\Project;
use App\Models\PurchaseRequisition;
use App\Models\Unit;
use App\Models\User;
use App\Notifications\GenericNotification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * "Hamly has a Purchase Requisition step before the Purchase Order: a
 * site foreman flags 'need 50 bags cement by Tuesday' with no price or
 * supplier yet, then procurement turns it into a priced PO." Daftari's
 * PurchaseOrder approval chain only gates an already-priced order —
 * this is the earlier "what do we need" step, deliberately price-free.
 * A single reviewer approves/rejects (this is a small, single-owner
 * reviewed operation, not a multi-tier chain like PurchaseOrder's).
 */
class PurchaseRequisitionController extends Controller
{
    use ResolvesPerPage;

    public function index(Request $request)
    {
        $query = PurchaseRequisition::with('project', 'requestedBy', 'items')
            ->when($request->status, fn ($q, $status) => $q->where('status', $status))
            ->orderByDesc('id');

        $requisitions = $query->paginate($this->resolvePerPage($request))->withQueryString();

        $counts = [
            'all' => PurchaseRequisition::count(),
            'requested' => PurchaseRequisition::where('status', 'requested')->count(),
            'approved' => PurchaseRequisition::where('status', 'approved')->count(),
            'rejected' => PurchaseRequisition::where('status', 'rejected')->count(),
            'converted' => PurchaseRequisition::where('status', 'converted')->count(),
        ];

        return view('user.purchase-requisitions.index', compact('requisitions', 'counts'));
    }

    public function create()
    {
        return view('user.purchase-requisitions.form', [
            'requisition' => new PurchaseRequisition(['needed_by_date' => now()->toDateString()]),
            'items' => Item::where('is_active', true)->orderBy('name')->get(),
            'units' => Unit::orderBy('name')->get(),
            'projects' => Project::orderBy('name')->get(),
        ]);
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);

        $requisition = DB::transaction(function () use ($data) {
            $company = Auth::user()->company;

            $requisition = PurchaseRequisition::create([
                'company_id' => $company->id,
                'project_id' => $data['project_id'] ?? null,
                'requested_by' => Auth::id(),
                'requisition_number' => $company->nextPrNumber(),
                'needed_by_date' => $data['needed_by_date'] ?? null,
                'status' => 'requested',
                'notes' => $data['notes'] ?? null,
            ]);

            $this->syncItems($requisition, $data['items']);

            return $requisition;
        });

        return redirect()->route('app.purchase-requisitions.index')->with('status', __('Purchase requisition :number submitted.', ['number' => $requisition->requisition_number]));
    }

    public function edit(PurchaseRequisition $purchaseRequisition)
    {
        abort_unless($purchaseRequisition->status === 'requested', 404);

        return view('user.purchase-requisitions.form', [
            'requisition' => $purchaseRequisition,
            'items' => Item::where('is_active', true)->orderBy('name')->get(),
            'units' => Unit::orderBy('name')->get(),
            'projects' => Project::orderBy('name')->get(),
        ]);
    }

    public function update(Request $request, PurchaseRequisition $purchaseRequisition)
    {
        abort_unless($purchaseRequisition->status === 'requested', 404);

        $data = $this->validated($request);

        DB::transaction(function () use ($purchaseRequisition, $data) {
            $purchaseRequisition->update([
                'project_id' => $data['project_id'] ?? null,
                'needed_by_date' => $data['needed_by_date'] ?? null,
                'notes' => $data['notes'] ?? null,
            ]);

            $purchaseRequisition->items()->delete();
            $this->syncItems($purchaseRequisition, $data['items']);
        });

        return redirect()->route('app.purchase-requisitions.index')->with('status', __('Purchase requisition updated.'));
    }

    public function destroy(PurchaseRequisition $purchaseRequisition)
    {
        abort_unless($purchaseRequisition->status === 'requested', 404);

        $purchaseRequisition->delete();

        return redirect()->route('app.purchase-requisitions.index')->with('status', __('Purchase requisition deleted.'));
    }

    public function approve(PurchaseRequisition $purchaseRequisition)
    {
        abort_unless($purchaseRequisition->status === 'requested', 404);

        $purchaseRequisition->update([
            'status' => 'approved',
            'reviewed_by' => Auth::id(),
            'reviewed_at' => now(),
        ]);

        User::find($purchaseRequisition->requested_by)?->notify(new GenericNotification(
            title: __('Purchase requisition approved'),
            body: __(':number is approved — create a purchase order from it whenever you\'re ready.', ['number' => $purchaseRequisition->requisition_number]),
            url: route('app.purchase-requisitions.index'),
            icon: 'purchases',
        ));

        return back()->with('status', __('Purchase requisition approved.'));
    }

    public function reject(Request $request, PurchaseRequisition $purchaseRequisition)
    {
        abort_unless($purchaseRequisition->status === 'requested', 404);

        $data = $request->validate(['rejection_reason' => ['required', 'string', 'max:1000']]);

        $purchaseRequisition->update([
            'status' => 'rejected',
            'reviewed_by' => Auth::id(),
            'reviewed_at' => now(),
            'rejection_reason' => $data['rejection_reason'],
        ]);

        User::find($purchaseRequisition->requested_by)?->notify(new GenericNotification(
            title: __('Purchase requisition rejected'),
            body: __(':number was rejected: :reason', ['number' => $purchaseRequisition->requisition_number, 'reason' => $data['rejection_reason']]),
            url: route('app.purchase-requisitions.index'),
            icon: 'purchases',
        ));

        return back()->with('status', __('Purchase requisition rejected.'));
    }

    public function createPurchaseOrder(PurchaseRequisition $purchaseRequisition)
    {
        abort_unless($purchaseRequisition->status === 'approved', 404);

        return redirect()->route('app.purchase-orders.create', ['from_requisition' => $purchaseRequisition->id]);
    }

    private function validated(Request $request): array
    {
        $companyId = Auth::user()->company_id;

        return $request->validate([
            'project_id' => ['nullable', \Illuminate\Validation\Rule::exists('projects', 'id')->where('company_id', $companyId)],
            'needed_by_date' => ['nullable', 'date'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.item_id' => ['nullable', \Illuminate\Validation\Rule::exists('items', 'id')->where('company_id', $companyId)],
            'items.*.unit_id' => ['nullable', \Illuminate\Validation\Rule::exists('units', 'id')->where('company_id', $companyId)],
            'items.*.description' => ['required', 'string', 'max:255'],
            'items.*.quantity' => ['required', 'numeric', 'min:0.01'],
        ]);
    }

    private function syncItems(PurchaseRequisition $requisition, array $items): void
    {
        foreach ($items as $sort => $row) {
            $requisition->items()->create([
                'item_id' => $row['item_id'] ?? null,
                'unit_id' => $row['unit_id'] ?? null,
                'description' => $row['description'],
                'quantity' => $row['quantity'],
                'sort_order' => $sort,
            ]);
        }
    }
}
