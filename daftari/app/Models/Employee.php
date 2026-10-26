<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Employee extends Model
{
    use BelongsToCompany;

    protected $fillable = [
        'company_id', 'branch_id', 'employee_number', 'full_name', 'full_name_ar',
        'national_id', 'nationality', 'is_saudi', 'date_of_birth', 'gender',
        'mobile', 'email', 'address', 'job_title', 'department',
        'is_operator', 'license_number', 'license_expiry_date',
        'hire_date', 'termination_date', 'status',
        'iban', 'bank_name', 'gosi_subscription_number',
        'basic_salary', 'housing_allowance', 'transport_allowance', 'other_allowance',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'is_saudi' => 'boolean',
            'is_operator' => 'boolean',
            'date_of_birth' => 'date',
            'hire_date' => 'date',
            'termination_date' => 'date',
            'license_expiry_date' => 'date',
            'basic_salary' => 'decimal:2',
            'housing_allowance' => 'decimal:2',
            'transport_allowance' => 'decimal:2',
            'other_allowance' => 'decimal:2',
        ];
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function payrollRunItems(): HasMany
    {
        return $this->hasMany(PayrollRunItem::class);
    }

    public function endOfServiceSettlements(): HasMany
    {
        return $this->hasMany(EndOfServiceSettlement::class);
    }

    public function leaveRequests(): HasMany
    {
        return $this->hasMany(LeaveRequest::class);
    }

    /** Audit finding CASH-02: cash advanced to this employee via a Payment Voucher. */
    public function paymentVouchers(): HasMany
    {
        return $this->hasMany(PaymentVoucher::class);
    }

    /** Cash repaid by this employee via a Receipt Voucher — the settlement side of an advance. */
    public function receiptVouchers(): HasMany
    {
        return $this->hasMany(ReceiptVoucher::class);
    }

    /** Real company expenses actually paid for out of this employee's cash advance — the other settlement side (see Expense::employee_id). */
    public function expenses(): HasMany
    {
        return $this->hasMany(Expense::class);
    }

    public function totalAdvanced(): float
    {
        return (float) $this->paymentVouchers()->where('status', 'issued')->sum('amount');
    }

    public function totalSettled(): float
    {
        return (float) $this->receiptVouchers()->where('status', 'issued')->sum('amount');
    }

    public function totalSpent(): float
    {
        return (float) $this->expenses()->sum('gross_amount');
    }

    /**
     * What this employee still holds (or owes back) out of every advance
     * they've ever been given: given minus whatever they've either spent
     * on real, itemized company expenses or physically returned unused.
     * Previously only subtracted returned cash — an advance Khalid had
     * genuinely spent on real purchases (recorded as Expense rows with
     * employee_id set) still showed as fully outstanding even though it
     * was no longer sitting in his pocket.
     */
    public function outstandingAdvanceTotal(): float
    {
        return round($this->totalAdvanced() - $this->totalSettled() - $this->totalSpent(), 2);
    }

    public function grossSalary(): float
    {
        return (float) $this->basic_salary + (float) $this->housing_allowance
            + (float) $this->transport_allowance + (float) $this->other_allowance;
    }
}
