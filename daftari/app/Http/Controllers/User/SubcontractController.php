<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Employee;
use App\Models\PaymentVoucher;
use App\Models\Project;
use App\Models\Subcontract;
use App\Models\SubcontractMilestone;
use App\Models\SubcontractPayment;
use App\Models\SubcontractReportCategory;
use App\Models\SubcontractWork;
use App\Models\Supplier;
use App\Services\MpdfRenderer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * "i need to add a module to handle subcontractor payments amounts
 * etc... this is not for one subcontractor in future i can give some
 * another subcontractor a project to track there payments... we have
 * payments in milestone wise... 20% advance ,30%,30%,15% and 5% after
 * handover and approval of the project i have already create payment
 * vouchers against subcontractor can i add payments records in module
 * and in some payment vouchers i need to change payments as well i need
 * to get receiving payments reports" — tracks a Supplier's contract on
 * a Project against a milestone schedule, without re-posting the GL:
 * every real payment is still a Payment Voucher (posted exactly as
 * before by LedgerPostingService); this module only links those
 * vouchers (or records a manual note for a payment made outside the
 * voucher system) against a contract value, retention %, and milestone
 * schedule, and prints the signed "Receiving Payments Report".
 */
class SubcontractController extends Controller
{
    private const DEFAULT_MILESTONES = [
        ['name' => 'Advance', 'percentage' => 20, 'requires_handover_approval' => false],
        ['name' => 'Milestone 2', 'percentage' => 30, 'requires_handover_approval' => false],
        ['name' => 'Milestone 3', 'percentage' => 30, 'requires_handover_approval' => false],
        ['name' => 'Milestone 4', 'percentage' => 15, 'requires_handover_approval' => false],
        ['name' => 'Final (after handover & approval)', 'percentage' => 5, 'requires_handover_approval' => true],
    ];

    /**
     * "we need to separate project manager from subcontractor... make
     * it completely separate from subcontractor" — ProjectManagerBudgetController
     * extends this class and overrides only these four hooks (which
     * party_type this controller is locked to, which route/view prefix
     * its pages live under, and whether the milestone schedule UI even
     * applies) so the two modules share every actual budget/BOQ/
     * category-breakdown/voucher-linking behavior below without
     * duplicating it, while staying fully separate in the URL, the
     * nav, and which party (Supplier vs. Employee) each one can create.
     */
    protected function partyTypeScope(): string
    {
        return 'supplier';
    }

    protected function routeName(string $action): string
    {
        return 'app.subcontracts.'.$action;
    }

    protected function viewName(string $view): string
    {
        return 'user.subcontracts.'.$view;
    }

    protected function syncsMilestones(): bool
    {
        return true;
    }

    /**
     * The real enforcement behind "completely separate": a Subcontract
     * whose party_type doesn't match this controller's own scope
     * (an employee-party budget hit via the supplier-only /subcontracts
     * URLs, or vice versa) 404s rather than quietly loading — otherwise
     * route-model binding alone would let either URL prefix open, edit
     * or even re-save (silently flipping its party_type) the other
     * module's record.
     */
    private function ensureInScope(Subcontract $subcontract): void
    {
        abort_unless($subcontract->party_type === $this->partyTypeScope(), 404);
    }

