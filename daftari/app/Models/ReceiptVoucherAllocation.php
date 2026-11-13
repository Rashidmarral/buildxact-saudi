<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One invoice's share of a single Receipt Voucher's real amount — see
 * this table's own migration for the full "why". Posted (invoice_
 * payment_id set) the same moment the voucher itself is settled
 * (ReceiptVoucherController::settleInvoiceAndPost()), or left null
 * until then if the voucher is still pending approval.
 */
class ReceiptVoucherAllocation extends Model
{
    use BelongsToCompany;

    protected $fillable = ['company_id', 'receipt_voucher_id', 'invoice_id', 'invoice_payment_id', 'amount'];

    public function receiptVoucher(): BelongsTo
    {
        return $this->belongsTo(ReceiptVoucher::class);
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    public function invoicePayment(): BelongsTo
    {
        return $this->belongsTo(InvoicePayment::class);
    }
}
