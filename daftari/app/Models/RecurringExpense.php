<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use App\Notifications\GenericNotification;
use App\Services\Accounting\LedgerPostingService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\App;

class RecurringExpense extends Model
{
    use BelongsToCompany;

    public const FREQUENCIES = ['weekly', 'monthly', 'quarterly', 'yearly'];

    protected $fillable = [
        'company_id', 'expense_category_id', 'project_id', 'bank_account_id', 'account_id', 'created_by',
        'title', 'vendor_name', 'description', 'gross_amount', 'tax_category', 'reference',
        'frequency', 'start_date', 'next_run_date', 'end_date', 'status', 'last_generated_at', 'generated_count', 'notes',
    ];

    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'next_run_date' => 'date',
            'end_date' => 'date',
            'last_generated_at' => 'datetime',
        ];
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

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    /**
     * Mirrors RecurringInvoice::nextRunDateAfter() — kept as one method so
     * seeding next_run_date on creation and advancing it after each
     * generation never drift apart.
     */
    public function nextRunDateAfter(\DateTimeInterface $from): \Illuminate\Support\Carbon
    {
        $date = \Illuminate\Support\Carbon::instance($from);

        return match ($this->frequency) {
            'weekly' => $date->copy()->addWeek(),
            'quarterly' => $date->copy()->addMonths(3),
            'yearly' => $date->copy()->addYear(),
            default => $date->copy()->addMonth(),
        };
    }

    /**
     * Creates a real Expense from this template, mirroring
     * ExpenseController::store()'s gross/VAT computation and approval-
     * threshold check exactly, then advances this recurrence to its next
     * run date (or marks it completed once that would fall past end_date).
     */
    public function generateExpense(): Expense
    {
        $company = $this->company;
        $rate = Expense::taxRateFor($this->tax_category);
        $gross = (float) $this->gross_amount;
        $vat = round($gross * $rate / (100 + $rate), 2);
        $requiresApproval = $company->expenseRequiresApproval($gross);

        $expense = Expense::create([
            'company_id' => $this->company_id,
            'expense_category_id' => $this->expense_category_id,
            'project_id' => $this->project_id,
            'bank_account_id' => $this->bank_account_id,
            'account_id' => $this->account_id,
            'created_by' => $this->created_by,
            'vendor_name' => $this->vendor_name,
            'description' => $this->description,
            'gross_amount' => $gross,
            'vat_amount' => $vat,
            'amount' => round($gross - $vat, 2),
            'tax_category' => $this->tax_category,
            'reference' => $this->reference,
            'expense_date' => $this->next_run_date,
            'status' => $requiresApproval ? 'pending_approval' : 'approved',
        ]);

        if ($requiresApproval) {
            $this->notifyApprovers($expense);
        } else {
            App::make(LedgerPostingService::class)->postExpense($expense);
        }

        AuditLog::record('recurring_expense.generate', $expense, __('Generated expense from recurring expense ":title"', [
            'title' => $this->title,
        ]), $this->created_by);

        $nextRun = $this->nextRunDateAfter($this->next_run_date);

        $this->update([
            'last_generated_at' => now(),
            'generated_count' => $this->generated_count + 1,
            'next_run_date' => $nextRun,
            'status' => ($this->end_date && $nextRun->gt($this->end_date)) ? 'completed' : $this->status,
        ]);

        return $expense;
    }

    private function notifyApprovers(Expense $expense): void
    {
        User::where('company_id', $expense->company_id)
            ->get()
            ->filter(fn (User $user) => $user->hasPermission('approvals'))
            ->each(fn (User $user) => $user->notify(new GenericNotification(
                title: __('Expense awaiting approval'),
                body: __(':amount :currency from :vendor', ['amount' => number_format($expense->gross_amount, 2), 'currency' => $expense->company->currency, 'vendor' => $expense->vendor_name ?: __('Unknown vendor')]),
                url: route('app.expenses.index'),
                icon: 'purchases',
            )));
    }
}
