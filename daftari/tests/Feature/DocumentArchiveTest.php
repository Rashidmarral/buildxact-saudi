<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\AccountMapping;
use App\Models\Attachment;
use App\Models\BankAccount;
use App\Models\Company;
use App\Models\Expense;
use App\Models\MachineryAsset;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

/**
 * "make a proper document saving module like, client po, suplier bill,
 * company invoices, expenses bill etc that are required for an audit
 * at the end of year" — the Document Archive is the single page that
 * reads across every document type's attachments at once. These tests
 * cover the filtering, cross-company isolation, CSV export, and the
 * per-type reference label/url resolution (which used to be broken:
 * the view called $this->referenceLabel() on itself, which doesn't
 * exist in a compiled Blade template — now precomputed in the
 * controller before the view is rendered).
 */
class DocumentArchiveTest extends TestCase
{
    use RefreshDatabase;

    private function makeCompany(): Company
    {
        $company = Company::create(['name' => 'Archive Co.', 'slug' => 'archive-co-'.uniqid()]);
        Account::seedSystemAccounts($company->id);
        AccountMapping::seedDefaults($company->id);

        return $company;
    }

    private function makeOwner(Company $company): User
    {
        return User::factory()->create(['role' => 'owner', 'company_id' => $company->id, 'status' => 'active']);
    }

    private function makeExpense(Company $company): Expense
    {
        $bank = BankAccount::create(['company_id' => $company->id, 'name' => 'Petty Cash', 'type' => 'cash', 'currency' => 'SAR', 'is_active' => true]);

        return Expense::create([
            'company_id' => $company->id, 'bank_account_id' => $bank->id,
            'description' => 'Fuel', 'amount' => 100, 'gross_amount' => 100, 'vat_amount' => 0,
            'tax_category' => 'zero_rated', 'status' => 'pending', 'expense_date' => now()->toDateString(),
        ]);
    }

    private function attach(\Illuminate\Database\Eloquent\Model $model, Company $company, User $uploader, string $name = 'doc.pdf'): Attachment
    {
        return $model->attachments()->create([
            'company_id' => $company->id,
            'uploaded_by' => $uploader->id,
            'original_name' => $name,
            'path' => 'fake/path/'.$name,
            'size' => 1024,
            'mime_type' => 'application/pdf',
        ]);
    }

    public function test_the_index_page_lists_attachments_across_document_types(): void
    {
        $company = $this->makeCompany();
        $owner = $this->makeOwner($company);
        $expense = $this->makeExpense($company);
        $machine = MachineryAsset::create(['company_id' => $company->id, 'asset_code' => 'EQ-00001', 'name' => 'Steel Roller', 'status' => 'available']);

        $this->attach($expense, $company, $owner, 'fuel-receipt.jpg');
        $this->attach($machine, $company, $owner, 'istimara.pdf');

        $response = $this->actingAs($owner)->get(route('app.documents.index'));

        $response->assertOk()
            ->assertSee('fuel-receipt.jpg')
            ->assertSee('istimara.pdf')
            ->assertSee('Fuel')
            ->assertSee('EQ-00001');
    }

    public function test_it_can_filter_by_document_type(): void
    {
        $company = $this->makeCompany();
        $owner = $this->makeOwner($company);
        $expense = $this->makeExpense($company);
        $machine = MachineryAsset::create(['company_id' => $company->id, 'asset_code' => 'EQ-00002', 'name' => 'Bobcat', 'status' => 'available']);

        $this->attach($expense, $company, $owner, 'fuel-receipt.jpg');
        $this->attach($machine, $company, $owner, 'istimara.pdf');

        $response = $this->actingAs($owner)->get(route('app.documents.index', ['type' => 'expense']));

        $response->assertOk()->assertSee('fuel-receipt.jpg')->assertDontSee('istimara.pdf');
    }

    public function test_it_can_filter_by_file_name_search(): void
    {
        $company = $this->makeCompany();
        $owner = $this->makeOwner($company);
        $expense = $this->makeExpense($company);

        $this->attach($expense, $company, $owner, 'fuel-receipt.jpg');
        $this->attach($expense, $company, $owner, 'invoice-copy.pdf');

        $response = $this->actingAs($owner)->get(route('app.documents.index', ['search' => 'fuel']));

        $response->assertOk()->assertSee('fuel-receipt.jpg')->assertDontSee('invoice-copy.pdf');
    }

    public function test_a_company_only_sees_its_own_documents(): void
    {
        $companyA = $this->makeCompany();
        $companyB = $this->makeCompany();
        $ownerA = $this->makeOwner($companyA);
        $ownerB = $this->makeOwner($companyB);
        $expenseB = $this->makeExpense($companyB);

        $this->attach($expenseB, $companyB, $ownerB, 'other-companys-file.pdf');

        $response = $this->actingAs($ownerA)->get(route('app.documents.index'));

        $response->assertOk()->assertDontSee('other-companys-file.pdf');
    }

    public function test_the_reference_link_points_to_the_expenses_edit_page(): void
    {
        $company = $this->makeCompany();
        $owner = $this->makeOwner($company);
        $expense = $this->makeExpense($company);
        $this->attach($expense, $company, $owner, 'fuel-receipt.jpg');

        $response = $this->actingAs($owner)->get(route('app.documents.index'));

        $response->assertOk()->assertSee(route('app.expenses.edit', $expense), false);
    }

    public function test_a_document_whose_source_record_was_deleted_still_lists_without_error(): void
    {
        $company = $this->makeCompany();
        $owner = $this->makeOwner($company);
        $expense = $this->makeExpense($company);
        $attachment = $this->attach($expense, $company, $owner, 'orphan.pdf');
        $expense->forceDelete();

        $response = $this->actingAs($owner)->get(route('app.documents.index'));

        $response->assertOk()->assertSee('orphan.pdf')->assertSee('(source deleted)');
        $this->assertDatabaseHas('attachments', ['id' => $attachment->id]);
    }

    public function test_csv_export_includes_the_reference_and_file_name(): void
    {
        $company = $this->makeCompany();
        $owner = $this->makeOwner($company);
        $expense = $this->makeExpense($company);
        $this->attach($expense, $company, $owner, 'fuel-receipt.jpg');

        $response = $this->actingAs($owner)->get(route('app.documents.index', ['export' => 'csv']));

        $response->assertOk();
        $this->assertStringContainsString('fuel-receipt.jpg', $response->streamedContent());
        $this->assertStringContainsString('Fuel', $response->streamedContent());
    }
}
