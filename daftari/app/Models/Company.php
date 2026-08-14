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
        'receipt_prefix', 'next_receipt_number', 'payment_voucher_prefix', 'next_payment_voucher_number',
        'bill_prefix', 'next_bill_number', 'po_prefix', 'next_po_number',
        'currency', 'locale', 'status', 'trial_ends_at', 'default_branch_id',
        'default_bank_account_id', 'alternative_seller_id_type', 'alternative_seller_id',
        'primary_customer_type', 'negative_number_format',
        'zatca_environment', 'zatca_sync_frequency', 'zatca_sync_b2b', 'zatca_sync_b2c',
        'zatca_onboarding_status', 'zatca_egs_serial', 'zatca_csr', 'zatca_private_key',
        'zatca_compliance_request_id', 'zatca_compliance_csid', 'zatca_compliance_secret',
        'zatca_production_request_id', 'zatca_production_csid', 'zatca_production_secret',
        'zatca_last_invoice_hash', 'zatca_linked_at', 'zatca_last_sync_at',
        'project_prefix', 'next_project_number',
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
        'receipt_prefix' => 'RV',
        'next_receipt_number' => 1,
        'payment_voucher_prefix' => 'PV',
        'next_payment_voucher_number' => 1,
        'bill_prefix' => 'BILL',
        'next_bill_number' => 1,
        'po_prefix' => 'PO',
        'next_po_number' => 1,
        'journal_prefix' => 'JE',
        'next_journal_number' => 1,
        'project_prefix' => 'PROJ',
        'next_project_number' => 1,
        'primary_customer_type' => 'mixed',
        'negative_number_format' => 'minus',
        'currency' => 'SAR',
        'locale' => 'en',
        'status' => 'active',
        'zatca_environment' => 'developer',
        'zatca_sync_frequency' => 'manual',
        'zatca_sync_b2b' => true,
        'zatca_sync_b2c' => true,
        'zatca_onboarding_status' => 'not_started',
    ];

    protected function casts(): array
    {
        return [
            'trial_ends_at' => 'datetime',
            'zatca_sync_b2b' => 'boolean',
            'zatca_sync_b2c' => 'boolean',
            'zatca_private_key' => 'encrypted',
            'zatca_compliance_csid' => 'encrypted',
            'zatca_compliance_secret' => 'encrypted',
            'zatca_production_csid' => 'encrypted',
            'zatca_production_secret' => 'encrypted',
            'zatca_linked_at' => 'datetime',
            'zatca_last_sync_at' => 'datetime',
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

    public function bankAccounts(): HasMany
    {
        return $this->hasMany(BankAccount::class);
    }

    public function defaultBankAccount(): ?BankAccount
    {
        return $this->default_bank_account_id ? BankAccount::find($this->default_bank_account_id) : null;
    }

    public function formatNumber(float $amount, int $decimals = 2): string
    {
        $formatted = number_format(abs($amount), $decimals);

        if ($amount >= 0) {
            return $formatted;
        }

        return $this->negative_number_format === 'parentheses' ? "($formatted)" : "-$formatted";
    }

    public function nextReceiptNumber(): string
    {
        $number = $this->receipt_prefix.'-'.str_pad((string) $this->next_receipt_number, 5, '0', STR_PAD_LEFT);
        $this->increment('next_receipt_number');

        return $number;
    }

    public function nextPaymentVoucherNumber(): string
    {
        $number = $this->payment_voucher_prefix.'-'.str_pad((string) $this->next_payment_voucher_number, 5, '0', STR_PAD_LEFT);
        $this->increment('next_payment_voucher_number');

        return $number;
    }

    public function nextBillNumber(): string
    {
        $number = $this->bill_prefix.'-'.str_pad((string) $this->next_bill_number, 5, '0', STR_PAD_LEFT);
        $this->increment('next_bill_number');

        return $number;
    }

    public function nextPoNumber(): string
    {
        $number = $this->po_prefix.'-'.str_pad((string) $this->next_po_number, 5, '0', STR_PAD_LEFT);
        $this->increment('next_po_number');

        return $number;
    }

    public function nextJournalNumber(): string
    {
        $number = $this->journal_prefix.'-'.str_pad((string) $this->next_journal_number, 5, '0', STR_PAD_LEFT);
        $this->increment('next_journal_number');

        return $number;
    }

    public function nextProjectCode(): string
    {
        $number = $this->project_prefix.'_'.str_pad((string) $this->next_project_number, 6, '0', STR_PAD_LEFT);
        $this->increment('next_project_number');

        return $number;
    }

    public function projects(): HasMany
    {
        return $this->hasMany(Project::class);
    }

    public function suppliers(): HasMany
    {
        return $this->hasMany(Supplier::class);
    }

    public function bills(): HasMany
    {
        return $this->hasMany(Bill::class);
    }

    public function purchaseOrders(): HasMany
    {
        return $this->hasMany(PurchaseOrder::class);
    }

    public function defaultBranch(): ?Branch
    {
        return $this->default_branch_id ? Branch::find($this->default_branch_id) : null;
    }

    public function isSuspended(): bool
    {
        return $this->status === 'suspended';
    }

    public function zatcaInvoiceLogs(): HasMany
    {
        return $this->hasMany(ZatcaInvoiceLog::class);
    }

    public function invoiceTemplates(): HasMany
    {
        return $this->hasMany(InvoiceTemplate::class);
    }

    public function accounts(): HasMany
    {
        return $this->hasMany(Account::class);
    }

    public function accountMappings(): HasMany
    {
        return $this->hasMany(AccountMapping::class);
    }

    public function journalEntries(): HasMany
    {
        return $this->hasMany(JournalEntry::class);
    }

    public function costCenters(): HasMany
    {
        return $this->hasMany(CostCenter::class);
    }

    public function costCenterLinks(): HasMany
    {
        return $this->hasMany(CostCenterLink::class);
    }

    /**
     * The template that should style a given document type: the closest
     * default (type-specific default, else the "all types" default), or
     * null if the company hasn't set one up — callers fall back to the
     * hardcoded look.
     */
    public function defaultTemplateFor(string $documentType): ?InvoiceTemplate
    {
        $candidates = $this->invoiceTemplates()
            ->where('is_default', true)
            ->whereIn('document_type', [$documentType, 'all'])
            ->get();

        return $candidates->firstWhere('document_type', $documentType) ?? $candidates->firstWhere('document_type', 'all');
    }

    public function isZatcaOnboarded(): bool
    {
        return $this->zatca_onboarding_status === 'onboarded';
    }

    public function zatcaCsidFor(string $environment): ?string
    {
        return $environment === 'production' ? $this->zatca_production_csid : $this->zatca_compliance_csid;
    }

    public function zatcaSecretFor(string $environment): ?string
    {
        return $environment === 'production' ? $this->zatca_production_secret : $this->zatca_compliance_secret;
    }
}
