<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Services\Zatca\ZatcaCryptoService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Common Name, Organization Unit Name, EGS Serial Number, and Business
 * Category are ZATCA's own free-text organizational identifiers — none of
 * them are the VAT number, and Organization Unit Name in particular is a
 * branch/group identifier per ZATCA's own CSR samples. They used to be
 * silently computed from the company's VAT number (same hardcoded value
 * for every company, with no way to correct it); they're now real
 * per-company fields (App\Http\Controllers\User\ZatcaController::
 * generateCsr()), and the VAT number itself must still reach the CSR's
 * mandatory UID extension field independently of what Organization Unit
 * is set to.
 */
class ZatcaCsrFieldsTest extends TestCase
{
    use RefreshDatabase;

    private function makeCompany(array $overrides = []): Company
    {
        return Company::create(array_merge([
            'name' => 'CSR Field Test Co.', 'slug' => 'csr-test-'.uniqid(),
            'vat_number' => '399999999900003', 'cr_number' => 'CRN999999',
            'street_name' => 'Test Street', 'city' => 'Riyadh',
            'zatca_sync_b2b' => true, 'zatca_sync_b2c' => true,
        ], $overrides));
    }

    public function test_csr_subject_uses_the_companys_own_custom_csr_fields_when_set(): void
    {
        $company = $this->makeCompany([
            'zatca_common_name' => 'MyCustomCN',
            'zatca_organization_unit_name' => 'dynamic-sa',
            'zatca_business_category' => 'Construction',
            'zatca_egs_serial' => '1-Custom|2-9.9.9|3-abc',
        ]);

        $result = (new ZatcaCryptoService())->generateCsr($company);
        $subject = openssl_csr_get_subject(base64_decode($result['csr']));

        $this->assertSame('MyCustomCN', $subject['CN']);
        $this->assertSame('dynamic-sa', $subject['OU']);
        $this->assertSame('CSR Field Test Co.', $subject['O']);
    }

    public function test_csr_subject_falls_back_to_company_name_not_vat_number_when_unset(): void
    {
        $company = $this->makeCompany();

        $result = (new ZatcaCryptoService())->generateCsr($company);
        $subject = openssl_csr_get_subject(base64_decode($result['csr']));

        $this->assertSame('CSR Field Test Co.', $subject['CN']);
        $this->assertSame('CSR Field Test Co.', $subject['OU']);
        $this->assertNotSame($company->vat_number, $subject['CN']);
        $this->assertNotSame($company->vat_number, $subject['OU']);
    }

    public function test_two_companies_get_independent_csr_fields_not_a_shared_hardcoded_value(): void
    {
        $companyA = $this->makeCompany(['name' => 'Company A', 'slug' => 'company-a-'.uniqid(), 'zatca_organization_unit_name' => 'branch-a']);
        $companyB = $this->makeCompany(['name' => 'Company B', 'slug' => 'company-b-'.uniqid(), 'zatca_organization_unit_name' => 'branch-b']);

        $subjectA = openssl_csr_get_subject(base64_decode((new ZatcaCryptoService())->generateCsr($companyA)['csr']));
        $subjectB = openssl_csr_get_subject(base64_decode((new ZatcaCryptoService())->generateCsr($companyB)['csr']));

        $this->assertSame('branch-a', $subjectA['OU']);
        $this->assertSame('branch-b', $subjectB['OU']);
    }
}
