<?php

namespace Database\Seeders;

use App\Models\Account;
use App\Models\AccountMapping;
use App\Models\BankAccount;
use App\Models\BankTransfer;
use App\Models\Bill;
use App\Models\BillItem;
use App\Models\BillPayment;
use App\Models\Branch;
use App\Models\Client;
use App\Models\Company;
use App\Models\CreditNote;
use App\Models\CreditNoteItem;
use App\Models\CustomsDeclaration;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\InvoicePayment;
use App\Models\Item;
use App\Models\ItemStock;
use App\Models\PaymentVoucher;
use App\Models\Plan;
use App\Models\Project;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\Quotation;
use App\Models\QuotationItem;
use App\Models\ReceiptVoucher;
use App\Models\Role;
use App\Models\Salesperson;
use App\Models\StockAdjustment;
use App\Models\Subscription;
use App\Models\Supplier;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\Accounting\LedgerPostingService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * Builds (or refreshes) the one shared demo company and every module's
 * sample data for it — company, users, customers, suppliers, products,
 * invoices, expenses, payments. There is no separate "reports" seed step:
 * every report in the app (Sales, P&L, VAT return, Balance Sheet, ...) is
 * computed live from this same transactional data, nothing is
 * pre-materialized, so reports are automatically populated once the rest
 * of this runs.
 *
 * Entirely self-contained and idempotent: every row here is scoped to the
 * one company created below (found/created by its fixed slug), so
 * re-running this never touches any other company's data. Invoked via
 * `php artisan demo:install` (see App\Console\Commands\InstallDemoData for
 * the documented, easy-to-run wrapper) or directly with
 * `php artisan db:seed --class=DemoSeeder`.
 */
