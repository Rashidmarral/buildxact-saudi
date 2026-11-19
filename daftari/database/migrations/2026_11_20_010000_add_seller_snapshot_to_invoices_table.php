<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * QA follow-up finding: pdfData()['company'] was still a live join to
 * the issuing Company record — editing the company's own name/address
 * after an invoice was issued silently rewrote that invoice's historical
 * PDF just as party_snapshot (the buyer's side of this same problem) was
 * fixed to stop happening for the client. This column does the same
 * thing for the seller: frozen the moment an invoice is actually issued
 * (Invoice::capturePartySnapshot(), same call site as party_snapshot),
 * so later edits to the company's own profile can't rewrite an
 * already-issued invoice's seller details either.
 *
 * Same honest limitation as the party_snapshot migration before it: the
 * backfill below can only freeze every existing non-draft invoice at the
 * company's CURRENT details, because what the company's details genuinely
 * were at each invoice's original issuance was never recorded. Changes no
 * amounts, statuses, numbers, or GL postings — only this new column.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->json('seller_snapshot')->nullable()->after('party_snapshot');
        });

        DB::table('invoices')
            ->join('companies', 'companies.id', '=', 'invoices.company_id')
            ->where('invoices.status', '!=', 'draft')
            ->whereNull('invoices.seller_snapshot')
            ->select([
                'invoices.id as invoice_id',
                'companies.name as company_name',
                'companies.name_ar as company_name_ar',
                'companies.vat_number as company_vat_number',
                'companies.cr_number as company_cr_number',
                'companies.address as company_address',
                'companies.building_number as company_building_number',
                'companies.street_name as company_street_name',
                'companies.district as company_district',
                'companies.city as company_city',
                'companies.postal_code as company_postal_code',
                'companies.additional_number as company_additional_number',
                'companies.phone as company_phone',
                'companies.email as company_email',
                'companies.logo_path as company_logo_path',
                'companies.stamp_path as company_stamp_path',
                'companies.stamp_size as company_stamp_size',
            ])
            ->orderBy('invoices.id')
            ->chunkById(500, function ($rows) {
                foreach ($rows as $row) {
                    DB::table('invoices')->where('id', $row->invoice_id)->update([
                        'seller_snapshot' => json_encode([
                            'name' => $row->company_name,
                            'name_ar' => $row->company_name_ar,
                            'vat_number' => $row->company_vat_number,
                            'cr_number' => $row->company_cr_number,
                            'address' => $row->company_address,
                            'building_number' => $row->company_building_number,
                            'street_name' => $row->company_street_name,
                            'district' => $row->company_district,
                            'city' => $row->company_city,
                            'postal_code' => $row->company_postal_code,
                            'additional_number' => $row->company_additional_number,
                            'phone' => $row->company_phone,
                            'email' => $row->company_email,
                            'logo_path' => $row->company_logo_path,
                            'stamp_path' => $row->company_stamp_path,
                            'stamp_size' => $row->company_stamp_size,
                        ]),
                    ]);
                }
            }, 'invoices.id', 'invoice_id');
    }

    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropColumn('seller_snapshot');
        });
    }
};
