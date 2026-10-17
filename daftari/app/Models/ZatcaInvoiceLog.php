<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ZatcaInvoiceLog extends Model
{
    use BelongsToCompany;

    protected $fillable = [
        'company_id', 'invoice_id', 'prepayment_invoice_id', 'environment', 'invoice_type', 'direction', 'status',
        'request_uuid', 'invoice_hash', 'previous_invoice_hash', 'xml_payload',
        'cryptographic_stamp', 'response_payload', 'error_message', 'submitted_at', 'cleared_at',
    ];

    protected function casts(): array
    {
        return [
            'submitted_at' => 'datetime',
            'cleared_at' => 'datetime',
        ];
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    /**
     * Set instead of invoice() for a prepayment (386) submission — see
     * the add_prepayment_support_to_zatca_invoice_logs_table migration;
     * exactly one of the two is ever non-null on a given row.
     */
    public function prepaymentInvoice(): BelongsTo
    {
        return $this->belongsTo(PrepaymentInvoice::class);
    }
}
