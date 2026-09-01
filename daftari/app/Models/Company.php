<?php

namespace App\Models;

use App\Services\Limits\UsageLimitService;
use App\Support\LimitRegistry;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Facades\DB;

class Company extends Model
{
    /**
     * Top-level ZATCA integration switch (see the add_zatca_integration_
     * mode_to_companies_table migration): 'disabled' turns off even the
     * always-on Phase 1 QR; 'phase1' is the historical default (QR on
     * every invoice, no ZATCA API calls); 'phase2' is required for the
     * onboarding wizard and any real clearance/reporting submission —
     * see isZatcaOnboarded(), the single choke point every sync path
     * (jobs, ZatcaSyncService, the scheduled command) already goes
     * through.
     */
    public const ZATCA_MODE_DISABLED = 'disabled';

    public const ZATCA_MODE_PHASE1 = 'phase1';

    public const ZATCA_MODE_PHASE2 = 'phase2';

    public const ZATCA_MODES = [self::ZATCA_MODE_DISABLED, self::ZATCA_MODE_PHASE1, self::ZATCA_MODE_PHASE2];

    protected $fillable = [
        'name', 'name_ar', 'organization_size', 'industry', 'slug', 'vat_number', 'cr_number', 'address', 'city',
        'building_number', 'street_name', 'district', 'postal_code', 'additional_number',
        'phone', 'email', 'logo_path', 'stamp_path', 'invoice_prefix', 'next_invoice_number',
        'credit_note_prefix', 'next_credit_note_number',
        'debit_note_prefix', 'next_debit_note_number',
        'purchase_return_prefix', 'next_purchase_return_number',
        'quotation_prefix', 'next_quotation_number', 'proforma_prefix', 'next_proforma_number',
        'receipt_prefix', 'next_receipt_number', 'payment_voucher_prefix', 'next_payment_voucher_number',
        'bill_prefix', 'next_bill_number', 'po_prefix', 'next_po_number',
        'currency', 'locale', 'timezone', 'status', 'is_demo', 'trial_ends_at', 'default_branch_id',
        'default_bank_account_id', 'alternative_seller_id_type', 'alternative_seller_id',
        'primary_customer_type', 'negative_number_format',
        'vat_makes_exempt_supplies', 'vat_recovery_percentage',
        'po_approval_threshold', 'expense_approval_threshold', 'accounting_lock_date',
        'invoice_approval_threshold', 'quotation_approval_threshold', 'invoice_dunning_enabled',
        'default_payment_terms_days',
        'zatca_environment', 'zatca_sync_frequency', 'zatca_sync_b2b', 'zatca_sync_b2c', 'zatca_integration_mode',
        'zatca_onboarding_status', 'zatca_egs_serial', 'zatca_common_name', 'zatca_organization_unit_name',
        'zatca_business_category', 'zatca_csr', 'zatca_private_key',
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
        'credit_note_prefix' => 'CN',
        'next_credit_note_number' => 1,
        'debit_note_prefix' => 'DN',
        'next_debit_note_number' => 1,
        'purchase_return_prefix' => 'PR',
        'next_purchase_return_number' => 1,
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
        'invoice_dunning_enabled' => true,
    ];

