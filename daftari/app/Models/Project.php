<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Project extends Model
{
    use BelongsToCompany;

    protected $fillable = [
        'company_id', 'code', 'name', 'name_ar', 'status', 'client_id',
        'start_date', 'end_date', 'target_revenue', 'cost_ceiling', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'end_date' => 'date',
        ];
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }

    public function expenses(): HasMany
    {
        return $this->hasMany(Expense::class);
    }

    /**
     * Quotations sent for this project. Not counted in revenue() — a
     * quotation is only an offer, not billed revenue — but linking it
     * lets a subcontracted job trace "client quoted/accepted this" back
     * to the project, the same way its Purchase Orders trace "we ordered
     * this from a sub-vendor for it" (see purchaseOrders()/bills()).
     */
    public function quotations(): HasMany
    {
        return $this->hasMany(Quotation::class);
    }

    /**
     * Purchase Orders placed against this project — typically a
     * sub-vendor contracted to deliver the same scope the project's own
     * client quotation/invoice covers. Not counted in costs() itself
     * (an order isn't a cost until it's billed — see bills()), but
     * linking it here is what lets a main-vendor/sub-vendor job show its
     * subcontract commitments alongside the client side.
     */
    public function purchaseOrders(): HasMany
    {
        return $this->hasMany(PurchaseOrder::class);
    }

    public function bills(): HasMany
    {
        return $this->hasMany(Bill::class);
    }

    /**
     * Cash & Banks vouchers/transfers tagged to this project — the actual
     * money movement behind revenue()/costs()' invoiced/billed figures.
     * See cashReceived()/cashPaid()/cashTransferredOut() below.
     */
    public function receiptVouchers(): HasMany
    {
        return $this->hasMany(ReceiptVoucher::class);
    }

    public function paymentVouchers(): HasMany
    {
        return $this->hasMany(PaymentVoucher::class);
    }

    public function bankTransfers(): HasMany
    {
        return $this->hasMany(BankTransfer::class);
    }

    /**
     * Billed revenue: totals of every non-draft invoice linked to this
     * project — real numbers from real invoices, not a stored estimate.
     */
    public function revenue(): float
    {
        return (float) $this->invoices()->whereNotIn('status', ['draft', 'cancelled'])->sum('total');
    }

    /**
     * Direct expenses plus every posted supplier Bill linked to this
     * project — the latter is what makes a subcontracted job's margin
     * real: a sub-vendor's Bill (raised from their PO) counts as project
     * cost the same way a client's Invoice counts as project revenue. A
     * draft or void Bill doesn't count yet, mirroring revenue()'s
     * exclusion of draft/cancelled invoices.
     */
    public function costs(): float
    {
        return (float) $this->expenses()->sum('amount')
            + (float) $this->bills()->whereNotIn('status', ['draft', 'void'])->sum('total');
    }

    public function margin(): float
    {
        return $this->revenue() - $this->costs();
    }

    public function marginPercent(): ?float
    {
        $revenue = $this->revenue();

        return $revenue > 0 ? round(($this->margin() / $revenue) * 100, 1) : null;
    }

    /**
     * The four Project Cash Flow module figures — real cash in/out/moved
     * for this project, as distinct from revenue()/costs() above (which
     * are invoiced/billed amounts, not necessarily collected/paid yet).
     * The 'issued' status filter mirrors BankAccount::currentBalance()
     * exactly, so these numbers agree with what a bank statement for the
     * same vouchers would show.
     */
    public function cashReceived(): float
    {
        return (float) $this->receiptVouchers()->where('status', 'issued')->sum('amount');
    }

    public function cashPaid(): float
    {
        return (float) $this->paymentVouchers()->where('status', 'issued')->sum('amount');
    }

    /**
     * BankTransfer has no status column — every transfer posts
     * immediately (see BankTransferController::store()). A project has no
     * "own account" a transfer could land in, so any transfer tagged to
     * this project is always counted as cash leaving the project's story,
     * regardless of which of the company's bank accounts it moved between.
     */
    public function cashTransferredOut(): float
    {
        return (float) $this->bankTransfers()->sum('amount');
    }

    public function netCashPosition(): float
    {
        return $this->cashReceived() - $this->cashPaid() - $this->cashTransferredOut();
    }
}
