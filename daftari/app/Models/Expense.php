<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Expense extends Model
{
    use BelongsToCompany;

    public const TAX_CATEGORIES = ['standard_15', 'zero_rated', 'exempt'];

    protected $fillable = [
        'company_id', 'expense_category_id', 'project_id', 'bank_account_id', 'account_id', 'created_by',
        'vendor_name', 'description', 'amount', 'gross_amount', 'vat_amount', 'tax_category',
        'reference', 'expense_date', 'receipt_path',
        'status', 'approved_by', 'approved_at', 'rejection_reason',
    ];

    protected function casts(): array
    {
        return [
            'expense_date' => 'date',
            'approved_at' => 'datetime',
        ];
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(ExpenseCategory::class, 'expense_category_id');
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function bankAccount(): BelongsTo
    {
        return $this->belongsTo(BankAccount::class);
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public static function taxRateFor(string $taxCategory): float
    {
        return $taxCategory === 'standard_15' ? 15.0 : 0.0;
    }
}
