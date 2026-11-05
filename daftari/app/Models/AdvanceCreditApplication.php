<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AdvanceCreditApplication extends Model
{
    use BelongsToCompany;

    protected $fillable = [
        'company_id', 'advance_credit_portion_id', 'invoice_id', 'invoice_item_id',
        'amount', 'vat_amount', 'total', 'created_by',
    ];

    public function portion(): BelongsTo
    {
        return $this->belongsTo(AdvanceCreditPortion::class, 'advance_credit_portion_id');
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    public function invoiceItem(): BelongsTo
    {
        return $this->belongsTo(InvoiceItem::class);
    }
}