    public function index(Request $request)
    {
        $subcontracts = Subcontract::with('project', 'supplier', 'employee')
            ->where('party_type', $this->partyTypeScope())
            ->when($request->filled('project_id'), fn ($q) => $q->where('project_id', $request->integer('project_id')))
            ->when($request->filled('supplier_id'), fn ($q) => $q->where('supplier_id', $request->integer('supplier_id')))
            ->when($request->filled('employee_id'), fn ($q) => $q->where('employee_id', $request->integer('employee_id')))
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->query('status')))
            ->orderByDesc('id')
            ->get();

        return view($this->viewName('index'), [
            'subcontracts' => $subcontracts,
            'projects' => Project::orderBy('name')->get(),
            'suppliers' => Supplier::orderBy('name')->get(),
            'employees' => Employee::where('status', 'active')->orderBy('full_name')->get(),
        ]);
    }

    public function create()
    {
        return view($this->viewName('form'), [
            'subcontract' => new Subcontract(['party_type' => $this->partyTypeScope(), 'retention_percent' => 5, 'start_date' => now()->toDateString()]),
            'projects' => Project::orderBy('name')->get(),
            'suppliers' => Supplier::orderBy('name')->get(),
            'employees' => Employee::where('status', 'active')->orderBy('full_name')->get(),
            'defaultMilestones' => self::DEFAULT_MILESTONES,
        ]);
    }

    public function store(Request $request)
    {
        $data = $this->validatedContract($request);

        $subcontract = DB::transaction(function () use ($data, $request) {
            $subcontract = Subcontract::create([
                ...$data,
                'created_by' => Auth::id(),
            ]);

            $this->syncBoqItems($subcontract, $request);

            if ($this->syncsMilestones()) {
                $this->syncMilestones($subcontract, $request);
            }

            return $subcontract;
        });

        AuditLog::record('subcontract.create', $subcontract, __('Created subcontract :title for :party on :project', [
            'title' => $subcontract->title ?: $subcontract->contract_number ?: '#'.$subcontract->id,
            'party' => $subcontract->partyName(),
            'project' => $subcontract->project->name,
        ]));

        return redirect()->route($this->routeName('show'), $subcontract)->with('status', __('Subcontract created.'));
    }

    /**
     * "make a report something like this 110800 cash we have paid to
     * shahid shows that then subbase we have given them on 73000 same
     * asphalt transportation something like this" — a free-text,
     * user-defined breakdown of this subcontract's own payments by
     * whatever cost category the operator is thinking in (who got paid,
     * what the money was for), same group_label[]/group_terms[]
     * mechanism GroupsVouchersByPayee already gives the plain Payment
     * Vouchers summary — reimplemented here (not reused via the trait)
     * because the match source is a SubcontractPayment, falling back to
     * its linked voucher's own reference/payee_name/notes.
     */
    /**
     * "ashraf and aljazeera payments will merge into 73000 payment...
     * because ashraf 7700 is trailer rent and one 5000 net cash paid by
     * us that is payable of shahid and that 5000 is include in 110800
     * in shahid payment" — two of Ashraf's 7 real vouchers aren't
     * subbase material at all: the SAR 7,700 "Asphalt Trailer rent"
     * transfer is really a transportation cost, and the SAR 5,000 "net
     * cash paid... in terms of trailer rent" is really Shahid's own
     * expense paid through Ashraf, so it's grouped with Shahid instead.
     * Groups are matched in order, each claiming its payments before the
     * next group even sees what's left — so the most specific match has
     * to come first. The MC1 voucher's own text also says "Shahid", and
     * the SAR 5,000 "net cash paid... trailer rent" voucher's text also
     * says "trailer rent" the same way the SAR 7,700 one does, so both
     * go ahead of the broader "Cash/Transfer"/"shahid" catch-all, and
     * "Cash/Transfer" itself goes ahead of "Asphalt Transportation" so
     * its longer, more specific phrase claims that one SAR 5,000 voucher
     * first.
     */
    private const DEFAULT_CATEGORY_GROUPS = [
        'MC1' => 'mc1',
        'Cash/Transfer' => 'shahid,net cash paid to ashraf',
        'Asphalt Transportation' => 'trailer rent',
        'Sub Base' => 'ashraf,gazaierih',
        'Asphalt' => 'anajim',
        'Machine' => 'machine',
    ];

    public function show(Subcontract $subcontract, Request $request)
    {
        $this->ensureInScope($subcontract);

        $subcontract->load('project', 'supplier', 'employee', 'boqItems', 'milestones.payments', 'works.payments', 'payments.milestone', 'payments.work', 'payments.paymentVoucher', 'reportCategories');

        // "i am unable to select vouchers that is not assign to project
        // manager... i can select any voucher if that voucher assign or
        // not assign to project manager" — a real voucher is routinely
        // issued under one party_type/payee (e.g. a supplier, or no
        // party at all) and only later turns out to really be cash that
        // went through Shahid, so this list is every unlinked issued
        // voucher on the project, regardless of which party it's
        // currently tagged to — not just the ones that already happen
        // to match this subcontract's own party_type/employee_id or
        // supplier_id.
        $linkableVouchers = PaymentVoucher::where('status', 'issued')
            ->whereDoesntHave('subcontractPayments')
            ->when($subcontract->project_id, fn ($q) => $q->where(fn ($q2) => $q2->whereNull('project_id')->orWhere('project_id', $subcontract->project_id)))
            ->orderByDesc('date')
            ->get();

        return view($this->viewName('show'), [
            'subcontract' => $subcontract,
            'linkableVouchers' => $linkableVouchers,
            'categoryGroups' => $this->categoryGroupsFromRequest($subcontract, $request),
            'defaultCategoryGroups' => self::DEFAULT_CATEGORY_GROUPS,
            'reportCategoryRows' => $subcontract->reportCategories->isNotEmpty() ? $this->resolveReportCategories($subcontract) : null,
        ]);
    }

    public function categoryReportPdf(Request $request, Subcontract $subcontract)
    {
        $this->ensureInScope($subcontract);

        $subcontract->load('project', 'supplier', 'employee');

        $groups = $this->categoryGroupsFromRequest($subcontract, $request) ?? [];

        $pdf = app(MpdfRenderer::class)->render('user.subcontracts.pdf.category-breakdown', [
            'company' => Auth::user()->company,
            'subcontract' => $subcontract,
            'groups' => $groups,
            'template' => Auth::user()->company->defaultTemplateFor('subcontractor_receiving_report'),
        ]);

        return response($pdf, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="payment-breakdown-'.$subcontract->id.'.pdf"',
        ]);
    }

    private function categoryGroupsFromRequest(Subcontract $subcontract, Request $request): ?array
    {
        $labels = (array) $request->query('group_label', []);
        $termLists = (array) $request->query('group_terms', []);

        if (empty(array_filter($labels, fn ($l) => trim((string) $l) !== ''))) {
            return null;
        }

        $remaining = $subcontract->payments()->with('paymentVoucher')->get()->keyBy('id');
        $groups = [];

        foreach ($labels as $i => $label) {
            $label = trim((string) $label);
            $keywords = array_values(array_filter(array_map('trim', explode(',', (string) ($termLists[$i] ?? '')))));

            if ($label === '' || empty($keywords)) {
                continue;
            }

            $matched = $this->matchPayments($remaining, $keywords);

            $groups[] = ['label' => $label, 'payments' => $matched->values(), 'total' => round((float) $matched->sum('amount'), 2)];
            $remaining = $remaining->diffKeys($matched->all());
        }

        if ($remaining->isNotEmpty()) {
            $groups[] = ['label' => __('Other / Unmatched'), 'payments' => $remaining->values(), 'total' => round((float) $remaining->sum('amount'), 2)];
        }

        return empty($groups) ? null : $groups;
    }

    /**
     * @param  \Illuminate\Support\Collection<int, SubcontractPayment>  $pool
     * @return \Illuminate\Support\Collection<int, SubcontractPayment>
     */
    private function matchPayments($pool, array $keywords)
    {
        return $pool->filter(function (SubcontractPayment $payment) use ($keywords) {
            $haystack = mb_strtolower(collect([
                $payment->reference, $payment->notes,
                $payment->paymentVoucher->reference ?? null,
                $payment->paymentVoucher->payee_name ?? null,
                $payment->paymentVoucher->notes ?? null,
            ])->filter()->implode(' '));

            foreach ($keywords as $term) {
                if ($term !== '' && str_contains($haystack, mb_strtolower($term))) {
                    return true;
                }
            }

            return false;
        });
    }

    public function storeReportCategory(Request $request, Subcontract $subcontract)
    {
        $this->ensureInScope($subcontract);

        $data = $this->validatedReportCategory($request);

        $subcontract->reportCategories()->create([
            ...$data,
            'sort_order' => $subcontract->reportCategories()->max('sort_order') + 1,
            'amount' => $this->resolveCategoryAmount($data),
        ]);

        return back()->with('status', __('Report category added.'));
    }

    public function updateReportCategory(Request $request, Subcontract $subcontract, SubcontractReportCategory $reportCategory)
    {
        $this->ensureInScope($subcontract);
        abort_unless($reportCategory->subcontract_id === $subcontract->id, 404);

        $data = $this->validatedReportCategory($request);

        $reportCategory->update([
            ...$data,
            'amount' => $this->resolveCategoryAmount($data),
        ]);

        return back()->with('status', __('Report category updated.'));
    }

    public function destroyReportCategory(Subcontract $subcontract, SubcontractReportCategory $reportCategory)
    {
        $this->ensureInScope($subcontract);
        abort_unless($reportCategory->subcontract_id === $subcontract->id, 404);

        $reportCategory->delete();

        return back()->with('status', __('Report category removed.'));
    }

    /**
     * "add an option to add subproject option or work for main
     * project and assign voucher accordingly" — a Work is just a
     * name (and optional budget_amount) a payment can be tagged to;
     * see SubcontractWork's docblock for why this is a lighter-weight
     * sibling to milestones rather than reusing them.
     */
    public function storeWork(Request $request, Subcontract $subcontract)
    {
        $this->ensureInScope($subcontract);

        $data = $this->validatedWork($request);

        $subcontract->works()->create([
            ...$data,
            'sort_order' => $subcontract->works()->max('sort_order') + 1,
        ]);

        return back()->with('status', __('Work added.'));
    }

    public function updateWork(Request $request, Subcontract $subcontract, SubcontractWork $work)
    {
        $this->ensureInScope($subcontract);
        abort_unless($work->subcontract_id === $subcontract->id, 404);

        $work->update($this->validatedWork($request));

        return back()->with('status', __('Work updated.'));
    }

    public function destroyWork(Subcontract $subcontract, SubcontractWork $work)
    {
        $this->ensureInScope($subcontract);
        abort_unless($work->subcontract_id === $subcontract->id, 404);

        $work->delete();

        return back()->with('status', __('Work removed. Any payments tagged to it keep their own record, just untagged.'));
    }

    private function validatedWork(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'budget_amount' => ['nullable', 'numeric', 'min:0'],
        ]);
    }

    private function validatedReportCategory(Request $request): array
    {
        $data = $request->validate([
            'label' => ['required', 'string', 'max:255'],
            'display_mode' => ['required', Rule::in([SubcontractReportCategory::ITEMIZED, SubcontractReportCategory::MEASURED])],
            'match_terms' => ['nullable', 'string', 'max:255'],
            'quantity' => ['nullable', 'numeric', 'min:0'],
            'unit' => ['nullable', 'string', 'max:30'],
            'rate' => ['nullable', 'numeric', 'min:0'],
            'amount' => ['nullable', 'numeric', 'min:0'],
        ]);

        if ($data['display_mode'] === SubcontractReportCategory::ITEMIZED && empty(trim((string) ($data['match_terms'] ?? '')))) {
            throw ValidationException::withMessages(['match_terms' => [__('An itemized category needs at least one match keyword.')]]);
        }

        return $data;
    }

    /**
     * "if i need to update quantities of item it will applied to whole
     * like recalculate... maybe quantity increase or decrease at the
     * end of project" — a measured category's amount is always
     * quantity × rate when both are given, recalculated every time
     * either changes, not a figure that can silently drift from them.
     * With only one of the two (or neither — a flat lump-sum category
     * like "Machine" or "Mubashir and Khalid"), the typed-in amount is
     * authoritative. An itemized category's amount isn't stored at all
     * — it's always computed live from its matching payments.
     */
    private function resolveCategoryAmount(array $data): float
    {
        if ($data['display_mode'] === SubcontractReportCategory::ITEMIZED) {
            return 0.0;
        }

        if (($data['quantity'] ?? null) !== null && ($data['rate'] ?? null) !== null) {
            return round((float) $data['quantity'] * (float) $data['rate'], 2);
        }

        return round((float) ($data['amount'] ?? 0), 2);
    }

    /**
     * @return array<int, array{category: SubcontractReportCategory, payments: \Illuminate\Support\Collection, total: float}>
     */
    private function resolveReportCategories(Subcontract $subcontract): array
    {
        $remaining = $subcontract->payments()->with('paymentVoucher')->get()->keyBy('id');
        $rows = [];

        foreach ($subcontract->reportCategories as $category) {
            // A measured category's own amount (quantity × rate, or a
            // flat figure) is what's shown — never the sum of whatever
            // it matches. But its match_terms, when given, still pull
            // the real vouchers behind it (e.g. "Sub Base" covering the
            // real Ashraf/Al Jazeera vouchers) out of the remaining
            // pool, so that same money doesn't also surface a second
            // time under "Other / Unmatched" below.
            if ($category->isMeasured()) {
                $remaining = $remaining->diffKeys($this->matchPayments($remaining, $category->keywords())->all());
                $rows[] = ['category' => $category, 'payments' => collect(), 'total' => (float) $category->amount];

                continue;
            }

            $matched = $this->matchPayments($remaining, $category->keywords());
            $rows[] = ['category' => $category, 'payments' => $matched->values()->sortBy('paid_at')->values(), 'total' => round((float) $matched->sum('amount'), 2)];
            $remaining = $remaining->diffKeys($matched->all());
        }

        if ($remaining->isNotEmpty()) {
            $other = new SubcontractReportCategory(['label' => __('Other / Unmatched'), 'display_mode' => SubcontractReportCategory::ITEMIZED]);
            $rows[] = ['category' => $other, 'payments' => $remaining->values()->sortBy('paid_at')->values(), 'total' => round((float) $remaining->sum('amount'), 2)];
        }

        return $rows;
    }

    public function edit(Subcontract $subcontract)
    {
        $this->ensureInScope($subcontract);

        $subcontract->load('boqItems', 'milestones');

        return view($this->viewName('form'), [
            'subcontract' => $subcontract,
            'projects' => Project::orderBy('name')->get(),
            'suppliers' => Supplier::orderBy('name')->get(),
            'employees' => Employee::where('status', 'active')->orderBy('full_name')->get(),
            'defaultMilestones' => self::DEFAULT_MILESTONES,
        ]);
    }

    public function update(Request $request, Subcontract $subcontract)
    {
        $this->ensureInScope($subcontract);

        $data = $this->validatedContract($request);

        DB::transaction(function () use ($subcontract, $data, $request) {
            $subcontract->update($data);
            $this->syncBoqItems($subcontract, $request);

            if ($this->syncsMilestones()) {
                $this->syncMilestones($subcontract, $request);
            }
        });

        return redirect()->route($this->routeName('show'), $subcontract)->with('status', __('Subcontract updated.'));
    }

    public function destroy(Subcontract $subcontract)
    {
        $this->ensureInScope($subcontract);

        if ($subcontract->payments()->exists()) {
            return back()->with('status', __('This subcontract has recorded payments — remove them first.'));
        }

        $subcontract->boqItems()->delete();
        $subcontract->milestones()->delete();
        $subcontract->delete();

        return redirect()->route($this->routeName('index'))->with('status', __('Subcontract deleted.'));
    }

    /**
     * "the payments i have made clear them as milestone total milestone
     * paid move to next" — when no milestone is picked explicitly, the
     * net amount is applied to fill whichever milestone is first still
     * short, in sequence order, spilling any remainder into the next
     * one(s) rather than sitting unassigned.
     */
    public function storePayment(Request $request, Subcontract $subcontract)
    {
        $this->ensureInScope($subcontract);

        $data = $request->validate([
            'milestone_id' => ['nullable', Rule::exists('subcontract_milestones', 'id')->where('subcontract_id', $subcontract->id)],
            'work_id' => ['nullable', Rule::exists('subcontract_works', 'id')->where('subcontract_id', $subcontract->id)],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'gross_amount' => ['nullable', 'numeric', 'min:0.01'],
            'paid_at' => ['required', 'date'],
            'method' => ['nullable', 'string', 'max:30'],
            'reference' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $common = array_intersect_key($data, array_flip(['paid_at', 'method', 'reference', 'notes', 'work_id']));
        $common['created_by'] = Auth::id();

        if (! empty($data['milestone_id'])) {
            $subcontract->payments()->create([
                ...$common, 'milestone_id' => $data['milestone_id'],
                'amount' => $data['amount'], 'gross_amount' => $data['gross_amount'] ?? null,
            ]);
        } else {
            $this->createAllocatedPayments($subcontract, (float) $data['amount'], $common, $data['gross_amount'] ?? null);
        }

        return back()->with('status', __('Payment recorded.'));
    }

    public function updatePayment(Request $request, Subcontract $subcontract, SubcontractPayment $payment)
    {
        $this->ensureInScope($subcontract);
        abort_unless($payment->subcontract_id === $subcontract->id, 404);

        // The net amount/milestone (what this slice counts toward) is
        // always editable, voucher-linked or not — that's exactly the
        // "paid with tax, applied without tax" correction. Only method
        // stays tied to the voucher's real transfer when linked, since
        // that's where the cash actually moved, not a judgment call.
        $rules = [
            'milestone_id' => ['nullable', Rule::exists('subcontract_milestones', 'id')->where('subcontract_id', $subcontract->id)],
            'work_id' => ['nullable', Rule::exists('subcontract_works', 'id')->where('subcontract_id', $subcontract->id)],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'gross_amount' => ['nullable', 'numeric', 'min:0.01'],
            'paid_at' => ['required', 'date'],
            'reference' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];

        if (! $payment->payment_voucher_id) {
            $rules['method'] = ['nullable', 'string', 'max:30'];
        }

        $data = $request->validate($rules);

        $payment->update($data);

        return back()->with('status', __('Payment updated.'));
    }

    /** Thin wrapper kept so existing call sites below read the same as before — see Subcontract::applyPaymentAcrossMilestones() for the actual waterfall logic, now shared with one-off seeders. */
    private function createAllocatedPayments(Subcontract $subcontract, float $netAmount, array $common, ?float $grossAmount): void
    {
        $subcontract->applyPaymentAcrossMilestones($netAmount, $common, $grossAmount);
    }

    public function destroyPayment(Subcontract $subcontract, SubcontractPayment $payment)
    {
        $this->ensureInScope($subcontract);
        abort_unless($payment->subcontract_id === $subcontract->id, 404);

        $payment->delete();

        return back()->with('status', __('Payment removed from the tracker. The underlying voucher, if any, is untouched.'));
    }

    /**
     * "deleting vouchers should be multiple not a just single remove
     * link" — the bulk counterpart to destroyPayment()/unlinkVoucher()
     * above (both of which just do $payment->delete() regardless of
     * whether it's voucher-linked or manual), for the checkbox-driven
     * multi-select on the show page. Removing the tracker row never
     * touches the underlying voucher or its accounting entries either
     * way.
     */
    public function destroyPayments(Request $request, Subcontract $subcontract)
    {
        $this->ensureInScope($subcontract);

        $data = $request->validate([
            'payment_id' => ['required', 'array', 'min:1'],
            'payment_id.*' => [Rule::exists('subcontract_payments', 'id')->where('subcontract_id', $subcontract->id)],
        ]);

        $count = $subcontract->payments()->whereIn('id', $data['payment_id'])->delete();

        return back()->with('status', __(':count payment(s) removed from the tracker. The underlying vouchers, if any, are untouched.', ['count' => $count]));
    }

    /**
     * "add functionality i can add multiple payment vouchers or
     * multiselect vouchers at a time" — payment_voucher_id now takes
     * an array (the single-voucher case is just an array of one), so
     * one submission can link several vouchers in one go. Each is
     * still linked through the exact same per-voucher logic as
     * before; one already linked (a race with another tab, or just
     * stale page data) is skipped rather than failing the whole
     * batch, since the others in the batch are still perfectly valid
     * to link.
     */
    public function linkVoucher(Request $request, Subcontract $subcontract)
    {
        $this->ensureInScope($subcontract);

        $data = $request->validate([
            'payment_voucher_id' => ['required', 'array', 'min:1'],
            'payment_voucher_id.*' => [Rule::exists('payment_vouchers', 'id')],
            'milestone_id' => ['nullable', Rule::exists('subcontract_milestones', 'id')->where('subcontract_id', $subcontract->id)],
            // "the last voucher 6000 is against same project but
            // different work... we have an option to add subproject
            // option or work... and assign voucher accordingly" — which
            // named Work (e.g. "MC1", "RC2 Spraying") this voucher's
            // money actually went toward, if this budget tracks more
            // than one.
            'work_id' => ['nullable', Rule::exists('subcontract_works', 'id')->where('subcontract_id', $subcontract->id)],
            // "i have paid the same amount with tax but i am paying my
            // supplier this amount without tax" — defaults to the
            // voucher's real transfer when left blank (the common case:
            // nothing to adjust), but can be set lower to apply only the
            // VAT-exclusive portion toward the contract/milestones while
            // the voucher itself keeps recording the real cash moved.
            // Only meaningful with exactly one voucher selected — applying
            // one override amount across several different vouchers'
            // totals wouldn't mean anything, so it's ignored otherwise.
            'net_amount' => ['nullable', 'numeric', 'min:0.01'],
        ]);

        // No party_type/employee_id/supplier_id filter here either — see
        // the matching note on $linkableVouchers in show(): any issued,
        // not-yet-linked voucher can be linked to this subcontract,
        // whichever party it was originally issued under.
        $ids = $data['payment_voucher_id'];
        $vouchers = PaymentVoucher::whereIn('id', $ids)->get()->keyBy('id');

        $linkedCount = 0;
        $skippedCount = 0;
        $lastVoucherNumber = null;

        foreach ($ids as $id) {
            $voucher = $vouchers->get($id);

            if (! $voucher || $voucher->subcontractPayments()->exists()) {
                $skippedCount++;

                continue;
            }

            $netAmount = (count($ids) === 1 && ! empty($data['net_amount'])) ? (float) $data['net_amount'] : (float) $voucher->amount;
            $common = [
                'payment_voucher_id' => $voucher->id, 'paid_at' => $voucher->date, 'work_id' => $data['work_id'] ?? null,
                'method' => $voucher->method, 'reference' => $voucher->reference, 'created_by' => Auth::id(),
            ];

            if (! empty($data['milestone_id'])) {
                $subcontract->payments()->create([
                    ...$common, 'milestone_id' => $data['milestone_id'],
                    'amount' => $netAmount, 'gross_amount' => (float) $voucher->amount,
                ]);
            } else {
                $this->createAllocatedPayments($subcontract, $netAmount, $common, (float) $voucher->amount);
            }

            $linkedCount++;
            $lastVoucherNumber = $voucher->voucher_number;
        }

        if ($linkedCount === 0) {
            return back()->with('status', __('That voucher is already linked to a subcontract.'));
        }

        if ($linkedCount === 1 && $skippedCount === 0) {
            return back()->with('status', __('Voucher :number linked to this subcontract.', ['number' => $lastVoucherNumber]));
        }

        return back()->with('status', $skippedCount > 0
            ? __(':count voucher(s) linked; :skipped already linked and skipped.', ['count' => $linkedCount, 'skipped' => $skippedCount])
            : __(':count voucher(s) linked to this subcontract.', ['count' => $linkedCount]));
    }

    public function unlinkVoucher(Subcontract $subcontract, SubcontractPayment $payment)
    {
        $this->ensureInScope($subcontract);
        abort_unless($payment->subcontract_id === $subcontract->id, 404);

        $payment->delete();

        return back()->with('status', __('Voucher unlinked. The voucher itself, and its accounting entries, are untouched.'));
    }

    public function reportPdf(Subcontract $subcontract)
    {
        $this->ensureInScope($subcontract);

        $subcontract->load('project', 'supplier', 'employee', 'boqItems', 'milestones.payments', 'works.payments', 'payments.milestone', 'payments.work', 'payments.paymentVoucher', 'reportCategories');

        $pdf = app(MpdfRenderer::class)->render('user.subcontracts.pdf.receiving-report', [
            'company' => Auth::user()->company,
            'subcontract' => $subcontract,
            'reportCategoryRows' => $subcontract->reportCategories->isNotEmpty() ? $this->resolveReportCategories($subcontract) : null,
            'template' => Auth::user()->company->defaultTemplateFor('subcontractor_receiving_report'),
        ]);

        $filenamePrefix = $subcontract->isEmployeeParty() ? 'budget-report-' : 'receiving-payments-report-';

        return response($pdf, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="'.$filenamePrefix.$subcontract->id.'.pdf"',
        ]);
    }

    private function validatedContract(Request $request): array
    {
        // Forced, not merely defaulted: this controller is locked to
        // whichever party_type partyTypeScope() says, regardless of
        // what a request actually posts — the Subcontract form has no
        // way to pick the other one, but this is the real enforcement
        // (a raw request can't smuggle an employee party through the
        // supplier-only Subcontracts controller, or vice versa).
        $request->merge(['party_type' => $this->partyTypeScope()]);

        $data = $request->validate([
            'project_id' => ['required', Rule::exists('projects', 'id')],
            'party_type' => ['required', Rule::in(['supplier', 'employee'])],
            'supplier_id' => ['required_if:party_type,supplier', 'nullable', Rule::exists('suppliers', 'id')],
            'employee_id' => ['required_if:party_type,employee', 'nullable', Rule::exists('employees', 'id')],
            'contract_number' => ['nullable', 'string', 'max:100'],
            'title' => ['nullable', 'string', 'max:255'],
            'contract_value' => ['required', 'numeric', 'min:0'],
            'retention_percent' => ['required', 'numeric', 'min:0', 'max:100'],
            'start_date' => ['nullable', 'date'],
            'status' => ['required', Rule::in(['active', 'completed', 'cancelled'])],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        // Whichever party_type isn't the one actually chosen gets nulled
        // out rather than left as whatever stray value the form posted
        // (or, on an edit, whatever the record previously held before a
        // party_type switch) — a subcontract is with exactly one party.
        if ($data['party_type'] === 'supplier') {
            $data['employee_id'] = null;
        } else {
            $data['supplier_id'] = null;
        }

        return $data;
    }

    private function syncBoqItems(Subcontract $subcontract, Request $request): void
    {
        $subcontract->boqItems()->delete();

        $items = $request->input('boq_items', []);

        foreach ($items as $index => $row) {
            $description = trim((string) ($row['description'] ?? ''));
            $amount = $row['amount'] ?? null;

            if ($description === '' || $amount === null || $amount === '') {
                continue;
            }

            $subcontract->boqItems()->create([
                'description' => $description,
                'quantity' => $row['quantity'] ?? null,
                'unit' => $row['unit'] ?? null,
                'rate' => $row['rate'] ?? null,
                'amount' => (float) $amount,
                'sort_order' => $index,
            ]);
        }
    }

    private function syncMilestones(Subcontract $subcontract, Request $request): void
    {
        $rows = $request->input('milestones', []);

        if (empty($rows)) {
            $rows = self::DEFAULT_MILESTONES;
        }

        $totalPercent = 0.0;
        foreach ($rows as $row) {
            $totalPercent += (float) ($row['percentage'] ?? 0);
        }

        if (abs($totalPercent - 100) > 0.05) {
            throw ValidationException::withMessages([
                'milestones' => [__('The milestone percentages must add up to 100% (currently :total%).', ['total' => number_format($totalPercent, 2)])],
            ]);
        }

        // Preserve already-recorded payment history on existing
        // milestones (matched by sequence) instead of deleting and
        // recreating — a plain delete-then-recreate would null out
        // subcontract_payments.milestone_id via the FK's nullOnDelete,
        // silently detaching every payment from its milestone.
        $existing = $subcontract->milestones()->get()->keyBy('sequence');

        foreach ($rows as $index => $row) {
            $sequence = $index + 1;
            $name = trim((string) ($row['name'] ?? '')) ?: __('Milestone :n', ['n' => $sequence]);
            $percentage = (float) ($row['percentage'] ?? 0);
            $amount = round((float) $subcontract->contract_value * $percentage / 100, 2);
            $requiresApproval = ! empty($row['requires_handover_approval']);

            $milestone = $existing->get($sequence);

            if ($milestone) {
                $milestone->update([
                    'name' => $name, 'percentage' => $percentage, 'amount' => $amount,
                    'requires_handover_approval' => $requiresApproval,
                ]);
                $existing->forget($sequence);
            } else {
                $subcontract->milestones()->create([
                    'sequence' => $sequence, 'name' => $name, 'percentage' => $percentage,
                    'amount' => $amount, 'requires_handover_approval' => $requiresApproval,
                ]);
            }
        }

        // Any leftover sequences beyond the new row count are genuinely
        // removed — their payments (if any) fall back to unassigned
        // (milestone_id null) rather than block the edit.
        foreach ($existing as $stale) {
            $stale->delete();
        }
    }
}
