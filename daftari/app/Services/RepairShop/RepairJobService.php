<?php

namespace App\Services\RepairShop;

use App\Models\Company;
use App\Models\Item;
use App\Models\PosRegister;
use App\Models\PosSale;
use App\Models\RepairJob;
use App\Models\RepairJobItem;
use App\Services\Accounting\LedgerPostingService;
use App\Services\Pos\PosSaleService;
use App\Services\Pos\PosShiftService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Repair job lifecycle: intake a ticket, record parts/labor as the
 * technician works it, gate the move into actual repair behind an
 * explicit customer-approved estimate, and checkout — which reuses
 * PosSaleService as-is (same GL posting, stock deduction and payment
 * recording a retail POS sale gets), exactly the pattern the Restaurant
 * module already established for its own checkout.
 */
class RepairJobService
{
    private const STATUSES = ['received', 'diagnosing', 'awaiting_approval', 'in_repair', 'ready', 'collected', 'cancelled'];

    public function __construct(
        private PosShiftService $shiftService,
        private PosSaleService $saleService,
    ) {}

    /**
     * @param  array{client_id?: int|null, item_description: string, brand?: string|null, model?: string|null, year?: string|null, serial_or_plate?: string|null, issue_description?: string|null}  $data
     */
    public function createJob(Company $company, array $data): RepairJob
    {
        return RepairJob::create([
            'company_id' => $company->id,
            'job_number' => $company->nextRepairJobNumber(),
            'client_id' => $data['client_id'] ?? null,
            'item_description' => $data['item_description'],
            'brand' => $data['brand'] ?? null,
            'model' => $data['model'] ?? null,
            'year' => $data['year'] ?? null,
            'serial_or_plate' => $data['serial_or_plate'] ?? null,
            'issue_description' => $data['issue_description'] ?? null,
            'status' => 'received',
            'created_by' => Auth::id(),
        ]);
    }

    /**
     * @param  array<int, array{item_id: int, quantity: float, unit_price?: float, core_exchange_credit?: float, notes?: string|null}>  $lines
     */
    public function addItems(RepairJob $job, array $lines): void
    {
        if (! $job->isOpen()) {
            throw new RuntimeException(__('This job is already closed.'));
        }

        if (empty($lines)) {
            throw new RuntimeException(__('Add at least one part or labor line.'));
        }

        DB::transaction(function () use ($job, $lines) {
            foreach ($lines as $line) {
                $item = Item::findOrFail($line['item_id']);

                RepairJobItem::create([
                    'company_id' => $job->company_id,
                    'repair_job_id' => $job->id,
                    'item_id' => $item->id,
                    'description' => $item->name,
                    'quantity' => (float) $line['quantity'],
                    'unit_price' => (float) ($line['unit_price'] ?? $item->unit_price),
                    'vat_rate' => (float) $item->vat_rate,
                    'core_exchange_credit' => (float) ($line['core_exchange_credit'] ?? 0),
                    'notes' => $line['notes'] ?? null,
                ]);
            }
        });
    }

    public function updateDiagnosis(RepairJob $job, ?string $notes): void
    {
        if (! $job->isOpen()) {
            throw new RuntimeException(__('This job is already closed.'));
        }

        $job->update(['diagnosis_notes' => $notes]);
    }

    public function approveEstimate(RepairJob $job): void
    {
        if (! $job->isOpen()) {
            throw new RuntimeException(__('This job is already closed.'));
        }

        if ($job->items()->doesntExist()) {
            throw new RuntimeException(__('Add parts/labor lines before approving an estimate.'));
        }

        $job->update(['approved_at' => now(), 'approved_by' => Auth::id()]);
    }

    /**
     * 'in_repair' is gated behind an approved estimate — a customer must
     * have signed off on the parts+labor total before the shop starts
     * spending parts/time on the job. Every other transition is left to
     * staff judgement (a walk-in with a free diagnosis might jump straight
     * from 'diagnosing' to 'ready', for instance).
     */
    public function updateStatus(RepairJob $job, string $status): void
    {
        if (! in_array($status, self::STATUSES, true)) {
            throw new RuntimeException(__('Invalid status.'));
        }

        if (! $job->isOpen()) {
            throw new RuntimeException(__('This job is already closed.'));
        }

        if ($status === 'in_repair' && ! $job->isApproved()) {
            throw new RuntimeException(__('Approve the estimate with the customer before starting the repair.'));
        }

        $job->update(['status' => $status]);
    }

    /**
     * @param  array<int, array{method: string, amount: float, reference?: string}>  $payments
     */
    public function checkout(RepairJob $job, array $payments, LedgerPostingService $ledger, ?int $registerId = null): PosSale
    {
        if (! $job->isOpen()) {
            throw new RuntimeException(__('This job is already closed.'));
        }

        $job->loadMissing('items');

        if ($job->items->isEmpty()) {
            throw new RuntimeException(__('Add parts/labor lines to the job before checkout.'));
        }

        $register = $registerId
            ? PosRegister::findOrFail($registerId)
            : $this->resolveRegister($job->company);

        $shift = $register->openShift() ?? $this->shiftService->open($register, 0);

        $cartLines = $job->items->map(fn (RepairJobItem $line) => [
            'item_id' => $line->item_id,
            'quantity' => (float) $line->quantity,
            'unit_price' => (float) $line->unit_price,
            'discount_amount' => (float) $line->core_exchange_credit,
        ])->all();

        return DB::transaction(function () use ($job, $shift, $cartLines, $payments, $ledger) {
            $sale = $this->saleService->checkout($shift, $cartLines, $payments, $job->client_id, $ledger);

            $job->update(['status' => 'collected', 'pos_sale_id' => $sale->id, 'completed_at' => now()]);

            return $sale;
        });
    }

    public function cancelJob(RepairJob $job, string $reason): void
    {
        if (! $job->isOpen()) {
            throw new RuntimeException(__('This job is already closed.'));
        }

        $job->update(['status' => 'cancelled', 'cancel_reason' => $reason]);
    }

    /**
     * A single company-wide "Repair Shop" register, created on first use —
     * same reasoning as Restaurant's own resolveRegister(): the shop
     * shouldn't have to visit the separate POS module first just to check
     * a job out.
     */
    private function resolveRegister(Company $company): PosRegister
    {
        return PosRegister::withoutGlobalScopes()->firstOrCreate(
            ['company_id' => $company->id, 'name' => 'Repair Shop'],
            ['is_active' => true]
        );
    }
}
