<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * QA audit finding: pdfData()['doc']['party'] was a live join to the
 * Client record — editing the client's name after an invoice was issued
 * silently rewrote that invoice's historical PDF/public page. This
 * column freezes the buyer's details the moment an invoice is actually
 * issued (Invoice::capturePartySnapshot(), called from
 * InvoiceController::doSend()) so later client edits can't touch it.
 *
 * The backfill below can't reconstruct what a client's details genuinely
 * were back when each already-issued invoice was first sent — that
 * history was never recorded. Freezing every non-draft invoice at its
 * client's CURRENT details is still strictly better than leaving them
 * perpetually mutable going forward, and is the most honest thing this
 * migration can do with data that was never captured. It changes no
 * amounts, statuses, numbers, or GL postings — only this new column.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->json('party_snapshot')->nullable()->after('client_id');
        });

        DB::table('invoices')
            ->join('clients', 'clients.id', '=', 'invoices.client_id')
            ->where('invoices.status', '!=', 'draft')
            ->whereNull('invoices.party_snapshot')
            ->select([
                'invoices.id as invoice_id',
                'clients.name as client_name',
                'clients.name_ar as client_name_ar',
                'clients.vat_number as client_vat_number',
                'clients.cr_number as client_cr_number',
                'clients.building_number as client_building_number',
                'clients.street_name as client_street_name',
                'clients.district as client_district',
                'clients.city as client_city',
                'clients.state as client_state',
                'clients.country as client_country',
                'clients.postal_code as client_postal_code',
                'clients.email as client_email',
            ])
            ->orderBy('invoices.id')
            ->chunkById(500, function ($rows) {
                foreach ($rows as $row) {
                    DB::table('invoices')->where('id', $row->invoice_id)->update([
                        'party_snapshot' => json_encode([
                            'name' => $row->client_name,
                            'name_ar' => $row->client_name_ar,
                            'vat_number' => $row->client_vat_number,
                            'cr_number' => $row->client_cr_number,
                            'building_number' => $row->client_building_number,
                            'street_name' => $row->client_street_name,
                            'district' => $row->client_district,
                            'city' => $row->client_city,
                            'state' => $row->client_state,
                            'country' => $row->client_country,
                            'postal_code' => $row->client_postal_code,
                            'email' => $row->client_email,
                        ]),
                    ]);
                }
            }, 'invoices.id', 'invoice_id');
    }

    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropColumn('party_snapshot');
        });
    }
};
