<?php
namespace Tests\Feature;
use App\Models\{Company,Client,Invoice,InvoiceItem,User,Account,AccountMapping};
use App\Services\Zatca\ZatcaXmlGenerator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
class RecheckRemainingGapsTest extends TestCase
{
 use RefreshDatabase;
 private function fixture():array{
  $c=Company::create(['name'=>'Original Seller','slug'=>'recheck-seller','vat_number'=>'300012345600003','currency'=>'SAR']);
  Account::seedSystemAccounts($c->id);AccountMapping::seedDefaults($c->id);
  $u=User::factory()->create(['company_id'=>$c->id,'role'=>'owner','status'=>'active']);
  $b=Client::create(['company_id'=>$c->id,'name'=>'Original Buyer','vat_number'=>'300012345600003']);
  $i=Invoice::create(['company_id'=>$c->id,'client_id'=>$b->id,'invoice_number'=>$c->nextInvoiceNumber(),'status'=>'draft','type'=>'standard','currency'=>'SAR','issue_date'=>now()]);
  InvoiceItem::create(['invoice_id'=>$i->id,'description'=>'Synthetic service','quantity'=>1,'unit_price'=>100,'vat_rate'=>15,'vat_amount'=>15,'line_total'=>115]);
  $i->recalculateTotals();return[$c,$u,$b,$i];
 }
 public function test_discounted_xml_tax_breakdown_equals_document_vat():void{
  [$c,$u,$b,$i]=$this->fixture();$i->discount_type='fixed';$i->discount_value=10;$i->recalculateTotals();
  $xml=app(ZatcaXmlGenerator::class)->generate($i,'0100000',null,'00000000-0000-4000-8000-000000000001');$doc=new \DOMDocument();$doc->loadXML($xml);$xp=new \DOMXPath($doc);
  $nodes=$xp->query('/*[local-name()="Invoice"]/*[local-name()="TaxTotal"]/*[local-name()="TaxSubtotal"]/*[local-name()="TaxAmount"]');$sum=0;foreach($nodes as$n)$sum+=(float)$n->textContent;
  $this->assertEqualsWithDelta((float)$i->vat_total,$sum,0.001,'XML breakdown must equal its document VAT');
 }
 public function test_seller_details_stay_frozen_after_issuance():void{
  [$c,$u,$b,$i]=$this->fixture();$this->actingAs($u)->post(route('app.invoices.send',$i))->assertSessionDoesntHaveErrors();
  $c->update(['name'=>'Changed Seller']);$this->assertSame('Original Seller',$i->fresh()->pdfData()['company']->name);
 }
 public function test_xml_buyer_matches_the_frozen_pdf_buyer():void{
  [$c,$u,$b,$i]=$this->fixture();$this->actingAs($u)->post(route('app.invoices.send',$i))->assertSessionDoesntHaveErrors();
  $b->update(['name'=>'Changed Buyer']);$i=$i->fresh();$this->assertSame('Original Buyer',$i->partyForDocument()->name);
  $xml=app(ZatcaXmlGenerator::class)->generate($i,'0100000',null,'00000000-0000-4000-8000-000000000001');$this->assertStringNotContainsString('Changed Buyer',$xml,'XML should use the same issuance snapshot as the PDF');
 }
 public function test_issued_phase1_invoice_requires_credit_note_instead_of_direct_cancel():void{
  [$c,$u,$b,$i]=$this->fixture();$c->update(['zatca_integration_mode'=>'phase1']);
  $this->actingAs($u)->post(route('app.invoices.send',$i))->assertSessionDoesntHaveErrors();
  $this->post(route('app.invoices.cancel',$i));$this->assertNotSame('cancelled',$i->fresh()->status);
 }
 public function test_checkout_initiation_is_post_only():void{
  $route=app('router')->getRoutes()->getByName('public.invoices.pay');
  $this->assertContains('POST',$route->methods());$this->assertNotContains('GET',$route->methods());
 }
 public function test_existing_yearly_counter_continues_after_migration():void{
  [$c]=$this->fixture();$c->update(['document_numbering_resets_yearly'=>true,'next_invoice_number'=>3]);
  $c->invoice_number_fiscal_year=$c->currentFiscalYearLabel();$c->invoice_number_fiscal_year_baseline=0;$c->save();
  // State of a pre-upgrade yearly company after issuing numbers 1 and 2:
  // the old column means next=3; the migration adds baseline=0.
  $this->assertSame('INV-'.$c->currentFiscalYearLabel().'-00003',$c->nextInvoiceNumber());
 }
 public function test_preupgrade_yearly_company_can_resume_lifetime_without_collision():void{
  [$c,$u,$b,$i]=$this->fixture();
  Invoice::create(['company_id'=>$c->id,'client_id'=>$b->id,'invoice_number'=>'INV-00002','status'=>'sent','issue_date'=>now()]);
  // Old software reset the shared counter after enabling yearly mode.
  $c->next_invoice_number=2;$c->invoice_number_fiscal_year=$c->currentFiscalYearLabel();$c->invoice_number_fiscal_year_baseline=0;$c->document_numbering_resets_yearly=false;$c->save();
  $next=$c->nextInvoiceNumber();$this->assertFalse(Invoice::where('company_id',$c->id)->where('invoice_number',$next)->exists(),'Pre-upgrade counter still selects '.$next);
 }
 public function test_resolved_smtp_transport_does_not_keep_previous_tenant():void{
  [$c]=$this->fixture();$other=Company::create(['name'=>'Other Tenant','slug'=>'other-tenant']);
  config(['mail.mailers.smtp'=>['transport'=>'smtp','host'=>'platform.example.invalid','port'=>587,'username'=>null,'password'=>null],'mail.from.address'=>'platform@example.invalid']);
  \Illuminate\Support\Facades\Mail::purge('smtp');
  \App\Models\CompanyMailConfig::create(['company_id'=>$c->id,'is_enabled'=>true,'smtp_host'=>'tenant-a.example.invalid','smtp_port'=>587,'smtp_encryption'=>'tls','from_address'=>'a@example.invalid']);
  $service=app(\App\Services\CompanyMailService::class);$service->applyFor($c);
  $first=(string)\Illuminate\Support\Facades\Mail::mailer('smtp')->getSymfonyTransport();
  $this->assertStringContainsString('tenant-a.example.invalid',$first);
  $service->applyFor($other);
  $second=(string)\Illuminate\Support\Facades\Mail::mailer('smtp')->getSymfonyTransport();
  $this->assertStringContainsString('platform.example.invalid',$second,'Resolved mail transport must be refreshed, not just its config');
 }
}
