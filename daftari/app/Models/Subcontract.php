<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * One party's (a Supplier's, or — per party_type — an Employee acting as
 * a project manager) agreed scope on one Project: a contract value, a
 * retention percentage withheld until handover, an optional BOQ
 * breakdown behind that value, and a milestone-wise payment schedule.
 * See subcontracts migration docblock for why this is its own entity
 * rather than fields on Project/Supplier/Employee — the same party can
 * hold subcontracts on several projects, and a project can have several
 * parties (different trades, or cash advanced to an employee who then
 * manages a work package themselves).
 */
class Subcontract extends Model
{
    use BelongsToCompany, SoftDeletes;

    protected $fillable = [
        'company_id', 'project_id', 'party_type', 'supplier_id', 'employee_id', 'contract_number', 'title',
        'contract_value', 'retention_percent', 'start_date', 'status', 'notes', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'contract_value' => 'decimal:2',
            'retention_percent' => 'decimal:2',
            'start_date' => 'date',
        ];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function isEmployeeParty(): bool
    {
        return $this->party_type === 'employee';
    }

    /** The supplier or employee this contract is actually with, whichever party_type says applies. */
    public function party(): Supplier|Employee|null
    {
        return $this->isEmployeeParty() ? $this->employee : $this->supplier;
    }

    public function partyName(): string
    {
        return $this->isEmployeeParty() ? (string) $this->employee?->full_name : (string) $this->supplier?->name;
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function boqItems(): HasMany
    {
        return $this->hasMany(SubcontractBoqItem::class)->orderBy('sort_order');
    }

    public function milestones(): HasMany
    {
        return $this->hasMany(SubcontractMilestone::class)->orderBy('sequence');
    }

    public function works(): HasMany
    {
        return $this->hasMany(SubcontractWork::class)->orderBy('sort_order');
    }

    public function payments(): HasMany
    {
        return $this->hasMany(SubcontractPayment::class)->orderByDesc('paid_at')->orderByDesc('id');
    }

    public function reportCategories(): HasMany
    {
        return $this->hasMany(SubcontractReportCategory::class)->orderBy('sort_order');
    }

    /**
     * The net total applied toward the contract/milestone schedule —
     * what drives balanceBeforeRetention()/percentComplete() against
     * the (VAT-exclusive) contract_value. See the subcontract_payments
     * migration docblock for why this can differ from the real cash
     * received (totalReceivedGross()).
     */
    public function totalPaid(): float
    {
        return (float) $this->payments()->sum('amount');
    }

    /**
     * The real cash received to date, VAT included where it was
     * recorded — "i have paid the same amount with tax but i am paying
     * my supplier this amount without tax". Equal to totalPaid() unless
     * one or more payments recorded a gross_amount different from the
     * net amount applied to the contract.
     */
    public function totalReceivedGross(): float
    {
        return (float) $this->payments()->get()->sum(fn (SubcontractPayment $p) => $p->displayGross());
    }

    public function balanceBeforeRetention(): float
    {
        return round((float) $this->contract_value - $this->totalPaid(), 2);
    }

    public function retentionAmount(): float
    {
        return round((float) $this->contract_value * ((float) $this->retention_percent / 100), 2);
    }

    public function balanceAfterRetention(): float
    {
        return round($this->balanceBeforeRetention() - $this->retentionAmount(), 2);
    }

    public function percentComplete(): float
    {
        if ((float) $this->contract_value <= 0) {
            return 0.0;
        }

        return round(min(100, ($this->totalPaid() / (float) $this->contract_value) * 100), 1);
    }

    /**
     * Splits $netAmount across this contract's milestones in sequence
     * order, filling each one's remaining balance before spilling into
     * the next, and creates one SubcontractPayment row per milestone it
     * touches. The real cash figure ($grossAmount, when different from
     * the net total) is recorded on only the first row so
     * totalReceivedGross() never double-counts it. Any amount left over
     * once every milestone is fully funded (no milestones defined, or an
     * overpayment) lands in a final milestone_id=null row rather than
     * being silently dropped.
     *
     * Shared by the controller (manual "Add a payment" entries, and
     * voucher auto-fill) and by one-off production seeders recording a
     * real, non-cash credit against the contract — e.g. material bought
     * directly and supplied to a subcontractor — the exact same way a
     * cash payment is applied, since both reduce what's actually owed.
     */
    public function applyPaymentAcrossMilestones(float $netAmount, array $attributes, ?float $grossAmount = null): void
    {
        $remaining = round($netAmount, 2);
        $rows = [];

        foreach ($this->milestones as $milestone) {
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
            $this->payments()->create([
                'company_id' => $this->company_id, ...$attributes, ...$row,
                'gross_amount' => $i === 0 ? $grossAmount : 0,
            ]);
        }
    }
}
