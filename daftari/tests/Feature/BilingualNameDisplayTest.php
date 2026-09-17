<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\AccountMapping;
use App\Models\Client;
use App\Models\Company;
use App\Models\Item;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Feedback: "when i select ar language and open products or clients or
 * supplier still there english name shows instead of ar name" — name_ar
 * was captured on every Client/Item/Supplier but never actually read by
 * the list pages, which always rendered the raw `name` column regardless
 * of the active interface language. Client/Item/Supplier now expose a
 * display_name accessor (App\Models\Concerns\HasBilingualName) that the
 * index pages (and every dropdown that lists them elsewhere in the app)
 * render instead, so the switch is real: Arabic locale + a name_ar on the
 * record means the Arabic name is what's shown.
 */
class BilingualNameDisplayTest extends TestCase
{
    use RefreshDatabase;

    private function makeCompany(): Company
    {
        $company = Company::create(['name' => 'Bilingual Co.', 'slug' => 'bilingual-'.uniqid()]);
        Account::seedSystemAccounts($company->id);
        AccountMapping::seedDefaults($company->id);

        return $company;
    }

    private function makeOwner(Company $company): User
    {
        return User::factory()->create(['role' => 'owner', 'company_id' => $company->id, 'status' => 'active']);
    }

    public function test_clients_index_shows_the_arabic_name_when_arabic_locale_is_active(): void
    {
        $company = $this->makeCompany();
        $owner = $this->makeOwner($company);
        Client::create(['company_id' => $company->id, 'name' => 'Modern Technology Company', 'name_ar' => 'الشركة الحديثة للتكنولوجيا']);

        $response = $this->actingAs($owner)->withSession(['locale' => 'ar'])->get(route('app.clients.index'));

        $response->assertOk();
        $response->assertSee('الشركة الحديثة للتكنولوجيا');
        $response->assertDontSee('Modern Technology Company');
    }

    public function test_clients_index_still_shows_the_english_name_when_english_locale_is_active(): void
    {
        $company = $this->makeCompany();
        $owner = $this->makeOwner($company);
        Client::create(['company_id' => $company->id, 'name' => 'Modern Technology Company', 'name_ar' => 'الشركة الحديثة للتكنولوجيا']);

        $response = $this->actingAs($owner)->withSession(['locale' => 'en'])->get(route('app.clients.index'));

        $response->assertOk();
        $response->assertSee('Modern Technology Company');
    }

    public function test_a_client_without_an_arabic_name_still_falls_back_to_english_under_arabic_locale(): void
    {
        $company = $this->makeCompany();
        $owner = $this->makeOwner($company);
        Client::create(['company_id' => $company->id, 'name' => 'English Only Client']);

        $response = $this->actingAs($owner)->withSession(['locale' => 'ar'])->get(route('app.clients.index'));

        $response->assertOk();
        $response->assertSee('English Only Client');
    }

    public function test_items_index_shows_the_arabic_name_when_arabic_locale_is_active(): void
    {
        $company = $this->makeCompany();
        $owner = $this->makeOwner($company);
        Item::create(['company_id' => $company->id, 'name' => 'Cement Bag', 'name_ar' => 'كيس أسمنت', 'unit_price' => 20]);

        $response = $this->actingAs($owner)->withSession(['locale' => 'ar'])->get(route('app.items.index'));

        $response->assertOk();
        $response->assertSee('كيس أسمنت');
        $response->assertDontSee('Cement Bag');
    }

    public function test_suppliers_index_shows_the_arabic_name_when_arabic_locale_is_active(): void
    {
        $company = $this->makeCompany();
        $owner = $this->makeOwner($company);
        Supplier::create(['company_id' => $company->id, 'name' => 'Al-Kharif Supplies', 'name_ar' => 'مؤسسة الخريف']);

        $response = $this->actingAs($owner)->withSession(['locale' => 'ar'])->get(route('app.suppliers.index'));

        $response->assertOk();
        $response->assertSee('مؤسسة الخريف');
        $response->assertDontSee('Al-Kharif Supplies');
    }
}
