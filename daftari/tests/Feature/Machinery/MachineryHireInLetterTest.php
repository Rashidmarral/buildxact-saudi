<?php

namespace Tests\Feature\Machinery;

use App\Models\Account;
use App\Models\AccountMapping;
use App\Models\Company;
use App\Models\CompanyLetter;
use App\Models\MachineryHireInContract;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\Supplier;
use App\Models\User;
use App\Support\LetterPresets;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Generating a hire-in agreement letter — the 7-clause preset modeled on
 * the real attached "MC1 & RC2 Vehicle/Equipment Agreement" — and the
 * create() form prefilling the SUPPLIER as party B (the reverse of every
 * other letter kind, where the counterparty is normally a client).
 */
class MachineryHireInLetterTest extends TestCase
{
    use RefreshDatabase;

    private function makeCompany(): Company
    {
        $plan = Plan::create([
            'name' => 'Pro', 'slug' => 'pro-'.uniqid(), 'price_monthly' => 100, 'price_yearly' => 1000,
            'is_active' => true, 'has_machinery_equipment' => true,
        ]);
        $company = Company::create(['name' => 'Dynamic Core Contracting', 'slug' => 'dcc-'.uniqid(), 'status' => 'active', 'currency' => 'SAR']);
        Account::seedSystemAccounts($company->id);
        AccountMapping::seedDefaults($company->id);
        Subscription::create([
            'company_id' => $company->id, 'plan_id' => $plan->id, 'status' => 'active',
            'billing_cycle' => 'monthly', 'current_period_start' => now(), 'current_period_end' => now()->addMonth(),
        ]);

        return $company;
    }

    private function makeOwner(Company $company): User
    {
        return User::factory()->create(['role' => 'owner', 'company_id' => $company->id, 'status' => 'active']);
    }

    public function test_the_preset_has_seven_numbered_clauses_plus_intro_and_closing(): void
    {
        $blueprint = LetterPresets::blueprint('machinery_hire_in_agreement');

        $this->assertSame('First Party (Hirer)', $blueprint['party_a_role']);
        $this->assertSame('Second Party (Equipment Supplier)', $blueprint['party_b_role']);
        $headings = collect($blueprint['content'])->where('is_heading', true)->pluck('text_en');
        $this->assertCount(7, $headings);
        $this->assertStringContainsString('Rate and Payment', $headings->last());
    }

    public function test_the_create_form_prefills_the_supplier_as_party_b(): void
    {
        $company = $this->makeCompany();
        $owner = $this->makeOwner($company);
        $contract = MachineryHireInContract::create([
            'company_id' => $company->id, 'contract_number' => $company->nextHireInContractNumber(),
            'supplier_name' => 'Simat Al-Rifah Co.', 'supplier_cr_number' => '7053564584',
            'equipment_description' => 'MC1 spray tanker', 'start_date' => now()->toDateString(),
            'rate' => 0.25, 'rate_type' => 'per_unit', 'rate_unit_label' => 'sq. meter',
            'fuel_responsibility' => 'supplier', 'status' => 'active',
        ]);

        $response = $this->actingAs($owner)->get(route('app.machinery.letters.create', [
            'document_type' => 'machinery_hire_in_agreement', 'machinery_hire_in_contract_id' => $contract->id,
        ]));

        $response->assertOk();
        $response->assertSee('Simat Al-Rifah Co.', false);
        $response->assertSee('7053564584', false);
    }

    public function test_generating_the_letter_links_it_to_the_contract_and_supplier(): void
    {
        $company = $this->makeCompany();
        $owner = $this->makeOwner($company);
        $supplier = Supplier::create(['company_id' => $company->id, 'supplier_code' => 'SUP-001', 'name' => 'Simat Al-Rifah Co.', 'type' => 'company']);
        $contract = MachineryHireInContract::create([
            'company_id' => $company->id, 'contract_number' => $company->nextHireInContractNumber(),
            'supplier_id' => $supplier->id, 'equipment_description' => 'MC1 spray tanker',
            'start_date' => now()->toDateString(), 'rate' => 0.25, 'rate_type' => 'per_unit',
            'rate_unit_label' => 'sq. meter', 'fuel_responsibility' => 'supplier', 'status' => 'active',
        ]);
        $blueprint = LetterPresets::blueprint('machinery_hire_in_agreement');

        $response = $this->actingAs($owner)->post(route('app.machinery.letters.store'), [
            'machinery_hire_in_contract_id' => $contract->id,
            'document_type' => 'machinery_hire_in_agreement',
            'title' => $blueprint['title'], 'title_ar' => $blueprint['title_ar'],
            'letter_date' => now()->toDateString(),
            'party_a_role' => $blueprint['party_a_role'], 'party_b_role' => $blueprint['party_b_role'],
            'party_b_name' => 'Simat Al-Rifah Co.', 'supplier_id' => $supplier->id,
            'language_mode' => 'bilingual',
            'content' => $blueprint['content'],
        ]);

        $letter = CompanyLetter::first();
        $response->assertRedirect(route('app.machinery.letters.show', $letter));
        $this->assertSame($contract->id, $letter->machinery_hire_in_contract_id);
        $this->assertSame($supplier->id, $letter->supplier_id);

        $pdf = $this->actingAs($owner)->get(route('app.machinery.letters.pdf', $letter));
        $pdf->assertOk();
    }
}
