<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Models\Bill;
use App\Models\CostCenter;
use App\Models\CostCenterLink;
use App\Models\Invoice;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class CostCenterController extends Controller
{
    private const TYPES = ['department', 'project', 'branch'];

    public function index(Request $request)
    {
        $company = Auth::user()->company;
        $view = $request->query('view', 'list') === 'tree' ? 'tree' : 'list';

        $costCenters = $company->costCenters()->with('parent')->orderBy('code')->get();

        $links = $company->costCenterLinks()->with('costCenter')->latest('id')->take(50)->get()
            ->map(function (CostCenterLink $link) {
                $link->entity_label = match ($link->entity_type) {
                    'invoice' => Invoice::find($link->entity_id)?->invoice_number ?? __('(deleted)'),
                    'bill' => Bill::find($link->entity_id)?->bill_number ?? __('(deleted)'),
                    default => (string) $link->entity_id,
                };

                return $link;
            });

        return view('user.cost-centers.index', [
            'costCenters' => $costCenters,
            'view' => $view,
            'types' => self::TYPES,
            'scopedTypes' => array_values(array_intersect(self::TYPES, $company->costCenterScopeTypes())) ?: self::TYPES,
            'links' => $links,
            'invoices' => Invoice::orderByDesc('id')->take(100)->get(),
            'bills' => Bill::orderByDesc('id')->take(100)->get(),
            'requiredOnSales' => $company->cost_center_required_on_sales,
            'requiredOnExpenses' => $company->cost_center_required_on_expenses,
        ]);
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);

        Auth::user()->company->costCenters()->create($data);

        return back()->with('status', __('Cost center added.'));
    }

    public function update(Request $request, CostCenter $costCenter)
    {
        $data = $this->validated($request, $costCenter->id);

        if ($data['parent_id'] == $costCenter->id) {
            return back()->withErrors(['parent_id' => __('A cost center cannot be its own parent.')]);
        }

        $costCenter->update($data);

        return back()->with('status', __('Cost center updated.'));
    }

    public function destroy(CostCenter $costCenter)
    {
        if ($costCenter->journalEntryLines()->exists()) {
            return back()->withErrors(['cost_center' => __('This cost center has journal entries and cannot be deleted. Deactivate it instead.')]);
        }

        if ($costCenter->children()->exists()) {
            return back()->withErrors(['cost_center' => __('This cost center has sub cost centers and cannot be deleted.')]);
        }

        $costCenter->delete();

        return back()->with('status', __('Cost center deleted.'));
    }

    public function storeLink(Request $request)
    {
        $companyId = Auth::user()->company_id;

        $data = $request->validate([
            'cost_center_id' => ['required', Rule::exists('cost_centers', 'id')->where('company_id', $companyId)],
            'entity_type' => ['required', 'in:invoice,bill'],
            'entity_id' => ['required', 'integer'],
            'allocation_percent' => ['required', 'numeric', 'min:0.01', 'max:100'],
        ]);

        if ($data['entity_type'] === 'invoice' && ! Invoice::find($data['entity_id'])) {
            return back()->withErrors(['entity_id' => __('That invoice was not found.')]);
        }

        if ($data['entity_type'] === 'bill' && ! Bill::find($data['entity_id'])) {
            return back()->withErrors(['entity_id' => __('That bill was not found.')]);
        }

        $existingTotal = CostCenterLink::where('company_id', $companyId)
            ->where('entity_type', $data['entity_type'])
            ->where('entity_id', $data['entity_id'])
            ->sum('allocation_percent');

        if ($existingTotal + $data['allocation_percent'] > 100.01) {
            return back()->withErrors(['allocation_percent' => __('Total allocation for this document would exceed 100%.')]);
        }

        Auth::user()->company->costCenterLinks()->create($data);

        return back()->with('status', __('Cost center link saved.'));
    }

    public function destroyLink(CostCenterLink $costCenterLink)
    {
        $costCenterLink->delete();

        return back()->with('status', __('Cost center link removed.'));
    }

    /**
     * "add more... required-on-sales/expenses toggles, scope setting" —
     * required_on_sales/_on_expenses gate InvoiceController::send() and
     * BillController::post() (see blockedByMissingCostCentre() on each);
     * scope narrows which of the three cost centre types this company's
     * own add/edit forms and pickers offer, without touching any
     * existing cost centre already using a type that's now out of scope.
     */
    public function updateSettings(Request $request)
    {
        $data = $request->validate([
            'cost_center_scope' => ['nullable', 'array'],
            'cost_center_scope.*' => [Rule::in(self::TYPES)],
        ]);

        Auth::user()->company->update([
            'cost_center_required_on_sales' => $request->boolean('cost_center_required_on_sales'),
            'cost_center_required_on_expenses' => $request->boolean('cost_center_required_on_expenses'),
            'cost_center_scope' => empty($data['cost_center_scope']) ? null : implode(',', $data['cost_center_scope']),
        ]);

        return back()->with('status', __('Cost center settings saved.'));
    }

    /**
     * "add more... a re-tag a period tool" — bulk-corrects a date range
     * that was tagged wrong (or never tagged at all): every invoice/bill
     * issued/dated in the period has its existing cost centre links for
     * that document replaced with a single 100% link to the chosen cost
     * centre. A document the company has no access to (wrong company)
     * is excluded by the query scoping itself, same as everywhere else.
     */
    public function retagPeriod(Request $request)
    {
        $companyId = Auth::user()->company_id;

        $data = $request->validate([
            'entity_type' => ['required', 'in:invoice,bill'],
            'cost_center_id' => ['required', Rule::exists('cost_centers', 'id')->where('company_id', $companyId)],
            'from' => ['required', 'date'],
            'to' => ['required', 'date', 'after_or_equal:from'],
        ]);

        $documentIds = $data['entity_type'] === 'invoice'
            ? Invoice::whereBetween('issue_date', [$data['from'], $data['to']])->pluck('id')
            : Bill::whereBetween('bill_date', [$data['from'], $data['to']])->pluck('id');

        CostCenterLink::where('company_id', $companyId)
            ->where('entity_type', $data['entity_type'])
            ->whereIn('entity_id', $documentIds)
            ->delete();

        $now = now();
        $rows = $documentIds->map(fn (int $id) => [
            'company_id' => $companyId, 'cost_center_id' => $data['cost_center_id'],
            'entity_type' => $data['entity_type'], 'entity_id' => $id, 'allocation_percent' => 100,
            'created_at' => $now, 'updated_at' => $now,
        ])->all();

        if ($rows !== []) {
            CostCenterLink::insert($rows);
        }

        return back()->with('status', __(':count :type retagged to the chosen cost center.', ['count' => count($rows), 'type' => $data['entity_type'] === 'invoice' ? __('invoice(s)') : __('bill(s)')]));
    }

    private function validated(Request $request, ?int $ignoreId = null): array
    {
        $companyId = Auth::user()->company_id;

        $data = $request->validate([
            'code' => ['required', 'string', 'max:20', Rule::unique('cost_centers')->where('company_id', $companyId)->ignore($ignoreId)],
            'name' => ['required', 'string', 'max:255'],
            'name_ar' => ['nullable', 'string', 'max:255'],
            'type' => ['required', Rule::in(self::TYPES)],
            'parent_id' => ['nullable', Rule::exists('cost_centers', 'id')->where('company_id', $companyId)],
        ]);

        $data['is_active'] = $request->boolean('is_active', true);

        return $data;
    }
}
