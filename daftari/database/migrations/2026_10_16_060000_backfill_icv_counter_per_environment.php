<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Companion to the migration that added zatca_icv_counter: gives every
 * existing company's currently-active environment a starting counter
 * equal to the highest ICV it has actually ever sent, so switching
 * ZatcaSyncService::nextIcv() from a pooled-across-environments COUNT(*)
 * to this persisted, per-environment counter doesn't hand anyone's very
 * next real submission an ICV lower than one ZATCA has already seen.
 *
 * The real historical ICV is parsed directly out of each log row's own
 * stored xml_payload (the <cbc:ID>ICV</cbc:ID><cbc:UUID>N</cbc:UUID>
 * reference every generateFor*() method writes — see
 * ZatcaXmlGenerator::icvReference()) rather than reconstructed from row
 * counts, since submissions interleaved across environments (developer
 * testing, then simulation, then back to developer) mean a per-
 * environment row count and the ICV values that environment's own log
 * rows actually carry are not reliably the same number. A row with no
 * xml_payload (rejected before any XML was built, e.g. a cancelled
 * invoice) never consumed an ICV and is correctly skipped.
 */
return new class extends Migration
{
    public function up(): void
    {
        $tables = ['zatca_invoice_logs', 'zatca_credit_note_logs', 'zatca_debit_note_logs'];

        // [companyId][environment] => highest ICV found in that pair's log rows.
        $maxIcv = [];

        foreach ($tables as $table) {
            DB::table($table)
                ->whereNotNull('xml_payload')
                ->select(['company_id', 'environment', 'xml_payload'])
                ->orderBy('id')
                ->chunk(200, function ($rows) use (&$maxIcv) {
                    foreach ($rows as $row) {
                        if (! preg_match('/<cbc:ID>ICV<\/cbc:ID><cbc:UUID>(\d+)<\/cbc:UUID>/', (string) $row->xml_payload, $m)) {
                            continue;
                        }

                        $icv = (int) $m[1];
                        $key = $row->company_id.'|'.$row->environment;
                        $maxIcv[$key] = max($maxIcv[$key] ?? 0, $icv);
                    }
                });
        }

        if (empty($maxIcv)) {
            return;
        }

        $companies = DB::table('companies')->select(['id', 'zatca_environment'])->get()->keyBy('id');

        foreach ($maxIcv as $key => $icv) {
            [$companyId, $environment] = explode('|', $key, 2);
            $companyId = (int) $companyId;
            $company = $companies->get($companyId);

            if (! $company) {
                continue;
            }

            if ($environment === $company->zatca_environment) {
                // The environment currently live on the company row —
                // the one its very next real submission will actually use.
                DB::table('companies')->where('id', $companyId)->update(['zatca_icv_counter' => $icv]);
            } else {
                // Any other environment this company has visited already
                // has a save slot from Company::switchZatcaEnvironment();
                // updateOrInsert covers the defensive case where it
                // somehow doesn't.
                DB::table('zatca_environment_credentials')->updateOrInsert(
                    ['company_id' => $companyId, 'environment' => $environment],
                    ['icv_counter' => $icv, 'updated_at' => now(), 'created_at' => now()]
                );
            }
        }
    }

    public function down(): void
    {
        // Irreversible on purpose — this only seeds a starting point for
        // the new counter; there is nothing meaningful to restore by
        // zeroing it back out, and doing so would reintroduce the exact
        // regression this migration exists to prevent.
    }
};
