<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * A general-purpose way to record money received that isn't tied to an
 * invoice — see the create_incomes_table migration's docblock. Mirrors
 * Expense deliberately: same gross/VAT split, same "financial account"
 * (where the money landed) / "GL account" (where it's booked) pair, same
 * optional project tag.
 */
class Income extends Model
{
    use BelongsToCompany, SoftDeletes;

    public const TAX_CATEGORIES = ['standard_15', 'zero_rated', 'exempt'];

    protected $fillable = [
        'company_id', 'income_category_id', 'project_id', 'bank_account_id', 'account_id', 'created_by',
        'payer_name', 'description', 'amount', 'gross_amount', 'vat_amount', 'tax_category',
        'reference', 'income_date', 'status',
    ];

    protected function casts(): array
    {
        return [
            'income_date' => 'date',
        ];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(IncomeCategory::class, 'income_category_id');
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
