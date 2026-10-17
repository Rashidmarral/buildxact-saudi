<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InvoicePayment extends Model
{
    protected $fillable = ['invoice_id', 'bank_account_id', 'amount', 'exchange_rate', 'paid_at', 'method', 'reference', 'notes'];

    protected function casts(): array
    {
        return [
            'paid_at' => 'date',
        ];
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    public function bankAccount(): BelongsTo
    {
        return $this->belongsTo(BankAccount::class);
    }

    /**
     * The same wording as the "Method" dropdown on the Record Payment
     * form (InvoiceController::storePayment()'s view) — reused on the
     * printed document's own "Payment Type" field (sage_classic/
     * retail_classic layouts) so the two never drift apart.
     */
    public static function methodLabel(?string $method): ?string
    {
        return match ($method) {
            'cash' => __('Cash'),
            'bank_transfer' => __('Bank transfer'),
            'card' => __('Card'),
            'other' => __('Other'),
            default => null,
        };
    }
}
