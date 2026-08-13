<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReceiptVoucher extends Model
{
    use BelongsToCompany;

    protected $fillable = [
        'company_id', 'bank_account_id', 'client_id', 'invoice_id', 'invoice_payment_id',
        'created_by', 'voucher_number', 'date', 'payer_name', 'amount', 'method',
        'reference', 'notes', 'status',
    ];

    protected function casts(): array
    {
        return ['date' => 'date'];
    }

    public function bankAccount(): BelongsTo
    {
        return $this->belongsTo(BankAccount::class);
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
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
