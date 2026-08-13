<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Company extends Model
{
    protected $fillable = [
        'name', 'name_ar', 'slug', 'vat_number', 'cr_number', 'address', 'city',
        'phone', 'email', 'logo_path', 'invoice_prefix', 'next_invoice_number',
        'currency', 'locale', 'status', 'trial_ends_at',
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

    public function isSuspended(): bool
    {
        return $this->status === 'suspended';
    }
}
