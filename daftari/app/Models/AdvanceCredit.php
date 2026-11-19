<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * See this feature's own migration for the full "why." A wrapper around
 * one or more AdvanceCreditPortions — the labeled slices of an advance
 * payment that can each be drawn down (in full or in part, over one or
 * several future invoices) against new invoices for the same client.
 */
class AdvanceCredit extends Model
{
    use BelongsToCompany;

    protected $fillable = ['company_id', 'client_id', 'project_id', 'source_invoice_id', 'name', 'notes', 'created_by'];

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function sourceInvoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class, 'source_invoice_id');
    }

    public function portions(): HasMany
    {
        return $this->hasMany(AdvanceCreditPortion::class)->orderBy('sort_order')->orderBy('id');
    }

    public function totalAmount(): float
    {
        return (float) $this->portions()->sum('total');
    }

    public function remainingTotal(): float
    {
        return round($this->portions->sum(fn (AdvanceCreditPortion $p) => $p->remainingTotal()), 2);
    }
}
