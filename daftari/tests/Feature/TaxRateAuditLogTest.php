<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Company;
use App\Models\TaxRate;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Audit finding SEC-08: tax-rate configuration — the number deciding how
 * much VAT every invoice/bill/expense charges or recovers — had no audit
 * trail at all. These lock in that every mutation now leaves a record.
 */
class TaxRateAuditLogTest extends TestCase
{
    use RefreshDatabase;

    private function makeOwner(): array
    {
        $company = Company::create(['name' => 'Tax Rate Audit Co.', 'slug' => 'tax-rate-audit-'.uniqid()]);
        $owner = User::factory()->create(['role' => 'owner', 'company_id' => $company->id, 'status' => 'active']);

        return [$company, $owner];
    }

    public function test_creating_a_tax_rate_is_logged(): void
    {
        [$company, $owner] = $this->makeOwner();

        $this->actingAs($owner)->post(route('app.tax-rates.store'), [
            'name' => 'Standard 15%', 'rate' => 15, 'type' => TaxRate::TYPE_STANDARD, 'is_active' => 1,
        ])->assertRedirect();

        $log = AuditLog::forCompany($company->id)->where('action', 'tax_rate.create')->first();
        $this->assertNotNull($log);
        $this->assertStringContainsString('Standard 15%', $log->description);
    }

    public function test_updating_a_tax_rate_logs_the_before_and_after_rate(): void
    {
        [$company, $owner] = $this->makeOwner();
        $taxRate = TaxRate::create(['company_id' => $company->id, 'name' => 'Standard', 'rate' => 15, 'type' => TaxRate::TYPE_STANDARD, 'is_active' => true]);

        $this->actingAs($owner)->put(route('app.tax-rates.update', $taxRate), [
            'name' => 'Standard', 'rate' => 20, 'type' => TaxRate::TYPE_STANDARD, 'is_active' => 1,
        ])->assertRedirect();

        $log = AuditLog::forCompany($company->id)->where('action', 'tax_rate.update')->first();
        $this->assertNotNull($log);
        $this->assertEquals(15, $log->old_value['rate']);
        $this->assertEquals(20, $log->new_value['rate']);
    }

    public function test_deleting_a_tax_rate_is_logged(): void
    {
        [$company, $owner] = $this->makeOwner();
        TaxRate::create(['company_id' => $company->id, 'name' => 'Default', 'rate' => 15, 'type' => TaxRate::TYPE_STANDARD, 'is_active' => true, 'is_default' => true]);
        $extra = TaxRate::create(['company_id' => $company->id, 'name' => 'Extra rate', 'rate' => 5, 'type' => TaxRate::TYPE_STANDARD, 'is_active' => true]);

        $this->actingAs($owner)->delete(route('app.tax-rates.destroy', $extra))->assertRedirect();

        $log = AuditLog::forCompany($company->id)->where('action', 'tax_rate.delete')->first();
        $this->assertNotNull($log);
        $this->assertStringContainsString('Extra rate', $log->description);
    }

    public function test_updating_vat_recovery_settings_is_logged(): void
    {
        [$company, $owner] = $this->makeOwner();

        $this->actingAs($owner)->post(route('app.tax-rates.vat-recovery'), [
            'vat_makes_exempt_supplies' => 1, 'vat_recovery_percentage' => 80,
        ])->assertRedirect();

        $log = AuditLog::forCompany($company->id)->where('action', 'tax_rate.recovery_update')->first();
        $this->assertNotNull($log);
        $this->assertFalse((bool) $log->old_value['vat_makes_exempt_supplies']);
        $this->assertTrue((bool) $log->new_value['vat_makes_exempt_supplies']);
    }
}
