<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Company extends Model
{
    protected $fillable = [
        'name', 'name_ar', 'slug', 'vat_number', 'cr_number', 'address', 'city',
        'phone', 'email', 'logo_path', 'invoice_prefix', 'next_invoice_number',
        'quotation_prefix', 'next_quotation_number', 'proforma_prefix', 'next_proforma_number',
        'currency', 'locale', 'status', 'trial_ends_at', 'default_branch_id',
    ];

    // Mirrors the migration's DB-level defaults on the in-memory model:
    // Eloquent doesn't reflect column defaults on a freshly created()
    // instance unless it's refreshed, so nextInvoiceNumber() etc. would
    // otherwise operate on null attributes right after Company::create().
    protected $attributes = [
        'invoice_prefix' => 'INV',
        'next_invoice_number' => 1,
        'quotation_prefix' => 'QTN',
        'next_quotation_number' => 1,
        'proforma_prefix' => 'PRO',
        'next_proforma_number' => 1,
        'currency' => 'SAR',
        'locale' => 'en',
        'status' => 'active',
    ];

    protected function casts(): array
    {
        return [
            'trial_ends_at' => 'datetime',
        ];
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function clients(): HasMany
    {
        return $this->hasMany(Client::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(Item::class);
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }

    public function expenses(): HasMany
    {
        return $this->hasMany(Expense::class);
    }

    public function expenseCategories(): HasMany
    {
        return $this->hasMany(ExpenseCategory::class);
    }

    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function activeSubscription(): ?Subscription
    {
        return $this->subscriptions()
            ->whereIn('status', ['trialing', 'active'])
            ->latest('id')
            ->first();
    }

    public function nextInvoiceNumber(): string
    {
        $number = $this->invoice_prefix.'-'.str_pad((string) $this->next_invoice_number, 5, '0', STR_PAD_LEFT);
        $this->increment('next_invoice_number');

        return $number;
    }

    public function nextQuotationNumber(string $type = 'quotation'): string
    {
        if ($type === 'proforma') {
            $number = $this->proforma_prefix.'-'.str_pad((string) $this->next_proforma_number, 5, '0', STR_PAD_LEFT);
            $this->increment('next_proforma_number');

            return $number;
        }

        $number = $this->quotation_prefix.'-'.str_pad((string) $this->next_quotation_number, 5, '0', STR_PAD_LEFT);
        $this->increment('next_quotation_number');

        return $number;
    }

    public function quotations(): HasMany
    {
        return $this->hasMany(Quotation::class);
    }

    public function branches(): HasMany
    {
        return $this->hasMany(Branch::class);
    }

    public function defaultBranch(): ?Branch
    {
        return $this->default_branch_id ? Branch::find($this->default_branch_id) : null;
    }

    public function isSuspended(): bool
    {
        return $this->status === 'suspended';
    }
}
