<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BankStatementLine extends Model
{
    use BelongsToCompany;

    public const MATCHABLE_TYPES = [
        'receipt_voucher' => ReceiptVoucher::class,
        'payment_voucher' => PaymentVoucher::class,
        'bank_transfer' => BankTransfer::class,
    ];

    protected $fillable = [
        'company_id', 'bank_reconciliation_id', 'date', 'description', 'reference',
        'amount', 'matched_type', 'matched_id',
    ];

    protected function casts(): array
    {
        return [
            'date' => 'date',
            'amount' => 'decimal:2',
        ];
    }

    public function reconciliation(): BelongsTo
    {
        return $this->belongsTo(BankReconciliation::class, 'bank_reconciliation_id');
    }

    public function isMatched(): bool
    {
        return $this->matched_type !== null;
    }

    /**
     * The voucher/transfer this line was matched to, or null if unmatched
     * or the matched row was since deleted.
     */
    public function matchedModel(): ?Model
    {
        $class = self::MATCHABLE_TYPES[$this->matched_type] ?? null;

        return $class ? $class::find($this->matched_id) : null;
    }
}
