<?php

use App\Models\Company;
use App\Models\TaxRate;
use App\Models\Unit;
use App\Models\WhtRate;
use Illuminate\Database\Migrations\Migration;

/**
 * Found while investigating the "Zubaidi has the same disabled unit
 * picker Dynamic Core had" report: RealCompanySeeder (which seeds both
 * of those real companies) never called TaxRate::seedDefaults() or
 * WhtRate::seedDefaults(), only AccountMapping's. It bypasses the normal
 * signup flow (AuthController::register()) entirely, which does call all
 * three, plus Unit::seedDefaults(). A company with zero TaxRate rows
 * isn't just missing a dropdown option — TaxRate::defaultRate() falls
 * back to a bare 0.0 (documented: "never a hardcoded 15"), so every new
 * invoice/quotation/bill line for that company silently pre-filled at 0%
 * VAT instead of 15% until someone noticed and typed it in by hand.
 *
 * Mirrors 2026_09_01_000600's "if this company has none, seed the
 * defaults" shape, extended to all three sets it should have gotten at
 * signup — and covers every company missing any of them in one pass,
 * not just the two RealCompanySeeder wrote.
 */
return new class extends Migration
{
    public function up(): void
    {
        Company::query()->pluck('id')->each(function (int $companyId) {
            if (! TaxRate::where('company_id', $companyId)->exists()) {
                TaxRate::seedDefaults($companyId);
            }

            if (! WhtRate::where('company_id', $companyId)->exists()) {
                WhtRate::seedDefaults($companyId);
            }

            if (! Unit::where('company_id', $companyId)->exists()) {
                Unit::seedDefaults($companyId);
            }
        });
    }

    public function down(): void
    {
        // Deliberately irreversible — see 2026_09_01_000600_backfill_default_units_and_item_base_units.php.
    }
};
