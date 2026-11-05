<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\AccountMapping;
use App\Models\Bill;
use App\Models\BillPayment;
use App\Models\Client;
use App\Models\Company;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\InvoicePayment;
use App\Models\Item;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The dashboard was rebuilt from stat-cards-and-a-table into a real
 * chart-driven layout (Sales & Purchases, Payment Sent & Received, Top
 * Selling Products, Top Customers, Sales by Payment Method) — this
 * exercises DashboardController's new chart-data aggregation directly
 * against the view data Chart.js consumes, rather than trying to read
 * numbers back out of a <canvas> element.
 */
class DashboardChartsTest extends TestCase
{
    use RefreshDatabase;

    private function makeOwner(): array
    {
        $company = Company::create(['name' => 'Chart Co.', 'slug' => 'chart-'.uniqid(), 'status' => 'active', 'currency' => 'SAR']);
        Account::seedSystemAccounts($company->id);
        AccountMapping::seedDefaults($company->id);
        $owner = User::factory()->create(['role' => 'owner', 'company_id' => $company->id, 'status' => 'active']);

        return [$company, $owner];
    }

    public function test_the_dashboard_aggregates_sales_purchases_and_top_lists_correctly(): void
    {
        [$company, $owner] = $this->makeOwner();

        $clientA = Client::create(['company_id' => $company->id, 'name' => 'Big Spender LLC']);
        $clientB = Client::create(['company_id' => $company->id, 'name' => 'Small Buyer Co']);
        $item = Item::create(['company_id' => $company->id, 'name' => 'Widget', 'unit_price' => 100, 'vat_rate' => 15, 'is_active' => true]);
        $supplier = Supplier::create(['company_id' => $company->id, 'name' => 'Acme Supplies']);

        $invoiceA = Invoice::create([
            'company_id' => $company->id, 'client_id' => $clientA->id, 'invoice_number' => 'INV-A',
            'status' => 'paid', 'issue_date' => now(), 'due_date' => now()->addDays(30),
            'subtotal' => 500, 'vat_total' => 75, 'total' => 575, 'amount_paid' => 575, 'currency' => 'SAR',
        ]);
        InvoiceItem::create(['invoice_id' => $invoiceA->id, 'item_id' => $item->id, 'description' => 'Widget', 'quantity' => 5, 'unit_price' => 100, 'vat_rate' => 15, 'vat_amount' => 75, 'line_total' => 500]);

        $invoiceB = Invoice::create([
            'company_id' => $company->id, 'client_id' => $clientB->id, 'invoice_number' => 'INV-B',
            'status' => 'paid', 'issue_date' => now(), 'due_date' => now()->addDays(30),
            'subtotal' => 100, 'vat_total' => 15, 'total' => 115, 'amount_paid' => 115, 'currency' => 'SAR',
        ]);
        InvoiceItem::create(['invoice_id' => $invoiceB->id, 'item_id' => $item->id, 'description' => 'Widget', 'quantity' => 1, 'unit_price' => 100, 'vat_rate' => 15, 'vat_amount' => 15, 'line_total' => 100]);

        InvoicePayment::create(['invoice_id' => $invoiceA->id, 'amount' => 575, 'paid_at' => now(), 'method' => 'cash']);
        InvoicePayment::create(['invoice_id' => $invoiceB->id, 'amount' => 115, 'paid_at' => now(), 'method' => 'bank_transfer']);

        $bill = Bill::create([
            'company_id' => $company->id, 'supplier_id' => $supplier->id, 'bill_number' => 'BILL-1',
            'status' => 'paid', 'bill_date' => now(), 'due_date' => now()->addDays(30),
            'subtotal' => 200, 'vat_total' => 30, 'total' => 230, 'amount_paid' => 230, 'currency' => 'SAR',
        ]);
        BillPayment::create(['bill_id' => $bill->id, 'amount' => 230, 'paid_at' => now()]);

        $response = $this->actingAs($owner)->get(route('app.dashboard'));
        $response->assertOk();

        $data = $response->original->getData();
        $charts = $data['charts'];

        // Sales & Purchases: today's bucket should hold both invoices'
        // totals and the bill's total.
        $todayIndex = array_search(now()->translatedFormat('M j'), $charts['salesPurchases']['labels']);
        $this->assertNotFalse($todayIndex);
        $this->assertEqualsWithDelta(575 + 115, $charts['salesPurchases']['sales'][$todayIndex], 0.01);
        $this->assertEqualsWithDelta(230, $charts['salesPurchases']['purchases'][$todayIndex], 0.01);

        // Payment flow: received = both invoice payments, sent = the bill payment.
        $this->assertEqualsWithDelta(575 + 115, $charts['paymentFlow']['received'][$todayIndex], 0.01);
        $this->assertEqualsWithDelta(230, $charts['paymentFlow']['sent'][$todayIndex], 0.01);

        // Top items: the one shared item across both invoices sums to 600.
        $this->assertCount(1, $charts['topItems']);
        $this->assertSame('Widget', $charts['topItems'][0]['label']);
        $this->assertEqualsWithDelta(600, $charts['topItems'][0]['total'], 0.01);

        // Top customers: the bigger spender should rank first.
        $this->assertSame('Big Spender LLC', $charts['topCustomers'][0]['label']);
        $this->assertEqualsWithDelta(575, $charts['topCustomers'][0]['total'], 0.01);
        $this->assertSame('Small Buyer Co', $charts['topCustomers'][1]['label']);

        // Payment methods: cash and bank transfer both recorded.
        $this->assertEqualsWithDelta(575, $charts['paymentMethods']['cash'], 0.01);
        $this->assertEqualsWithDelta(115, $charts['paymentMethods']['bank_transfer'], 0.01);

        // KPIs pick up the same activity.
        $this->assertEqualsWithDelta(575 + 115, $data['stats']['total_paid_this_month'], 0.01);
        $this->assertEqualsWithDelta(230, $data['stats']['total_purchases_this_month'], 0.01);

        $response->assertSee(__('Sales & Purchases'));
        $response->assertSee(__('Top Selling Products'));
        $response->assertSee(__('Payment Sent & Received'));
        $response->assertSee(__('Top Customers'));
        $response->assertSee(__('Sales by Payment Method'));
    }

    public function test_a_brand_new_company_shows_empty_states_without_errors(): void
    {
        [, $owner] = $this->makeOwner();

        $this->actingAs($owner)->get(route('app.dashboard'))
            ->assertOk()
            ->assertSee(__('No sales yet.'))
            ->assertSee(__('No payments recorded this week.'));
    }

    public function test_the_dashboard_renders_in_arabic(): void
    {
        [, $owner] = $this->makeOwner();
        $this->actingAs($owner)->get(route('locale.switch', 'ar'));

        $this->actingAs($owner)->get(route('app.dashboard'))
            ->assertOk()
            ->assertSee(__('Sales & Purchases'))
            ->assertSee(__('Receivables Aging'));
    }
}
