<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Str;

class Quotation extends Model
{
    use BelongsToCompany;

    protected $fillable = [
        'company_id', 'client_id', 'branch_id', 'salesperson_id', 'created_by', 'converted_invoice_id',
        'quotation_number', 'type', 'status', 'issue_date', 'expiry_date', 'subtotal', 'discount_total',
        'vat_total', 'total', 'currency', 'notes', 'bank_account_id',
        'approved_by', 'approved_at', 'approval_rejection_reason',
    ];

    protected function casts(): array
    {
        return [
            'issue_date' => 'date',
            'expiry_date' => 'date',
            'approved_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        // The token that makes the client-portal "view & accept" link work
        // — deliberately not mass-assignable, must only ever come from the
        // server, mirroring Invoice::public_token.
        static::creating(function (Quotation $quotation) {
            if (empty($quotation->public_token)) {
                $quotation->public_token = Str::random(40);
            }
        });
    }

    /**
     * Whether a client should be able to see this on their public link/
     * portal at all — a draft or pending-approval quotation hasn't been
     * issued to them yet.
     */
    public function isPubliclyViewable(): bool
    {
        return ! in_array($this->status, ['draft', 'pending_approval'], true);
    }

    /**
     * Whether the client can still accept/reject it — once converted,
     * expired, or already decided, the decision is final.
     */
    public function isActionable(): bool
    {
        return $this->status === 'issued' && ! $this->isExpired();
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function bankAccount(): BelongsTo
    {
        return $this->belongsTo(BankAccount::class);
    }

    public function attachments(): MorphMany
    {
        return $this->morphMany(Attachment::class, 'attachable');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function salesperson(): BelongsTo
    {
        return $this->belongsTo(Salesperson::class);
    }

    public function convertedInvoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class, 'converted_invoice_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(QuotationItem::class);
    }

    public function recalculateTotals(): void
    {
        $items = $this->items;

        $this->subtotal = $items->sum(fn ($item) => $item->quantity * $item->unit_price);
        $this->vat_total = $items->sum('vat_amount');
        $this->total = $this->subtotal - $this->discount_total + $this->vat_total;

        $this->save();
    }

    public function isExpired(): bool
    {
        return $this->expiry_date && $this->expiry_date->isPast() && ! in_array($this->status, ['converted', 'accepted']);
    }
}
