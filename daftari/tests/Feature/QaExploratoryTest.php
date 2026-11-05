<?php
namespace Tests\Feature;

use App\Models\{Account,AccountMapping,Client,Company,Invoice,InvoiceItem,Plan,Subscription,User,CompanyMailConfig};
use App\Services\CompanyMailService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class QaExploratoryTest extends TestCase
{
    use RefreshDatabase;
    private function fixture(array $attrs=[]): array
    {
        $company=Company::create(array_merge(['name'=>'QA Synthetic Company','slug'=>'qa-'.uniqid(),'currency'=>'SAR'],$attrs));
        Account::seedSystemAccounts($company->id);
        AccountMapping::seedDefaults($company->id);
        $user=User::factory()->create(['company_id'=>$company->id,'role'=>'owner','status'=>'active']);
        $client=Client::create(['company_id'=>$company->id,'name'=>'QA Original Customer','email'=>'qa@example.invalid']);
        $invoice=Invoice::create(['company_id'=>$company->id,'client_id'=>$client->id,'invoice_number'=>$company->nextInvoiceNumber(),'status'=>'draft','type'=>'standard','issue_date'=>now(),'currency'=>'SAR','subtotal'=>100,'vat_total'=>15,'total'=>115]);
        InvoiceItem::create(['invoice_id'=>$invoice->id,'description'=>'QA Service','quantity'=>1,'unit_price'=>100,'vat_rate'=>15,'vat_amount'=>15,'line_total'=>115]);
        return [$company,$user,$client,$invoice];
    }
    public function test_payment_cannot_bypass_required_invoice_approval(): void
    {
        [$company,$user,$client,$invoice]=$this->fixture(['invoice_approval_threshold'=>50]);
        $this->actingAs($user)->post(route('app.invoices.payments.store',$invoice),['amount'=>10,'paid_at'=>now()->toDateString(),'method'=>'cash']);
        $this->assertNotContains($invoice->fresh()->status,['sent','paid','partially_paid'],'Payment bypassed required invoice approval');
    }
    public function test_cancelled_invoice_cannot_be_reactivated_by_payment(): void
    {
        [$company,$user,$client,$invoice]=$this->fixture();
        $invoice->update(['status'=>'cancelled']);
        $this->actingAs($user)->post(route('app.invoices.payments.store',$invoice),['amount'=>10,'paid_at'=>now()->toDateString(),'method'=>'cash']);
        $this->assertSame('cancelled',$invoice->fresh()->status);
    }
    public function test_public_standard_invoice_requires_production_clearance(): void
    {
        [$company,$user,$client,$invoice]=$this->fixture(['zatca_integration_mode'=>Company::ZATCA_MODE_PHASE2,'zatca_environment'=>'production','zatca_onboarding_status'=>'onboarded','zatca_production_csid'=>'synthetic','zatca_production_secret'=>'synthetic','zatca_sync_b2b'=>true]);
        $plan=Plan::create(['name'=>'QA Pro','slug'=>'qa-pro','price_monthly'=>100,'price_yearly'=>1000,'is_active'=>true,'has_zatca_phase2'=>true]);
        Subscription::create(['company_id'=>$company->id,'plan_id'=>$plan->id,'status'=>'active','billing_cycle'=>'monthly','current_period_start'=>now(),'current_period_end'=>now()->addMonth()]);
        $invoice->update(['status'=>'sent']);
        $this->assertTrue($company->fresh()->isZatcaOnboarded());
        $response=$this->get(route('public.invoices.show',[$invoice->id,$invoice->public_token]));
        $this->assertNotSame(200,$response->getStatusCode(),'Uncleared production B2B invoice reached public customer view');
    }
    public function test_issued_invoice_customer_snapshot_survives_customer_edit(): void
    {
        [$company,$user,$client,$invoice]=$this->fixture();
        $this->actingAs($user)->post(route('app.invoices.send',$invoice))->assertSessionDoesntHaveErrors();
        $client->update(['name'=>'QA Changed Customer']);
        $this->assertSame('QA Original Customer',$invoice->fresh()->pdfData()['doc']['party']->name);
    }
    public function test_unconditional_discount_reduces_standard_vat_taxable_base(): void
    {
        [$company,$user,$client,$invoice]=$this->fixture();
        $invoice->discount_type='fixed'; $invoice->discount_value=10;
        $invoice->recalculateTotals();
        $this->assertEqualsWithDelta(13.50,(float)$invoice->vat_total,0.001,'SAR 100 less SAR 10 discount has SAR 90 taxable base at 15%');
        $this->assertEqualsWithDelta(103.50,(float)$invoice->total,0.001);
    }
    public function test_company_without_smtp_does_not_inherit_previous_company_smtp(): void
    {
        [$company]=$this->fixture();
        $other=Company::create(['name'=>'QA Other Company','slug'=>'qa-other']);
        config(['mail.mailers.smtp.host'=>'platform.example.invalid']);
        CompanyMailConfig::create(['company_id'=>$company->id,'is_enabled'=>true,'smtp_host'=>'tenant-a.example.invalid','smtp_port'=>587,'smtp_encryption'=>'tls','from_address'=>'a@example.invalid']);
        $service=app(CompanyMailService::class);
        $this->assertTrue($service->applyFor($company));
        $this->assertFalse($service->applyFor($other));
        $this->assertSame('platform.example.invalid',config('mail.mailers.smtp.host'));
    }
    public function test_disabling_yearly_numbering_does_not_reuse_an_existing_number(): void
    {
        [$company,$user,$client,$invoice]=$this->fixture();
        $second=Invoice::create(['company_id'=>$company->id,'client_id'=>$client->id,'invoice_number'=>$company->nextInvoiceNumber(),'issue_date'=>now()]);
        $company->update(['document_numbering_resets_yearly'=>true]);
        $company->nextInvoiceNumber();
        $company->update(['document_numbering_resets_yearly'=>false]);
        $next=$company->nextInvoiceNumber();
        $this->assertFalse(Invoice::where('company_id',$company->id)->where('invoice_number',$next)->exists(),'Numbering toggle reused '.$next);
    }
    public function test_fresh_demo_install_has_an_active_subscription(): void
    {
        $this->seed(\Database\Seeders\DatabaseSeeder::class);
        $company=Company::where('slug','al-rashid-trading')->firstOrFail();
        $this->assertTrue($company->subscriptions()->where('status','active')->exists(),'DemoSeeder expects professional plan, absent from PlanSeeder');
    }
}
