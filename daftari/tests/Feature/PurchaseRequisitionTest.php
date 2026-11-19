<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Project;
use App\Models\PurchaseRequisition;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/**
 * "Hamly has a Purchase Requisition step before the Purchase Order: a
 * site foreman flags 'need 50 bags cement by Tuesday' with no price or
 * supplier yet, then procurement turns it into a priced PO." Covers the
 * full requested -> approved/rejected -> converted lifecycle, and that
 * an approved requisition's items actually prefill the new PO.
 */
class PurchaseRequisitionTest extends TestCase
{
    use RefreshDatabase;

    private function makeCompany(): Company
    {
        return Company::create(['name' => 'Dynamic Core Contracting', 'slug' => 'dcc-'.uniqid()]);
    }

    private function makeOwner(Company $company): User
    {
        return User::factory()->create(['role' => 'owner', 'company_id' => $company->id, 'status' => 'active']);
    }

    public function test_a_requisition_can_be_submitted_with_line_items(): void
    {
        $company = $this->makeCompany();
        $owner = $this->makeOwner($company);

        $response = $this->actingAs($owner)->post(route('app.purchase-requisitions.store'), [
            'needed_by_date' => now()->addWeek()->toDateString(),
            'notes' => 'For the site road works',
            'items' => [
                ['description' => 'Cement bags (50kg)', 'quantity' => 50],
            ],
        ]);

        $response->assertRedirect(route('app.purchase-requisitions.index'));
        $this->assertDatabaseHas('purchase_requisitions', [
            'company_id' => $company->id, 'requested_by' => $owner->id, 'status' => 'requested',
        ]);
        $requisition = PurchaseRequisition::first();
        $this->assertSame('PRQ-00001', $requisition->requisition_number);
        $this->assertDatabaseHas('purchase_requisition_items', [
            'purchase_requisition_id' => $requisition->id, 'description' => 'Cement bags (50kg)', 'quantity' => 50,
        ]);
    }

    public function test_approving_a_requisition_notifies_the_requester(): void
    {
        Notification::fake();
        $company = $this->makeCompany();
        $owner = $this->makeOwner($company);
        $requester = User::factory()->create(['role' => 'staff', 'company_id' => $company->id, 'status' => 'active']);
        $requisition = PurchaseRequisition::create([
            'company_id' => $company->id, 'requested_by' => $requester->id,
            'requisition_number' => 'PRQ-00001', 'status' => 'requested',
        ]);

        $response = $this->actingAs($owner)->post(route('app.purchase-requisitions.approve', $requisition));

        $response->assertSessionDoesntHaveErrors();
        $this->assertSame('approved', $requisition->fresh()->status);
        $this->assertSame($owner->id, $requisition->fresh()->reviewed_by);
        Notification::assertSentTo($requester, \App\Notifications\GenericNotification::class);
    }

    public function test_rejecting_a_requisition_requires_a_reason_and_notifies_the_requester(): void
    {
        Notification::fake();
        $company = $this->makeCompany();
        $owner = $this->makeOwner($company);
        $requester = User::factory()->create(['role' => 'staff', 'company_id' => $company->id, 'status' => 'active']);
        $requisition = PurchaseRequisition::create([
            'company_id' => $company->id, 'requested_by' => $requester->id,
            'requisition_number' => 'PRQ-00001', 'status' => 'requested',
        ]);

        $this->actingAs($owner)->post(route('app.purchase-requisitions.reject', $requisition))
            ->assertSessionHasErrors('rejection_reason');

        $response = $this->actingAs($owner)->post(route('app.purchase-requisitions.reject', $requisition), [
            'rejection_reason' => 'Already have enough cement in stock',
        ]);

        $response->assertSessionDoesntHaveErrors();
        $this->assertSame('rejected', $requisition->fresh()->status);
        Notification::assertSentTo($requester, \App\Notifications\GenericNotification::class);
    }

    public function test_an_approved_requisitions_items_prefill_a_new_purchase_order(): void
    {
        $company = $this->makeCompany();
        $owner = $this->makeOwner($company);
        $project = Project::create(['company_id' => $company->id, 'code' => 'PRJ-1', 'name' => 'Riyadh Site']);
        $requisition = PurchaseRequisition::create([
            'company_id' => $company->id, 'project_id' => $project->id, 'requested_by' => $owner->id,
            'requisition_number' => 'PRQ-00001', 'status' => 'approved',
        ]);
        $requisition->items()->create(['description' => 'Cement bags (50kg)', 'quantity' => 50, 'sort_order' => 0]);

        $response = $this->actingAs($owner)->get(route('app.purchase-requisitions.create-po', $requisition));
        $response->assertRedirect(route('app.purchase-orders.create', ['from_requisition' => $requisition->id]));

        $createResponse = $this->actingAs($owner)->get(route('app.purchase-orders.create', ['from_requisition' => $requisition->id]));
        $createResponse->assertOk()->assertSee('Cement bags (50kg)')->assertSee('PRQ-00001');
    }

    public function test_submitting_a_purchase_order_from_a_requisition_marks_it_converted(): void
    {
        $company = $this->makeCompany();
        $owner = $this->makeOwner($company);
        $supplier = \App\Models\Supplier::create(['company_id' => $company->id, 'name' => 'Saed Est.']);
        $requisition = PurchaseRequisition::create([
            'company_id' => $company->id, 'requested_by' => $owner->id,
            'requisition_number' => 'PRQ-00001', 'status' => 'approved',
        ]);

        $response = $this->actingAs($owner)->post(route('app.purchase-orders.store'), [
            'supplier_id' => $supplier->id,
            'order_date' => now()->toDateString(),
            'from_requisition_id' => $requisition->id,
            'items' => [
                ['description' => 'Cement bags (50kg)', 'quantity' => 50, 'unit_price' => 20, 'vat_rate' => 15],
            ],
        ]);

        $response->assertRedirect();
        $requisition->refresh();
        $this->assertSame('converted', $requisition->status);
        $this->assertNotNull($requisition->purchase_order_id);
    }

    public function test_a_company_cannot_approve_another_companys_requisition(): void
    {
        $companyA = $this->makeCompany();
        $companyB = $this->makeCompany();
        $ownerA = $this->makeOwner($companyA);
        $requesterB = $this->makeOwner($companyB);
        $requisitionB = PurchaseRequisition::create([
            'company_id' => $companyB->id, 'requested_by' => $requesterB->id,
            'requisition_number' => 'PRQ-00001', 'status' => 'requested',
        ]);

        $this->actingAs($ownerA)->post(route('app.purchase-requisitions.approve', $requisitionB))
            ->assertNotFound();
    }

    public function test_a_requisition_can_only_be_deleted_while_still_requested(): void
    {
        $company = $this->makeCompany();
        $owner = $this->makeOwner($company);
        $requisition = PurchaseRequisition::create([
            'company_id' => $company->id, 'requested_by' => $owner->id,
            'requisition_number' => 'PRQ-00001', 'status' => 'approved',
        ]);

        $this->actingAs($owner)->delete(route('app.purchase-requisitions.destroy', $requisition))
            ->assertNotFound();

        $requisition->update(['status' => 'requested']);
        $this->actingAs($owner)->delete(route('app.purchase-requisitions.destroy', $requisition))
            ->assertRedirect(route('app.purchase-requisitions.index'));
        $this->assertDatabaseMissing('purchase_requisitions', ['id' => $requisition->id]);
    }
}
