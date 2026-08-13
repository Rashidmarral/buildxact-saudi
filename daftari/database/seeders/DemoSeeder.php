<?php

namespace Database\Seeders;

use App\Models\Client;
use App\Models\Company;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Item;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

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
                'city' => 'Riyadh',
                'phone' => '+966500000000',
                'email' => 'owner@daftari.local',
                'currency' => 'SAR',
                'status' => 'active',
                'trial_ends_at' => now()->addDays(config('daftari.trial_days')),
            ]
        );

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

        $client = Client::updateOrCreate(
            ['company_id' => $company->id, 'name' => 'Najd Construction Supplies'],
            [
                'name_ar' => 'مؤسسة نجد لتوريدات البناء',
                'vat_number' => '300098765400003',
                'email' => 'accounts@najd-supplies.example',
                'phone' => '+966511111111',
                'city' => 'Riyadh',
            ]
        );

        $item = Item::updateOrCreate(
            ['company_id' => $company->id, 'name' => 'Consulting services'],
            ['name_ar' => 'خدمات استشارية', 'unit' => 'hour', 'unit_price' => 250, 'vat_rate' => 15, 'is_active' => true]
        );

        if (! $company->invoices()->exists()) {
            $invoice = Invoice::create([
                'company_id' => $company->id,
                'client_id' => $client->id,
                'created_by' => $owner->id,
                'invoice_number' => $company->nextInvoiceNumber(),
                'type' => 'standard',
                'status' => 'sent',
                'issue_date' => now()->subDays(5),
                'due_date' => now()->addDays(25),
                'currency' => 'SAR',
            ]);

            $line = new InvoiceItem([
                'invoice_id' => $invoice->id,
                'item_id' => $item->id,
                'description' => $item->name,
                'quantity' => 20,
                'unit_price' => $item->unit_price,
                'vat_rate' => $item->vat_rate,
                'sort_order' => 0,
            ]);
            $line->recalculate();
            $line->save();

            $invoice->recalculateTotals();

            $invoice->invoicePayments()->create([
                'amount' => 2000,
                'paid_at' => now()->subDays(2),
                'method' => 'bank_transfer',
                'reference' => 'TRX-1029',
            ]);
            $invoice->amount_paid = $invoice->invoicePayments()->sum('amount');
            $invoice->status = $invoice->isFullyPaid() ? 'paid' : 'partially_paid';
            $invoice->save();
        }

        $category = ExpenseCategory::updateOrCreate(
            ['company_id' => $company->id, 'name' => 'Office supplies'],
            ['name_ar' => 'مستلزمات مكتبية']
        );

        if (! $company->expenses()->exists()) {
            Expense::create([
                'company_id' => $company->id,
                'expense_category_id' => $category->id,
                'created_by' => $owner->id,
                'vendor_name' => 'Riyadh Office Supplies',
                'description' => 'Printer paper and toner',
                'amount' => 450,
                'vat_amount' => 58.70,
                'expense_date' => now()->subDays(7),
            ]);
        }
    }
}
