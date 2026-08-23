<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RecurringInvoice extends Model
{
    use BelongsToCompany;

    public const FREQUENCIES = ['weekly', 'monthly', 'quarterly', 'yearly'];

    protected $fillable = [
        'company_id', 'client_id', 'branch_id', 'salesperson_id', 'project_id', 'bank_account_id', 'created_by',
        'title', 'type', 'frequency', 'due_days', 'start_date', 'next_run_date', 'end_date', 'status',
        'discount_total', 'retention_rate', 'notes', 'last_generated_at', 'generated_count',
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

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function bankAccount(): BelongsTo
    {
        return $this->belongsTo(BankAccount::class);
    }

    public function salesperson(): BelongsTo
    {
        return $this->belongsTo(Salesperson::class);
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(RecurringInvoiceItem::class);
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
     * The next run date after $from, per this recurrence's frequency.
     * Used both to seed next_run_date on creation and to advance it after
     * each generation — kept as one method so the two never drift apart.
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
     * Creates a real, draft Invoice + InvoiceItems from this template,
     * mirroring InvoiceController::store()'s computation exactly (same
     * totals/VAT/QR logic via Invoice::recalculateTotals()), then advances
     * this recurrence to its next run date (or marks it completed once
     * that would fall past end_date).
     */
    public function generateInvoice(): Invoice
    {
        $company = $this->company;
        $issueDate = $this->next_run_date;

        $invoice = \Illuminate\Support\Facades\DB::transaction(function () use ($company, $issueDate) {
            $invoice = Invoice::create([
                'company_id' => $this->company_id,
                'client_id' => $this->client_id,
                'branch_id' => $this->branch_id ?? $company->default_branch_id,
                'salesperson_id' => $this->salesperson_id,
                'project_id' => $this->project_id,
                'bank_account_id' => $this->bank_account_id,
                'created_by' => $this->created_by,
                'invoice_number' => $company->nextInvoiceNumber(),
                'type' => $this->type,
                'status' => 'draft',
                'issue_date' => $issueDate,
                'due_date' => $issueDate->copy()->addDays($this->due_days),
                'discount_total' => $this->discount_total,
                'retention_rate' => $this->retention_rate,
                'retention_amount' => 0,
                'currency' => $company->currency,
                'notes' => $this->notes,
            ]);

            foreach ($this->items()->orderBy('sort_order')->get() as $sort => $templateItem) {
                $line = new InvoiceItem([
                    'invoice_id' => $invoice->id,
                    'item_id' => $templateItem->item_id,
                    'unit_id' => $templateItem->unit_id,
                    'description' => $templateItem->description,
                    'quantity' => $templateItem->quantity,
                    'unit_price' => $templateItem->unit_price,
                    'vat_rate' => $templateItem->vat_rate,
                    'sort_order' => $sort,
                ]);
                $line->recalculate();
                $line->save();
            }

            $subtotal = $invoice->items()->get()->sum(fn ($item) => $item->quantity * $item->unit_price);
            $invoice->retention_amount = round($subtotal * ($this->retention_rate / 100), 2);
            $invoice->recalculateTotals();

            return $invoice;
        });

        AuditLog::record('recurring_invoice.generate', $invoice, __('Generated invoice :number from recurring invoice ":title"', [
            'number' => $invoice->invoice_number,
            'title' => $this->title,
        ]), $this->created_by);

        $nextRun = $this->nextRunDateAfter($this->next_run_date);

        $this->update([
            'last_generated_at' => now(),
            'generated_count' => $this->generated_count + 1,
            'next_run_date' => $nextRun,
            'status' => ($this->end_date && $nextRun->gt($this->end_date)) ? 'completed' : $this->status,
        ]);

        return $invoice;
    }
}
