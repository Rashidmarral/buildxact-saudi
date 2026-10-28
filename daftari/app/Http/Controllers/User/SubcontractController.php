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

    public function show(Subcontract $subcontract)
    {
        $subcontract->load('project', 'supplier', 'boqItems', 'milestones.payments', 'payments.milestone', 'payments.paymentVoucher');

        $linkableVouchers = PaymentVoucher::where('supplier_id', $subcontract->supplier_id)
            ->where('status', 'issued')
            ->whereDoesntHave('subcontractPayment')
            ->when($subcontract->project_id, fn ($q) => $q->where(fn ($q2) => $q2->whereNull('project_id')->orWhere('project_id', $subcontract->project_id)))
            ->orderByDesc('date')
            ->get();

        return view('user.subcontracts.show', [
            'subcontract' => $subcontract,
            'linkableVouchers' => $linkableVouchers,
        ]);
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

    public function storePayment(Request $request, Subcontract $subcontract)
    {
        $data = $request->validate([
            'milestone_id' => ['nullable', Rule::exists('subcontract_milestones', 'id')->where('subcontract_id', $subcontract->id)],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'paid_at' => ['required', 'date'],
            'method' => ['nullable', 'string', 'max:30'],
            'reference' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $subcontract->payments()->create([
            ...$data,
            'created_by' => Auth::id(),
        ]);

        return back()->with('status', __('Payment recorded.'));
    }

    public function updatePayment(Request $request, Subcontract $subcontract, SubcontractPayment $payment)
    {
        abort_unless($payment->subcontract_id === $subcontract->id, 404);

        $rules = [
            'milestone_id' => ['nullable', Rule::exists('subcontract_milestones', 'id')->where('subcontract_id', $subcontract->id)],
            'paid_at' => ['required', 'date'],
            'reference' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];

        // A voucher-linked payment's amount/method always come from the
        // voucher itself (edited there, not here) so the two can never
        // silently disagree — see defaultPurpose()-style reasoning on
        // PaymentVoucher.
        if (! $payment->payment_voucher_id) {
            $rules['amount'] = ['required', 'numeric', 'min:0.01'];
            $rules['method'] = ['nullable', 'string', 'max:30'];
        }

        $data = $request->validate($rules);

        $payment->update($data);

        return back()->with('status', __('Payment updated.'));
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
        ]);

        $voucher = PaymentVoucher::where('supplier_id', $subcontract->supplier_id)->findOrFail($data['payment_voucher_id']);

        if ($voucher->subcontractPayment()->exists()) {
            return back()->with('status', __('That voucher is already linked to a subcontract.'));
        }

        $subcontract->payments()->create([
            'milestone_id' => $data['milestone_id'] ?? null,
            'payment_voucher_id' => $voucher->id,
            'amount' => $voucher->amount,
            'paid_at' => $voucher->date,
            'method' => $voucher->method,
            'reference' => $voucher->reference,
            'created_by' => Auth::id(),
        ]);

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
