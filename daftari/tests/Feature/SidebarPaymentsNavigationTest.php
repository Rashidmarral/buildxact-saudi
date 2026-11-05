<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\AccountMapping;
use App\Models\Company;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * "can we make seperate sidebar menu to deal with payments with more
 * control and more professional" — Receipt Vouchers and Payment Vouchers
 * already existed as full GL-posting, bank-account-aware, approval-gated
 * documents (ReceiptVoucherController/PaymentVoucherController), but the
 * sidebar never linked to either index — only "Cash & Banks" (bank
 * accounts alone) was listed. Folds both into that same menu.
 */
class SidebarPaymentsNavigationTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_sidebar_links_to_receipt_and_payment_vouchers(): void
    {
        $company = Company::create(['name' => 'Dynamic Core Contracting', 'slug' => 'dcc-'.uniqid(), 'status' => 'active', 'currency' => 'SAR']);
        Account::seedSystemAccounts($company->id);
        AccountMapping::seedDefaults($company->id);
        $owner = User::factory()->create(['role' => 'owner', 'company_id' => $company->id, 'status' => 'active']);

        $response = $this->actingAs($owner)->get(route('app.dashboard'));

        $response->assertOk();
        $response->assertSee(route('app.bank-accounts.index'), false);
        $response->assertSee(route('app.receipt-vouchers.index'), false);
        $response->assertSee(route('app.payment-vouchers.index'), false);
    }
}
