<?php

namespace Tests\Feature\Machinery;

use App\Models\Company;
use App\Models\CompanyLetter;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * "Can you please add some agreement letters, contracts, approval,
 * rental out rental in... I need to check pdf view once download the
 * letter" — a console command that seeds one real, downloadable sample
 * per Letters & Agreements preset against the user's own company, so
 * they can check the PDF formatting without hand-typing demo content.
 */
class SeedMachineryDemoLettersTest extends TestCase
{
    use RefreshDatabase;

    private function makeCompany(string $name = 'Dynamic Core Contracting Company'): Company
    {
        $plan = Plan::create([
            'name' => 'Pro', 'slug' => 'pro-'.uniqid(), 'price_monthly' => 100, 'price_yearly' => 1000,
            'is_active' => true, 'has_machinery_equipment' => true,
        ]);
        $company = Company::create(['name' => $name, 'slug' => 'dcc-'.uniqid(), 'status' => 'active', 'currency' => 'SAR']);
        Subscription::create([
            'company_id' => $company->id, 'plan_id' => $plan->id, 'status' => 'active',
            'billing_cycle' => 'monthly', 'current_period_start' => now(), 'current_period_end' => now()->addMonth(),
        ]);

        return $company;
    }

    public function test_it_creates_one_letter_per_preset_kind(): void
    {
        $company = $this->makeCompany();

        $this->artisan('demo:machinery-letters', ['--company' => 'Dynamic Core'])
            ->assertSuccessful();

        $letters = CompanyLetter::where('company_id', $company->id)->get();
        $this->assertCount(5, $letters);
        $this->assertSame(
            ['machinery_sale_agreement', 'machinery_purchase_agreement', 'machinery_rental_agreement', 'machinery_hire_in_agreement', 'work_handover_letter'],
            $letters->pluck('document_type')->all()
        );
        $this->assertTrue($letters->every(fn ($l) => str_starts_with($l->title, '(Demo) ')));
    }

    public function test_the_hire_in_letter_mirrors_the_real_supplier_terms(): void
    {
        $company = $this->makeCompany();

        $this->artisan('demo:machinery-letters', ['--company' => 'Dynamic Core'])->assertSuccessful();

        $letter = CompanyLetter::where('company_id', $company->id)->where('document_type', 'machinery_hire_in_agreement')->first();
        $this->assertSame('Simat Al-Rifah Co.', $letter->party_b_name);
        $this->assertSame('First Party (Hirer)', $letter->party_a_role);
        $this->assertSame('Second Party (Equipment Supplier)', $letter->party_b_role);
        $this->assertGreaterThanOrEqual(7, collect($letter->content)->where('is_heading', true)->count());
    }

    public function test_every_seeded_letter_downloads_as_a_pdf(): void
    {
        $company = $this->makeCompany();
        $owner = User::factory()->create(['role' => 'owner', 'company_id' => $company->id, 'status' => 'active']);

        $this->artisan('demo:machinery-letters', ['--company' => 'Dynamic Core'])->assertSuccessful();

        foreach (CompanyLetter::where('company_id', $company->id)->get() as $letter) {
            $response = $this->actingAs($owner)->get(route('app.machinery.letters.pdf', $letter));
            $response->assertOk();
            $response->assertHeader('Content-Type', 'application/pdf');
        }
    }

    public function test_fresh_removes_and_reseeds_without_duplicating(): void
    {
        $company = $this->makeCompany();

        $this->artisan('demo:machinery-letters', ['--company' => 'Dynamic Core'])->assertSuccessful();
        $this->artisan('demo:machinery-letters', ['--company' => 'Dynamic Core', '--fresh' => true])->assertSuccessful();

        $this->assertCount(5, CompanyLetter::where('company_id', $company->id)->get());
    }

    public function test_fresh_only_deletes_this_commands_own_letters(): void
    {
        $company = $this->makeCompany();
        $realLetter = CompanyLetter::create([
            'company_id' => $company->id, 'reference_number' => $company->nextLetterNumber(),
            'document_type' => 'custom', 'title' => 'A Real Letter', 'letter_date' => now(),
            'party_a_role' => 'Company', 'party_b_role' => 'Client', 'party_b_name' => 'Real Client',
            'language_mode' => 'bilingual', 'content' => [['text_en' => 'Hello', 'text_ar' => 'مرحبا', 'is_heading' => false]],
        ]);

        $this->artisan('demo:machinery-letters', ['--company' => 'Dynamic Core'])->assertSuccessful();
        $this->artisan('demo:machinery-letters', ['--company' => 'Dynamic Core', '--fresh' => true])->assertSuccessful();

        $this->assertNotNull($realLetter->fresh());
        $this->assertCount(6, CompanyLetter::where('company_id', $company->id)->get());
    }

    public function test_no_matching_company_fails_cleanly(): void
    {
        $this->artisan('demo:machinery-letters', ['--company' => 'Nonexistent Co.'])
            ->assertFailed();
    }

    public function test_an_ambiguous_company_match_fails_cleanly(): void
    {
        $this->makeCompany('Dynamic Core Contracting Company');
        $this->makeCompany('Dynamic Core Logistics Company');

        $this->artisan('demo:machinery-letters', ['--company' => 'Dynamic Core'])
            ->assertFailed();
    }
}
