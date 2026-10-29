<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\PaymentVoucher;
use App\Models\Project;
use App\Models\Subcontract;
use App\Models\SubcontractMilestone;
use App\Models\SubcontractPayment;
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

    public function index(Request $request)
    {
        $subcontracts = Subcontract::with('project', 'supplier')
            ->when($request->filled('project_id'), fn ($q) => $q->where('project_id', $request->integer('project_id')))
            ->when($request->filled('supplier_id'), fn ($q) => $q->where('supplier_id', $request->integer('supplier_id')))
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->query('status')))
            ->orderByDesc('id')
            ->get();

        return view('user.subcontracts.index', [
            'subcontracts' => $subcontracts,
            'projects' => Project::orderBy('name')->get(),
            'suppliers' => Supplier::orderBy('name')->get(),
        ]);
    }

    public function create()
    {
        return view('user.subcontracts.form', [
            'subcontract' => new Subcontract(['retention_percent' => 5, 'start_date' => now()->toDateString()]),
            'projects' => Project::orderBy('name')->get(),
            'suppliers' => Supplier::orderBy('name')->get(),
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
            $this->syncMilestones($subcontract, $request);

            return $subcontract;
        });

        AuditLog::record('subcontract.create', $subcontract, __('Created subcontract :title for :supplier on :project', [
            'title' => $subcontract->title ?: $subcontract->contract_number ?: '#'.$subcontract->id,
            'supplier' => $subcontract->supplier->name,
            'project' => $subcontract->project->name,
        ]));

        return redirect()->route('app.subcontracts.show', $subcontract)->with('status', __('Subcontract created.'));
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
        $subcontract->load('project', 'supplier', 'boqItems', 'milestones.payments', 'payments.milestone', 'payments.paymentVoucher');

        $linkableVouchers = PaymentVoucher::where('supplier_id', $subcontract->supplier_id)
            ->where('status', 'issued')
            ->whereDoesntHave('subcontractPayments')
            ->when($subcontract->project_id, fn ($q) => $q->where(fn ($q2) => $q2->whereNull('project_id')->orWhere('project_id', $subcontract->project_id)))
            ->orderByDesc('date')
            ->get();

        return view('user.subcontracts.show', [
            'subcontract' => $subcontract,
            'linkableVouchers' => $linkableVouchers,
            'categoryGroups' => $this->categoryGroupsFromRequest($subcontract, $request),
            'defaultCategoryGroups' => self::DEFAULT_CATEGORY_GROUPS,
        ]);
    }

    public function categoryReportPdf(Request $request, Subcontract $subcontract)
    {
        $subcontract->load('project', 'supplier');

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

            $matched = $remaining->filter(function (SubcontractPayment $payment) use ($keywords) {
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

            $groups[] = ['label' => $label, 'payments' => $matched->values(), 'total' => round((float) $matched->sum('amount'), 2)];
            $remaining = $remaining->diffKeys($matched->all());
        }

        if ($remaining->isNotEmpty()) {
            $groups[] = ['label' => __('Other / Unmatched'), 'payments' => $remaining->values(), 'total' => round((float) $remaining->sum('amount'), 2)];
        }

        return empty($groups) ? null : $groups;
    }

    public function edit(Subcontract $subcontract)
    {
        $subcontract->load('boqItems', 'milestones');

        return view('user.subcontracts.form', [
            'subcontract' => $subcontract,
            'projects' => Project::orderBy('name')->get(),
            'suppliers' => Supplier::orderBy('name')->get(),
            'defaultMilestones' => self::DEFAULT_MILESTONES,
        ]);
    }

    public function update(Request $request, Subcontract $subcontract)
    {
        $data = $this->validatedContract($request);

        DB::transaction(function () use ($subcontract, $data, $request) {
            $subcontract->update($data);
            $this->syncBoqItems($subcontract, $request);
            $this->syncMilestones($subcontract, $request);
        });

        return redirect()->route('app.subcontracts.show', $subcontract)->with('status', __('Subcontract updated.'));
    }

    public function destroy(Subcontract $subcontract)
    {
        if ($subcontract->payments()->exists()) {
            return back()->with('status', __('This subcontract has recorded payments — remove them first.'));
        }

        $subcontract->boqItems()->delete();
        $subcontract->milestones()->delete();
        $subcontract->delete();

        return redirect()->route('app.subcontracts.index')->with('status', __('Subcontract deleted.'));
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
        $data = $request->validate([
            'milestone_id' => ['nullable', Rule::exists('subcontract_milestones', 'id')->where('subcontract_id', $subcontract->id)],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'gross_amount' => ['nullable', 'numeric', 'min:0.01'],
            'paid_at' => ['required', 'date'],
            'method' => ['nullable', 'string', 'max:30'],
            'reference' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $common = array_intersect_key($data, array_flip(['paid_at', 'method', 'reference', 'notes']));
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
        abort_unless($payment->subcontract_id === $subcontract->id, 404);

        // The net amount/milestone (what this slice counts toward) is
        // always editable, voucher-linked or not — that's exactly the
        // "paid with tax, applied without tax" correction. Only method
        // stays tied to the voucher's real transfer when linked, since
        // that's where the cash actually moved, not a judgment call.
        $rules = [
            'milestone_id' => ['nullable', Rule::exists('subcontract_milestones', 'id')->where('subcontract_id', $subcontract->id)],
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

    /**
     * Splits $netAmount across $subcontract's milestones in sequence
     * order, filling each one's remaining balance before spilling into
     * the next, and creates one SubcontractPayment row per milestone it
     * touches. The real cash figure ($grossAmount, when different from
     * the net total) is recorded on only the first row so
     * Subcontract::totalReceivedGross() never double-counts it. Any
     * amount left over once every milestone is fully funded (no
     * milestones defined, or an overpayment) lands in a final
     * milestone_id=null row rather than being silently dropped.
     */
    private function createAllocatedPayments(Subcontract $subcontract, float $netAmount, array $common, ?float $grossAmount): void
    {
        $remaining = round($netAmount, 2);
        $rows = [];

        foreach ($subcontract->milestones as $milestone) {
            if ($remaining <= 0.004) {
                break;
            }

            $milestoneRemaining = $milestone->balance();
            if ($milestoneRemaining <= 0.004) {
                continue;
            }

            $apply = round(min($remaining, $milestoneRemaining), 2);
            $rows[] = ['milestone_id' => $milestone->id, 'amount' => $apply];
            $remaining = round($remaining - $apply, 2);
        }

        if ($remaining > 0.004) {
            $rows[] = ['milestone_id' => null, 'amount' => $remaining];
        }

        foreach ($rows as $i => $row) {
            $subcontract->payments()->create([
                ...$common, ...$row,
                'gross_amount' => $i === 0 ? $grossAmount : 0,
            ]);
        }
    }

    public function destroyPayment(Subcontract $subcontract, SubcontractPayment $payment)
    {
        abort_unless($payment->subcontract_id === $subcontract->id, 404);

        $payment->delete();

        return back()->with('status', __('Payment removed from the tracker. The underlying voucher, if any, is untouched.'));
    }

    public function linkVoucher(Request $request, Subcontract $subcontract)
    {
        $data = $request->validate([
            'payment_voucher_id' => ['required', Rule::exists('payment_vouchers', 'id')],
            'milestone_id' => ['nullable', Rule::exists('subcontract_milestones', 'id')->where('subcontract_id', $subcontract->id)],
            // "i have paid the same amount with tax but i am paying my
            // supplier this amount without tax" — defaults to the
            // voucher's real transfer when left blank (the common case:
            // nothing to adjust), but can be set lower to apply only the
            // VAT-exclusive portion toward the contract/milestones while
            // the voucher itself keeps recording the real cash moved.
            'net_amount' => ['nullable', 'numeric', 'min:0.01'],
        ]);

        $voucher = PaymentVoucher::where('supplier_id', $subcontract->supplier_id)->findOrFail($data['payment_voucher_id']);

        if ($voucher->subcontractPayments()->exists()) {
            return back()->with('status', __('That voucher is already linked to a subcontract.'));
        }

        $netAmount = (float) ($data['net_amount'] ?? $voucher->amount);
        $common = [
            'payment_voucher_id' => $voucher->id, 'paid_at' => $voucher->date,
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

        return back()->with('status', __('Voucher :number linked to this subcontract.', ['number' => $voucher->voucher_number]));
    }

    public function unlinkVoucher(Subcontract $subcontract, SubcontractPayment $payment)
    {
        abort_unless($payment->subcontract_id === $subcontract->id, 404);

        $payment->delete();

        return back()->with('status', __('Voucher unlinked. The voucher itself, and its accounting entries, are untouched.'));
    }

    public function reportPdf(Subcontract $subcontract)
    {
        $subcontract->load('project', 'supplier', 'boqItems', 'milestones.payments', 'payments.milestone', 'payments.paymentVoucher');

        $pdf = app(MpdfRenderer::class)->render('user.subcontracts.pdf.receiving-report', [
            'company' => Auth::user()->company,
            'subcontract' => $subcontract,
            'template' => Auth::user()->company->defaultTemplateFor('subcontractor_receiving_report'),
        ]);

        return response($pdf, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="receiving-payments-report-'.$subcontract->id.'.pdf"',
        ]);
    }

    private function validatedContract(Request $request): array
    {
        return $request->validate([
            'project_id' => ['required', Rule::exists('projects', 'id')],
            'supplier_id' => ['required', Rule::exists('suppliers', 'id')],
            'contract_number' => ['nullable', 'string', 'max:100'],
            'title' => ['nullable', 'string', 'max:255'],
            'contract_value' => ['required', 'numeric', 'min:0'],
            'retention_percent' => ['required', 'numeric', 'min:0', 'max:100'],
            'start_date' => ['nullable', 'date'],
            'status' => ['required', Rule::in(['active', 'completed', 'cancelled'])],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);
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