class DemoSeeder extends Seeder
{
    public function run(): void
    {
        $company = Company::updateOrCreate(
            ['slug' => 'al-rashid-trading'],
            [
                'name' => 'Al Rashid Trading Co.',
                'name_ar' => 'شركة الراشد التجارية',
                'vat_number' => '300012345600003',
                'cr_number' => '1010123456',
                'address' => 'King Fahd Road, Riyadh',
                'building_number' => '7845',
                'street_name' => 'King Fahd Road',
                'district' => 'Al Olaya',
                'city' => 'Riyadh',
                'postal_code' => '12211',
                'additional_number' => '3411',
                'phone' => '+966500000000',
                'email' => 'owner@daftari.local',
                'currency' => 'SAR',
                'status' => 'active',
                // Module 23: marks this company as the shared, safe demo
                // account — see App\Support\DemoMode and
                // PreventDemoDestruction. Never set on a real customer.
                'is_demo' => true,
                'trial_ends_at' => now()->addDays(config('daftari.trial_days')),
            ]
        );

        // Re-running the seeder shouldn't pile up duplicate transactional
        // data — only build the rest of the dataset the first time.
        $alreadySeeded = $company->invoices()->exists();

        Role::seedSystemRoles($company->id);
        Account::seedSystemAccounts($company->id);
        AccountMapping::seedDefaults($company->id);

        $owner = User::updateOrCreate(
            ['email' => 'owner@daftari.local'],
            [
                'company_id' => $company->id,
                'name' => 'Fahad Al Rashid',
                'password' => Hash::make('Demo@12345'),
                'role' => 'owner',
                'status' => 'active',
            ]
        );

        // Two more demo team members (not just the owner) — Module 23 asks
        // for "demo users", plural — each attached to one of the system
        // roles Role::seedSystemRoles() just created, so their permission
        // sets are real and immediately usable for demoing Roles &
        // Permissions itself, not just extra login rows.
        $accountantRole = Role::where('company_id', $company->id)->where('slug', 'accountant')->first();
        $accountantUser = User::updateOrCreate(
            ['email' => 'accountant@daftari.local'],
            ['company_id' => $company->id, 'name' => 'Lama Al Zahrani', 'password' => Hash::make('Demo@12345'), 'role' => 'member', 'status' => 'active']
        );
        if ($accountantRole) {
            $accountantUser->roles()->sync([$accountantRole->id]);
        }

        $salesRole = Role::where('company_id', $company->id)->where('slug', 'sales')->first();
        $salesUser = User::updateOrCreate(
            ['email' => 'sales@daftari.local'],
            ['company_id' => $company->id, 'name' => 'Omar Al Ghamdi', 'password' => Hash::make('Demo@12345'), 'role' => 'member', 'status' => 'active']
        );
        if ($salesRole) {
            $salesUser->roles()->sync([$salesRole->id]);
        }

        $plan = Plan::where('slug', 'professional')->first();
        if ($plan && ! $company->subscriptions()->exists()) {
            Subscription::create([
                'company_id' => $company->id,
                'plan_id' => $plan->id,
                'status' => 'active',
                'billing_cycle' => 'monthly',
                'current_period_start' => now()->subDays(10),
                'current_period_end' => now()->addDays(20),
            ]);

            $company->payments()->create([
                'plan_id' => $plan->id,
                'amount' => $plan->price_monthly,
                'currency' => 'SAR',
                'status' => 'paid',
                'method' => 'manual',
                'paid_at' => now()->subDays(10),
            ]);
        }

        if ($alreadySeeded) {
            return;
        }

        $ledger = app(LedgerPostingService::class);

        // --- Branches, warehouses, bank accounts, salespersons ------------

        $mainBranch = Branch::create([
            'company_id' => $company->id, 'code' => 'BR-001', 'name' => 'Riyadh HQ', 'name_ar' => 'الرياض - المركز الرئيسي',
            'city' => 'Riyadh', 'country' => 'Saudi Arabia', 'phone' => '+966500000000', 'email' => 'riyadh@daftari.local',
        ]);
        $jeddahBranch = Branch::create([
            'company_id' => $company->id, 'code' => 'BR-002', 'name' => 'Jeddah Branch', 'name_ar' => 'فرع جدة',
            'city' => 'Jeddah', 'country' => 'Saudi Arabia', 'phone' => '+966522222222', 'email' => 'jeddah@daftari.local',
        ]);
        $company->update(['default_branch_id' => $mainBranch->id]);

        $mainWarehouse = Warehouse::create([
            'company_id' => $company->id, 'code' => 'WH-001', 'branch_id' => $mainBranch->id,
            'name' => 'Riyadh Main Warehouse', 'name_ar' => 'مستودع الرياض الرئيسي', 'is_default' => true,
        ]);
        $jeddahWarehouse = Warehouse::create([
            'company_id' => $company->id, 'code' => 'WH-002', 'branch_id' => $jeddahBranch->id,
            'name' => 'Jeddah Warehouse', 'name_ar' => 'مستودع جدة',
        ]);

        $bankAccount = BankAccount::create([
            'company_id' => $company->id, 'name' => 'Al Rahji Bank - Current Account', 'bank_name' => 'Al Rajhi Bank',
            'account_holder_name' => $company->name, 'account_number' => '338608010123456',
            'iban' => 'SA0380000000608010123456', 'type' => 'bank', 'opening_balance' => 150000,
            'opening_balance_date' => now()->subMonths(3), 'currency' => 'SAR', 'is_active' => true,
        ]);
        $cashAccount = BankAccount::create([
            'company_id' => $company->id, 'name' => 'Petty Cash', 'type' => 'cash',
            'opening_balance' => 5000, 'opening_balance_date' => now()->subMonths(3), 'currency' => 'SAR', 'is_active' => true,
        ]);
        $company->update(['default_bank_account_id' => $bankAccount->id]);

        $salesperson1 = Salesperson::create(['company_id' => $company->id, 'name' => 'Khalid Al Otaibi', 'email' => 'khalid@daftari.local', 'phone' => '+966533333333', 'commission_rate' => 5, 'is_active' => true]);
        $salesperson2 = Salesperson::create(['company_id' => $company->id, 'name' => 'Sara Al Harbi', 'email' => 'sara@daftari.local', 'phone' => '+966544444444', 'commission_rate' => 4, 'is_active' => true]);

        // --- Clients & suppliers -------------------------------------------

        $client = Client::create([
            'company_id' => $company->id, 'type' => 'company', 'name' => 'Najd Construction Supplies', 'name_ar' => 'مؤسسة نجد لتوريدات البناء',
            'vat_number' => '300098765400003', 'email' => 'accounts@najd-supplies.example', 'phone' => '+966511111111',
            'street_name' => 'Al Amir Mohammed Bin Abdulaziz Rd', 'building_number' => '2210', 'district' => 'Al Malaz',
            'city' => 'Riyadh', 'postal_code' => '11439', 'country' => 'Saudi Arabia',
        ]);
        $client2 = Client::create([
            'company_id' => $company->id, 'type' => 'company', 'name' => 'Jeddah Modern Interiors', 'name_ar' => 'جدة للديكورات الحديثة',
            'vat_number' => '300055566600003', 'email' => 'billing@jmi.example', 'phone' => '+966522223333',
            'city' => 'Jeddah', 'country' => 'Saudi Arabia',
        ]);
        $client3 = Client::create([
            'company_id' => $company->id, 'type' => 'individual', 'name' => 'Abdullah Al Qahtani', 'email' => 'abdullah.q@example.com',
            'phone' => '+966555556666', 'city' => 'Riyadh', 'country' => 'Saudi Arabia',
        ]);
        $client4 = Client::create([
            'company_id' => $company->id, 'type' => 'company', 'name' => 'Eastern Province Traders', 'name_ar' => 'تجار المنطقة الشرقية',
            'vat_number' => '300077788800003', 'email' => 'ap@ep-traders.example', 'phone' => '+966533445566',
            'city' => 'Dammam', 'country' => 'Saudi Arabia',
        ]);

        $supplier = Supplier::create([
            'company_id' => $company->id, 'supplier_code' => 'SUP-0001', 'type' => 'company', 'name' => 'Riyadh Office Supplies Co.',
            'name_ar' => 'شركة الرياض للتوريدات المكتبية', 'vat_number' => '300011122200003', 'email' => 'sales@riyadh-office.example',
            'phone' => '+966511112222', 'city' => 'Riyadh', 'country' => 'Saudi Arabia',
        ]);
        $supplier2 = Supplier::create([
            'company_id' => $company->id, 'supplier_code' => 'SUP-0002', 'type' => 'company', 'name' => 'Gulf Building Materials',
            'name_ar' => 'مواد البناء الخليجية', 'vat_number' => '300033344400003', 'email' => 'orders@gulf-materials.example',
            'phone' => '+966533334444', 'city' => 'Dammam', 'country' => 'Saudi Arabia',
        ]);

        // --- Items (services + trackable physical stock) -------------------

        $consulting = Item::create(['company_id' => $company->id, 'name' => 'Consulting services', 'name_ar' => 'خدمات استشارية', 'item_type' => 'service', 'unit' => 'hour', 'unit_code' => 'HUR', 'unit_price' => 250, 'purchase_price' => 0, 'vat_rate' => 15, 'is_active' => true, 'track_inventory' => false]);
        $installation = Item::create(['company_id' => $company->id, 'name' => 'Installation labor', 'name_ar' => 'أعمال التركيب', 'item_type' => 'service', 'unit' => 'day', 'unit_code' => 'DAY', 'unit_price' => 600, 'purchase_price' => 0, 'vat_rate' => 15, 'is_active' => true, 'track_inventory' => false]);
        $cement = Item::create(['company_id' => $company->id, 'name' => 'Cement bags (50kg)', 'name_ar' => 'أكياس أسمنت (50 كجم)', 'sku' => 'CEM-050', 'item_type' => 'physical', 'unit' => 'bag', 'unit_code' => 'BG', 'unit_price' => 22, 'purchase_price' => 15, 'vat_rate' => 15, 'is_active' => true, 'track_inventory' => true, 'reorder_point' => 100]);
        $steel = Item::create(['company_id' => $company->id, 'name' => 'Steel rebar 12mm (ton)', 'name_ar' => 'حديد تسليح 12مم (طن)', 'sku' => 'STL-012', 'item_type' => 'physical', 'unit' => 'ton', 'unit_code' => 'TNE', 'unit_price' => 2850, 'purchase_price' => 2400, 'vat_rate' => 15, 'is_active' => true, 'track_inventory' => true, 'reorder_point' => 5]);
        $paint = Item::create(['company_id' => $company->id, 'name' => 'Interior paint (18L)', 'name_ar' => 'دهان داخلي (18 لتر)', 'sku' => 'PNT-018', 'item_type' => 'physical', 'unit' => 'can', 'unit_code' => 'CA', 'unit_price' => 145, 'purchase_price' => 95, 'vat_rate' => 15, 'is_active' => true, 'track_inventory' => true, 'reorder_point' => 20]);
        $tiles = Item::create(['company_id' => $company->id, 'name' => 'Ceramic floor tiles (m²)', 'name_ar' => 'بلاط أرضيات سيراميك (م²)', 'sku' => 'TIL-060', 'item_type' => 'physical', 'unit' => 'sqm', 'unit_code' => 'MTK', 'unit_price' => 38, 'purchase_price' => 26, 'vat_rate' => 15, 'is_active' => true, 'track_inventory' => true, 'reorder_point' => 200]);

        foreach ([$cement, $steel, $paint, $tiles] as $item) {
            ItemStock::create(['item_id' => $item->id, 'warehouse_id' => $mainWarehouse->id, 'quantity' => 500]);
            ItemStock::create(['item_id' => $item->id, 'warehouse_id' => $jeddahWarehouse->id, 'quantity' => 150]);
        }

        $expenseCategory = ExpenseCategory::create(['company_id' => $company->id, 'name' => 'Office supplies', 'name_ar' => 'مستلزمات مكتبية']);
        $utilitiesCategory = ExpenseCategory::create(['company_id' => $company->id, 'name' => 'Utilities', 'name_ar' => 'مرافق']);
        $rentCategory = ExpenseCategory::create(['company_id' => $company->id, 'name' => 'Rent', 'name_ar' => 'إيجار']);

        $makeLine = function (int $quantity, float $unitPrice, float $vatRate, string $description, ?int $itemId = null): array {
            $subtotal = $quantity * $unitPrice;
            $vat = round($subtotal * ($vatRate / 100), 2);

            return [
                'item_id' => $itemId, 'description' => $description, 'quantity' => $quantity, 'unit_price' => $unitPrice,
                'vat_rate' => $vatRate, 'vat_amount' => $vat, 'line_total' => round($subtotal + $vat, 2), 'sort_order' => 0,
            ];
        };

        // --- Invoices across every status -----------------------------------

        // 1) Paid in full, stock-tracked items, delivered from the main warehouse.
        $invoicePaid = Invoice::create([
            'company_id' => $company->id, 'client_id' => $client->id, 'branch_id' => $mainBranch->id, 'salesperson_id' => $salesperson1->id,
            'created_by' => $owner->id, 'invoice_number' => $company->nextInvoiceNumber(), 'type' => 'standard', 'status' => 'sent',
            'issue_date' => now()->subDays(25), 'due_date' => now()->subDays(5), 'currency' => 'SAR',
            'bank_account_id' => $bankAccount->id, 'warehouse_id' => $mainWarehouse->id,
        ]);
        foreach ([$makeLine(200, $cement->unit_price, 15, $cement->name, $cement->id), $makeLine(2, $steel->unit_price, 15, $steel->name, $steel->id)] as $line) {
            InvoiceItem::create(array_merge($line, ['invoice_id' => $invoicePaid->id]));
        }
        $invoicePaid->recalculateTotals();
        $ledger->postInvoiceIssued($invoicePaid);
        foreach ([$cement->id => 200, $steel->id => 2] as $itemId => $qty) {
            ItemStock::where('item_id', $itemId)->where('warehouse_id', $mainWarehouse->id)->decrement('quantity', $qty);
        }
        $invoicePaid->update(['stock_deducted' => true]);
        $payment1 = InvoicePayment::create(['invoice_id' => $invoicePaid->id, 'amount' => $invoicePaid->total, 'paid_at' => now()->subDays(20), 'method' => 'bank_transfer', 'reference' => 'TRX-1029']);
        $ledger->postInvoicePayment($payment1);
        $invoicePaid->update(['amount_paid' => $invoicePaid->invoicePayments()->sum('amount'), 'status' => 'paid']);

        // 2) Partially paid, with retention withheld.
        $invoicePartial = Invoice::create([
            'company_id' => $company->id, 'client_id' => $client2->id, 'branch_id' => $mainBranch->id, 'salesperson_id' => $salesperson2->id,
            'created_by' => $owner->id, 'invoice_number' => $company->nextInvoiceNumber(), 'type' => 'standard', 'status' => 'sent',
            'issue_date' => now()->subDays(15), 'due_date' => now()->addDays(15), 'currency' => 'SAR', 'bank_account_id' => $bankAccount->id,
            'retention_rate' => 10,
        ]);
        InvoiceItem::create(array_merge($makeLine(30, $installation->unit_price, 15, $installation->name, $installation->id), ['invoice_id' => $invoicePartial->id]));
        $invoicePartial->recalculateTotals();
        $invoicePartial->update(['retention_amount' => round((float) $invoicePartial->subtotal * 0.10, 2)]);
        $ledger->postInvoiceIssued($invoicePartial->fresh());
        $payment2 = InvoicePayment::create(['invoice_id' => $invoicePartial->id, 'amount' => 5000, 'paid_at' => now()->subDays(8), 'method' => 'bank_transfer', 'reference' => 'TRX-1044']);
        $ledger->postInvoicePayment($payment2);
        $invoicePartial->update(['amount_paid' => $invoicePartial->invoicePayments()->sum('amount'), 'status' => 'partially_paid']);

        // 3) Sent, unpaid, now overdue.
        $invoiceOverdue = Invoice::create([
            'company_id' => $company->id, 'client_id' => $client3->id, 'branch_id' => $mainBranch->id,
            'created_by' => $owner->id, 'invoice_number' => $company->nextInvoiceNumber(), 'type' => 'standard', 'status' => 'sent',
            'issue_date' => now()->subDays(45), 'due_date' => now()->subDays(15), 'currency' => 'SAR', 'bank_account_id' => $bankAccount->id,
        ]);
        InvoiceItem::create(array_merge($makeLine(4, $consulting->unit_price, 15, $consulting->name, $consulting->id), ['invoice_id' => $invoiceOverdue->id]));
        $invoiceOverdue->recalculateTotals();
        $ledger->postInvoiceIssued($invoiceOverdue);

        // 4) Draft — never sent, no posting.
        $invoiceDraft = Invoice::create([
            'company_id' => $company->id, 'client_id' => $client4->id, 'branch_id' => $jeddahBranch->id,
            'created_by' => $owner->id, 'invoice_number' => $company->nextInvoiceNumber(), 'type' => 'standard', 'status' => 'draft',
            'issue_date' => now(), 'due_date' => now()->addDays(30), 'currency' => 'SAR',
        ]);
        InvoiceItem::create(array_merge($makeLine(50, $tiles->unit_price, 15, $tiles->name, $tiles->id), ['invoice_id' => $invoiceDraft->id]));
        $invoiceDraft->recalculateTotals();

        // 5) Cancelled.
        $invoiceCancelled = Invoice::create([
            'company_id' => $company->id, 'client_id' => $client->id, 'branch_id' => $mainBranch->id,
            'created_by' => $owner->id, 'invoice_number' => $company->nextInvoiceNumber(), 'type' => 'standard', 'status' => 'sent',
            'issue_date' => now()->subDays(10), 'due_date' => now()->addDays(20), 'currency' => 'SAR',
        ]);
        InvoiceItem::create(array_merge($makeLine(10, $paint->unit_price, 15, $paint->name, $paint->id), ['invoice_id' => $invoiceCancelled->id]));
        $invoiceCancelled->recalculateTotals();
        $ledger->postInvoiceIssued($invoiceCancelled);
        $ledger->reverse($invoiceCancelled->company, 'invoice', $invoiceCancelled->id, __('Invoice :number cancelled', ['number' => $invoiceCancelled->invoice_number]));
        $invoiceCancelled->update(['status' => 'cancelled']);

        // --- Credit note against the paid invoice ---------------------------

        $creditNote = CreditNote::create([
            'company_id' => $company->id, 'invoice_id' => $invoicePaid->id, 'client_id' => $invoicePaid->client_id, 'branch_id' => $mainBranch->id,
            'created_by' => $owner->id, 'credit_note_number' => $company->nextCreditNoteNumber(), 'issue_date' => now()->subDays(3),
            'reason' => 'Partial return of cement bags', 'status' => 'issued', 'currency' => 'SAR',
        ]);
        $originalCementLine = $invoicePaid->items()->where('item_id', $cement->id)->first();
        CreditNoteItem::create([
            'credit_note_id' => $creditNote->id, 'invoice_item_id' => $originalCementLine?->id, 'description' => $cement->name,
            'quantity' => 20, 'unit_price' => $cement->unit_price, 'vat_rate' => 15,
            'vat_amount' => round(20 * $cement->unit_price * 0.15, 2), 'line_total' => round(20 * $cement->unit_price * 1.15, 2),
        ]);
        $creditNote->recalculateTotals();
        $ledger->postCreditNote($creditNote);

        // --- Quotations across statuses --------------------------------------

        foreach ([
            ['status' => 'draft', 'client' => $client3, 'item' => $consulting, 'qty' => 10],
            ['status' => 'issued', 'client' => $client2, 'item' => $installation, 'qty' => 5],
            ['status' => 'accepted', 'client' => $client4, 'item' => $tiles, 'qty' => 120],
            ['status' => 'rejected', 'client' => $client, 'item' => $steel, 'qty' => 3],
        ] as $q) {
            $quotation = Quotation::create([
                'company_id' => $company->id, 'client_id' => $q['client']->id, 'branch_id' => $mainBranch->id, 'salesperson_id' => $salesperson1->id,
                'created_by' => $owner->id, 'quotation_number' => $company->nextQuotationNumber(), 'type' => 'quotation', 'status' => $q['status'],
                'issue_date' => now()->subDays(random_int(2, 20)), 'expiry_date' => now()->addDays(15), 'currency' => 'SAR', 'bank_account_id' => $bankAccount->id,
            ]);
            QuotationItem::create(array_merge($makeLine($q['qty'], $q['item']->unit_price, 15, $q['item']->name, $q['item']->id), ['quotation_id' => $quotation->id]));
            $quotation->recalculateTotals();
        }

        // --- Bills (purchases) across statuses -------------------------------

        $billPosted = Bill::create([
            'company_id' => $company->id, 'supplier_id' => $supplier->id, 'branch_id' => $mainBranch->id, 'created_by' => $owner->id,
            'bill_number' => $company->nextBillNumber(), 'supplier_reference' => 'INV-8841', 'status' => 'draft',
            'bill_date' => now()->subDays(20), 'due_date' => now()->addDays(10), 'currency' => 'SAR',
        ]);
        BillItem::create(array_merge($makeLine(20, 55, 15, 'Printer paper, toner & stationery', null), ['bill_id' => $billPosted->id]));
        $billPosted->recalculateTotals();
        $billPosted->update(['status' => 'posted']);
        $ledger->postBillPosted($billPosted);
        $billPayment = BillPayment::create(['bill_id' => $billPosted->id, 'amount' => $billPosted->total, 'paid_at' => now()->subDays(15), 'method' => 'bank_transfer', 'reference' => 'PAY-2201']);
        $ledger->postBillPayment($billPayment);
        $billPosted->update(['amount_paid' => $billPosted->billPayments()->sum('amount')]);

        $billUnpaid = Bill::create([
            'company_id' => $company->id, 'supplier_id' => $supplier2->id, 'branch_id' => $mainBranch->id, 'created_by' => $owner->id,
            'bill_number' => $company->nextBillNumber(), 'supplier_reference' => 'GBM-5521', 'status' => 'draft',
            'bill_date' => now()->subDays(6), 'due_date' => now()->addDays(24), 'currency' => 'SAR',
        ]);
        BillItem::create(array_merge($makeLine(300, $cement->purchase_price, 15, $cement->name, $cement->id), ['bill_id' => $billUnpaid->id]));
        $billUnpaid->recalculateTotals();
        $billUnpaid->update(['status' => 'posted']);
        $ledger->postBillPosted($billUnpaid);

        $billDraft = Bill::create([
            'company_id' => $company->id, 'supplier_id' => $supplier->id, 'branch_id' => $jeddahBranch->id, 'created_by' => $owner->id,
            'bill_number' => $company->nextBillNumber(), 'status' => 'draft', 'bill_date' => now(), 'due_date' => now()->addDays(30), 'currency' => 'SAR',
        ]);
        BillItem::create(array_merge($makeLine(50, $paint->purchase_price, 15, $paint->name, $paint->id), ['bill_id' => $billDraft->id]));
        $billDraft->recalculateTotals();

        // --- Purchase orders --------------------------------------------------

        $poApproved = PurchaseOrder::create([
            'company_id' => $company->id, 'supplier_id' => $supplier2->id, 'branch_id' => $mainBranch->id, 'created_by' => $owner->id,
            'po_number' => $company->nextPoNumber(), 'status' => 'draft', 'order_date' => now()->subDays(4), 'expected_date' => now()->addDays(10),
        ]);
        PurchaseOrderItem::create(array_merge($makeLine(5, $steel->purchase_price, 15, $steel->name, $steel->id), ['purchase_order_id' => $poApproved->id]));
        $poApproved->recalculateTotals();
        $poApproved->update(['status' => 'approved']);

        $poDraft = PurchaseOrder::create([
            'company_id' => $company->id, 'supplier_id' => $supplier->id, 'branch_id' => $mainBranch->id, 'created_by' => $owner->id,
            'po_number' => $company->nextPoNumber(), 'status' => 'draft', 'order_date' => now(), 'expected_date' => now()->addDays(14),
        ]);
        PurchaseOrderItem::create(array_merge($makeLine(200, $tiles->purchase_price, 15, $tiles->name, $tiles->id), ['purchase_order_id' => $poDraft->id]));
        $poDraft->recalculateTotals();

        // --- Customs declaration, linked to a bill -----------------------------

        $customs = CustomsDeclaration::create([
            'company_id' => $company->id, 'supplier_id' => $supplier2->id, 'created_by' => $owner->id,
            'declaration_number' => 'CD-'.now()->format('Y').'-00001', 'declaration_date' => now()->subDays(18),
            'port_of_entry' => 'King Abdulaziz Port, Dammam', 'customs_value' => 40000, 'customs_duty' => 2000,
            'vat_rate' => 15, 'vat_amount' => round((40000 + 2000) * 0.15, 2), 'sadad_reference' => 'SADAD-778821',
        ]);
        $customs->bills()->sync([$billUnpaid->id]);
        $ledger->postCustomsDeclaration($customs);

        // --- Expenses: one paid from the bank, one accrued, one zero-rated -----

        $expensePaid = Expense::create([
            'company_id' => $company->id, 'expense_category_id' => $expenseCategory->id, 'bank_account_id' => $cashAccount->id, 'created_by' => $owner->id,
            'vendor_name' => 'Riyadh Office Supplies', 'description' => 'Printer paper and toner', 'gross_amount' => 517,
            'tax_category' => 'standard_15', 'amount' => round(517 / 1.15, 2), 'vat_amount' => round(517 - 517 / 1.15, 2), 'expense_date' => now()->subDays(7),
        ]);
        $ledger->postExpense($expensePaid);

        $expenseAccrued = Expense::create([
            'company_id' => $company->id, 'expense_category_id' => $rentCategory->id, 'created_by' => $owner->id,
            'vendor_name' => 'Riyadh Commercial Properties', 'description' => 'Warehouse rent — this month', 'gross_amount' => 23000,
            'tax_category' => 'standard_15', 'amount' => round(23000 / 1.15, 2), 'vat_amount' => round(23000 - 23000 / 1.15, 2), 'expense_date' => now()->subDays(2),
        ]);
        $ledger->postExpense($expenseAccrued);

        $expenseZeroRated = Expense::create([
            'company_id' => $company->id, 'expense_category_id' => $utilitiesCategory->id, 'bank_account_id' => $bankAccount->id, 'created_by' => $owner->id,
            'vendor_name' => 'Saudi Electricity Company', 'description' => 'Electricity — warehouse', 'gross_amount' => 1840,
            'tax_category' => 'zero_rated', 'amount' => 1840, 'vat_amount' => 0, 'expense_date' => now()->subDays(4),
        ]);
        $ledger->postExpense($expenseZeroRated);

        // --- Vouchers: a receipt and a payment on account -----------------------

        $receiptVoucher = ReceiptVoucher::create([
            'company_id' => $company->id, 'bank_account_id' => $bankAccount->id, 'party_type' => 'customer', 'client_id' => $invoiceOverdue->client_id,
            'created_by' => $owner->id, 'voucher_number' => $company->nextReceiptNumber(), 'date' => now()->subDays(3), 'payer_name' => $invoiceOverdue->client->name,
            'amount' => 2500, 'method' => 'cheque', 'reference' => 'CHQ-6612', 'notes' => 'On-account payment, not yet applied to an invoice.', 'status' => 'confirmed',
        ]);
        $ledger->postReceiptVoucher($receiptVoucher);

        $paymentVoucher = PaymentVoucher::create([
            'company_id' => $company->id, 'bank_account_id' => $cashAccount->id, 'party_type' => 'supplier', 'supplier_id' => $supplier->id,
            'created_by' => $owner->id, 'voucher_number' => $company->nextPaymentVoucherNumber(), 'date' => now()->subDays(1), 'payee_name' => $supplier->name,
            'amount' => 800, 'method' => 'cash', 'reference' => 'ADV-119', 'notes' => 'Advance payment on account.', 'status' => 'confirmed',
        ]);
        $ledger->postPaymentVoucher($paymentVoucher);

        // --- Bank transfer & a manual stock adjustment ---------------------------

        $transfer = BankTransfer::create([
            'company_id' => $company->id, 'from_bank_account_id' => $bankAccount->id, 'to_bank_account_id' => $cashAccount->id,
            'created_by' => $owner->id, 'amount' => 3000, 'date' => now()->subDays(6), 'notes' => 'Petty cash top-up.',
        ]);
        $ledger->postBankTransfer($transfer);

        $adjustment = StockAdjustment::create([
            'company_id' => $company->id, 'item_id' => $paint->id, 'warehouse_id' => $mainWarehouse->id, 'created_by' => $owner->id,
            'type' => 'decrease', 'quantity' => 5, 'reason' => 'Damaged in storage', 'date' => now()->subDays(9), 'status' => 'confirmed',
        ]);
        ItemStock::where('item_id', $paint->id)->where('warehouse_id', $mainWarehouse->id)->decrement('quantity', 5);
        $ledger->postStockAdjustment($adjustment);

        // --- Projects -------------------------------------------------------

        Project::create([
            'company_id' => $company->id, 'code' => $company->nextProjectCode(), 'name' => 'Al Malaz Villa Renovation', 'name_ar' => 'ترميم فيلا الملز',
            'status' => 'active', 'client_id' => $client->id, 'start_date' => now()->subDays(30), 'end_date' => now()->addDays(60),
            'target_revenue' => 250000, 'cost_ceiling' => 180000, 'created_by' => $owner->id,
        ]);
        Project::create([
            'company_id' => $company->id, 'code' => $company->nextProjectCode(), 'name' => 'Jeddah Showroom Fit-out', 'name_ar' => 'تجهيز صالة عرض جدة',
            'status' => 'completed', 'client_id' => $client2->id, 'start_date' => now()->subDays(120), 'end_date' => now()->subDays(20),
            'target_revenue' => 90000, 'cost_ceiling' => 65000, 'created_by' => $owner->id,
        ]);
    }
}
