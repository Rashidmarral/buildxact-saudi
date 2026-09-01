<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use App\Services\ZatcaQrGenerator;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Str;

class Invoice extends Model
{
    use BelongsToCompany;

    protected $fillable = [
        'company_id', 'client_id', 'branch_id', 'salesperson_id', 'project_id', 'created_by', 'invoice_number', 'type',
        'status', 'issue_date', 'due_date', 'subtotal', 'discount_total', 'retention_rate', 'retention_amount',
        'vat_total', 'total', 'amount_paid', 'currency', 'exchange_rate', 'notes', 'qr_code', 'bank_account_id', 'warehouse_id', 'stock_deducted',
        'last_reminder_sent_at', 'last_reminder_tier', 'approved_by', 'approved_at', 'rejection_reason',
    ];

    protected function casts(): array
    {
        return [
            'issue_date' => 'date',
            'due_date' => 'date',
            'stock_deducted' => 'boolean',
            'last_reminder_sent_at' => 'datetime',
            'last_reminder_tier' => 'integer',
            'approved_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        // Deliberately not mass-assignable (not in $fillable) — this is
        // the token that makes the public "view & pay" link work, so it
        // must only ever come from the server, never from request input.
        static::creating(function (Invoice $invoice) {
            if (empty($invoice->public_token)) {
                $invoice->public_token = Str::random(40);
            }
        });
    }

    public function isOverdue(): bool
    {
        return in_array($this->status, ['sent', 'partially_paid'], true)
            && $this->due_date
            && $this->due_date->isPast()
            && $this->balanceDue() > 0.0;
    }

    public function daysOverdue(): int
    {
        return $this->due_date ? max(0, (int) $this->due_date->diffInDays(now(), true)) : 0;
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function bankAccount(): BelongsTo
    {
        return $this->belongsTo(BankAccount::class);
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function attachments(): MorphMany
    {
        return $this->morphMany(Attachment::class, 'attachable');
    }

    public function salesperson(): BelongsTo
    {
        return $this->belongsTo(Salesperson::class);
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function installments(): HasMany
    {
        return $this->hasMany(InvoiceInstallment::class)->orderBy('due_date')->orderBy('sort_order');
    }

    /**
     * Audit finding MEDIUM-16: a payment schedule is purely informational
     * — payments still land as one running total against amount_paid (no
     * per-installment allocation/FK), so "paid so far" per installment is
     * derived here by walking the schedule in due-date order and applying
     * amount_paid against each one until it runs out, oldest first.
     */
    public function installmentSchedule(): \Illuminate\Support\Collection
    {
        $remaining = (float) $this->amount_paid;

        return $this->installments->map(function (InvoiceInstallment $installment) use (&$remaining) {
            $paid = min((float) $installment->amount, max(0, $remaining));
            $remaining -= $paid;

            return (object) [
                'installment' => $installment,
                'paid_amount' => round($paid, 2),
                'status' => $paid >= (float) $installment->amount - 0.01
                    ? 'paid'
                    : ($paid > 0 ? 'partial' : 'pending'),
            ];
        });
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function items(): HasMany
    {
        return $this->hasMany(InvoiceItem::class);
    }

    public function invoicePayments(): HasMany
    {
        return $this->hasMany(InvoicePayment::class);
    }

    public function zatcaInvoiceLogs(): HasMany
    {
        return $this->hasMany(ZatcaInvoiceLog::class);
    }

    public function creditNotes(): HasMany
    {
        return $this->hasMany(CreditNote::class);
    }

    public function debitNotes(): HasMany
    {
        return $this->hasMany(DebitNote::class);
    }

    public function creditedTotal(): float
    {
        return (float) $this->creditNotes()->where('status', 'issued')->sum('total');
    }

    public function debitedTotal(): float
    {
        return (float) $this->debitNotes()->where('status', 'issued')->sum('total');
    }

    public function remainingCreditableTotal(): float
    {
        return round((float) $this->total - $this->creditedTotal(), 2);
    }

    /**
     * True once ZATCA has cleared (B2B) or reported (B2C) this invoice —
     * at that point the document is part of an immutable tax record and
     * ZATCA no longer allows edits. The only compliant way to correct a
     * locked invoice is a Credit Note referencing it.
     */
    public function isZatcaLocked(): bool
    {
        return $this->zatcaInvoiceLogs()->whereIn('status', ['cleared', 'reported'])->exists();
    }

    public function recalculateTotals(): void
    {
        $items = $this->items;

        $this->subtotal = $items->sum(fn ($item) => $item->quantity * $item->unit_price);
        $this->vat_total = $items->sum('vat_amount');
        $this->total = $this->subtotal - $this->discount_total + $this->vat_total;
        $this->amount_paid = $this->invoicePayments()->sum('amount');

        $this->qr_code = $this->company->isZatcaQrEnabled()
            ? ZatcaQrGenerator::generate(
                $this->company->name,
                (string) $this->company->vat_number,
                $this->issue_date ?? now(),
                (float) $this->total,
                (float) $this->vat_total
            )
            : null;

        $this->save();
    }

    public function balanceDue(): float
    {
        return round((float) $this->total - (float) $this->amount_paid, 2);
    }

    public function isFullyPaid(): bool
    {
        return $this->balanceDue() <= 0.0;
    }
}
