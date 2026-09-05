<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ZatcaDebitNoteLog extends Model
{
    use BelongsToCompany;

    protected $fillable = [
        'company_id', 'debit_note_id', 'environment', 'invoice_type', 'direction', 'status',
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

    public function debitNote(): BelongsTo
    {
        return $this->belongsTo(DebitNote::class);
    }
}