    protected function casts(): array
    {
        return [
            'trial_ends_at' => 'datetime',
            'is_demo' => 'boolean',
            'vat_makes_exempt_supplies' => 'boolean',
            'vat_recovery_percentage' => 'decimal:2',
            'zatca_sync_b2b' => 'boolean',
            'zatca_sync_b2c' => 'boolean',
            'zatca_private_key' => 'encrypted',
            'zatca_compliance_csid' => 'encrypted',
            'zatca_compliance_secret' => 'encrypted',
            'zatca_production_csid' => 'encrypted',
            'zatca_production_secret' => 'encrypted',
            'zatca_linked_at' => 'datetime',
            'zatca_last_sync_at' => 'datetime',
            'po_approval_threshold' => 'decimal:2',
            'expense_approval_threshold' => 'decimal:2',
            'invoice_approval_threshold' => 'decimal:2',
            'quotation_approval_threshold' => 'decimal:2',
            'invoice_dunning_enabled' => 'boolean',
            'default_payment_terms_days' => 'integer',
            'accounting_lock_date' => 'date',
        ];
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    /**
     * Who billing-related emails (welcome, receipts, renewal reminders) go
     * to — there can be more than one owner, and staff accounts shouldn't
     * see the company's payment details.
     */
    public function owners(): HasMany
    {
        return $this->hasMany(User::class)->where('role', 'owner');
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
            ->whereIn('status', Subscription::NON_TERMINAL_STATUSES)
            ->latest('id')
            ->first();
    }

    /**
     * Real (not estimated) storage usage: the sum of every attachment's
     * byte size, queried without the tenant scope since this is called
     * from platform-admin context where auth()->user() has no company_id.
     */
    public function storageUsedBytes(): int
    {
        return (int) Attachment::withoutGlobalScopes()->where('company_id', $this->id)->sum('size');
    }

    /**
     * Whether creating one more of $type would exceed the company's active
     * plan limit. A company with no active subscription, or a plan with no
     * limit set for $type (null = unlimited), is never blocked.
     *
     * The six keys also registered in App\Support\LimitRegistry (Module 07)
     * delegate to UsageLimitService, so they automatically become
     * override-aware (a Super Admin can raise or unlimit a single company's
     * cap) without any change to this method's 8 existing call sites.
     * invoice_templates/bank_accounts aren't part of that registry and keep
     * their original inline logic exactly as before.
     */
    public function hasReachedPlanLimit(string $type): bool
    {
        if (LimitRegistry::isValid($type)) {
            return app(UsageLimitService::class)->reached($this, $type);
        }

        $subscription = $this->activeSubscription();

        if (! $subscription) {
            return false;
        }

        $plan = $subscription->plan;

        [$limit, $used] = match ($type) {
            'invoice_templates' => [$plan->max_invoice_templates, $this->invoiceTemplates()->count()],
            'bank_accounts' => [$plan->max_bank_accounts, $this->bankAccounts()->count()],
            default => [null, 0],
        };

        return $limit !== null && $used >= $limit;
    }

    /**
     * Whether the company's active plan includes a given feature (see
     * Plan::FEATURE_KEYS). A company with no active subscription is never
     * blocked here, matching hasReachedPlanLimit()'s "never blocked
     * without a subscription" behavior.
     */
    public function hasFeature(string $key): bool
    {
        $subscription = $this->activeSubscription();

        if (! $subscription) {
            return true;
        }

        return $subscription->plan->hasFeature($key);
    }

    /**
     * Commercial audit finding A8: every next*Number() method used to read
     * its counter column from the already-loaded PHP model, then call
     * increment() (an atomic `col = col + 1` in SQL, but issued after the
     * read that produced the *displayed* number). Two requests that both
     * loaded the company before either committed could read the same
     * counter value and hand out the same document number — the DB-side
     * increments would still both apply, just one higher than the other,
     * leaving a gap and a duplicate instead of a clean sequence.
     *
     * lockForUpdate() re-reads the counter fresh and blocks any other
     * transaction trying to do the same until this one commits, so
     * concurrent saves are serialized on this row instead of racing.
     * Every caller already wraps its next*Number() call in its own
     * DB::transaction() (invoice/bill/etc. creation), so this join the
     * same transaction rather than opening a separate one.
     */
    private function nextSequenceNumber(string $column): int
    {
        return DB::transaction(function () use ($column) {
            $current = (int) self::query()->whereKey($this->id)->lockForUpdate()->value($column);

            self::query()->whereKey($this->id)->update([$column => $current + 1]);
            $this->{$column} = $current + 1;

            return $current;
        });
    }

    public function nextInvoiceNumber(): string
    {
        $number = $this->nextSequenceNumber('next_invoice_number');

        return $this->invoice_prefix.'-'.str_pad((string) $number, 5, '0', STR_PAD_LEFT);
    }

    public function nextCreditNoteNumber(): string
    {
        $number = $this->nextSequenceNumber('next_credit_note_number');

        return $this->credit_note_prefix.'-'.str_pad((string) $number, 5, '0', STR_PAD_LEFT);
    }

    public function nextDebitNoteNumber(): string
    {
        $number = $this->nextSequenceNumber('next_debit_note_number');

        return $this->debit_note_prefix.'-'.str_pad((string) $number, 5, '0', STR_PAD_LEFT);
    }

    public function nextPurchaseReturnNumber(): string
    {
        $number = $this->nextSequenceNumber('next_purchase_return_number');

        return $this->purchase_return_prefix.'-'.str_pad((string) $number, 5, '0', STR_PAD_LEFT);
    }

    public function nextQuotationNumber(string $type = 'quotation'): string
    {
        if ($type === 'proforma') {
            $number = $this->nextSequenceNumber('next_proforma_number');

            return $this->proforma_prefix.'-'.str_pad((string) $number, 5, '0', STR_PAD_LEFT);
        }

        $number = $this->nextSequenceNumber('next_quotation_number');

        return $this->quotation_prefix.'-'.str_pad((string) $number, 5, '0', STR_PAD_LEFT);
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

    public function warehouses(): HasMany
    {
        return $this->hasMany(Warehouse::class);
    }

    public function defaultBankAccount(): ?BankAccount
    {
        return $this->default_bank_account_id ? BankAccount::find($this->default_bank_account_id) : null;
    }

    public function poRequiresApproval(float $total): bool
    {
        return $this->po_approval_threshold !== null && $total >= (float) $this->po_approval_threshold;
    }

    public function expenseRequiresApproval(float $amount): bool
    {
        return $this->expense_approval_threshold !== null && $amount >= (float) $this->expense_approval_threshold;
    }

    public function invoiceRequiresApproval(float $total): bool
    {
        return $this->invoice_approval_threshold !== null && $total >= (float) $this->invoice_approval_threshold;
    }

    public function quotationRequiresApproval(float $total): bool
    {
        return $this->quotation_approval_threshold !== null && $total >= (float) $this->quotation_approval_threshold;
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
        $number = $this->nextSequenceNumber('next_receipt_number');

        return $this->receipt_prefix.'-'.str_pad((string) $number, 5, '0', STR_PAD_LEFT);
    }

    public function nextPaymentVoucherNumber(): string
    {
        $number = $this->nextSequenceNumber('next_payment_voucher_number');

        return $this->payment_voucher_prefix.'-'.str_pad((string) $number, 5, '0', STR_PAD_LEFT);
    }

    public function nextBillNumber(): string
    {
        $number = $this->nextSequenceNumber('next_bill_number');

        return $this->bill_prefix.'-'.str_pad((string) $number, 5, '0', STR_PAD_LEFT);
    }

    public function nextPoNumber(): string
    {
        $number = $this->nextSequenceNumber('next_po_number');

        return $this->po_prefix.'-'.str_pad((string) $number, 5, '0', STR_PAD_LEFT);
    }

    public function nextJournalNumber(): string
    {
        $number = $this->nextSequenceNumber('next_journal_number');

        return $this->journal_prefix.'-'.str_pad((string) $number, 5, '0', STR_PAD_LEFT);
    }

    public function nextProjectCode(): string
    {
        $number = $this->nextSequenceNumber('next_project_number');

        return $this->project_prefix.'_'.str_pad((string) $number, 6, '0', STR_PAD_LEFT);
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

    /**
     * True for the shared sandbox/demo company (Module 23) — never true
     * for a real customer's account. Checked by
     * App\Http\Middleware\PreventDemoDestruction and the payment/ZATCA
     * guards in BillingController/ZatcaSyncService/ZatcaController to
     * keep the demo account safe regardless of its own status/settings.
     */
    public function isDemo(): bool
    {
        return (bool) $this->is_demo;
    }

    public function zatcaInvoiceLogs(): HasMany
    {
        return $this->hasMany(ZatcaInvoiceLog::class);
    }

    public function tickets(): HasMany
    {
        return $this->hasMany(Ticket::class);
    }

    public function invoiceTemplates(): HasMany
    {
        return $this->hasMany(InvoiceTemplate::class);
    }

    public function accounts(): HasMany
    {
        return $this->hasMany(Account::class);
    }

    public function zakatCalculations(): HasMany
    {
        return $this->hasMany(ZakatCalculation::class);
    }

    public function fixedAssets(): HasMany
    {
        return $this->hasMany(FixedAsset::class);
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

    public function documents(): MorphMany
    {
        return $this->morphMany(Attachment::class, 'attachable')->whereNotNull('document_type')->latest('id');
    }

    /**
     * True only once ZATCA has actually issued a production CSID — not
     * just when the status flag says 'onboarded'. Onboarding used to
     * (incorrectly) set that flag straight after the compliance CSID
     * step for non-production environments, before any production
     * credential existed; checking the credential itself here means any
     * company still carrying that stale status self-heals back to "not
     * onboarded" instead of silently claiming a connection that was
     * never established.
     */
    public function isZatcaOnboarded(): bool
    {
        return $this->zatca_onboarding_status === 'onboarded'
            && (bool) $this->zatca_production_csid
            && $this->hasFeature('zatca_phase2')
            && $this->zatcaIntegrationMode() === self::ZATCA_MODE_PHASE2;
    }

    /**
     * Falls back to 'phase1' (not 'disabled') for a null/empty column —
     * the historical, always-on-QR behavior every company had before this
     * switch existed, so a row that predates the backfill (or a test
     * factory that doesn't set it) never silently loses its QR code.
     */
    public function zatcaIntegrationMode(): string
    {
        return in_array($this->zatca_integration_mode, self::ZATCA_MODES, true)
            ? $this->zatca_integration_mode
            : self::ZATCA_MODE_PHASE1;
    }

    public function isZatcaQrEnabled(): bool
    {
        return $this->zatcaIntegrationMode() !== self::ZATCA_MODE_DISABLED;
    }

    /**
     * The credential actual clearance/reporting submissions must
     * authenticate with. Despite the name, ZATCA requires this
     * production-CSID exchange in every environment (developer,
     * simulation, and production alike) — the compliance CSID from
     * onboarding step 2 is only valid for the compliance-check call
     * itself and is rejected (401) if used for clearance/reporting.
     */
    public function zatcaCsidFor(): ?string
    {
        return $this->zatca_production_csid;
    }

    public function zatcaSecretFor(): ?string
    {
        return $this->zatca_production_secret;
    }

    /**
     * Everything ZATCA Phase 2 onboarding needs from the company profile
     * before a CSR is worth generating — checked up front so a rejected
     * OTP/CSR at the Fatoora Portal (or a technically-valid-but-useless CSR)
     * doesn't surprise the user several steps in.
     *
     * @return array<int, array{key: string, label: string, ok: bool, hint: string}>
     */
    public function zatcaReadinessChecklist(): array
    {
        $vat = (string) $this->vat_number;
        $vatValid = (bool) preg_match('/^3\d{13}3$/', $vat);

        return [
            [
                'key' => 'vat_number',
                'label' => __('VAT registration number'),
                'ok' => $vatValid,
                'hint' => $vat === ''
                    ? __('Add your 15-digit VAT number in Settings.')
                    : __('Saudi VAT numbers are 15 digits, starting and ending with 3 (e.g. 300012345600003).'),
            ],
            [
                'key' => 'cr_number',
                'label' => __('Commercial registration (CR) number'),
                'ok' => filled($this->cr_number),
                'hint' => __('Add your CR number in Settings.'),
            ],
            [
                'key' => 'name',
                'label' => __('Legal company name'),
                'ok' => filled($this->name),
                'hint' => __('Add your registered company name in Settings.'),
            ],
            [
                'key' => 'street_name',
                'label' => __('Street name'),
                'ok' => filled($this->street_name ?: $this->address),
                'hint' => __('Add your street name in Settings → Address.'),
            ],
            [
                'key' => 'building_number',
                'label' => __('Building number'),
                'ok' => filled($this->building_number),
                'hint' => __('Add your 4-digit National Address building number in Settings → Address.'),
            ],
            [
                'key' => 'district',
                'label' => __('District'),
                'ok' => filled($this->district),
                'hint' => __('Add your district (neighborhood) in Settings → Address.'),
            ],
            [
                'key' => 'city',
                'label' => __('City'),
                'ok' => filled($this->city),
                'hint' => __('Add your city in Settings → Address.'),
            ],
            [
                'key' => 'postal_code',
                'label' => __('Postal code'),
                'ok' => filled($this->postal_code),
                'hint' => __('Add your 5-digit postal code in Settings → Address.'),
            ],
        ];
    }

    public function isZatcaReady(): bool
    {
        return collect($this->zatcaReadinessChecklist())->every(fn (array $check) => $check['ok']);
    }
}
