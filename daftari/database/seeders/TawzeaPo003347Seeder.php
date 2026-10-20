<?php

namespace Database\Seeders;

use App\Models\Client;
use App\Models\Company;
use App\Models\Quotation;
use App\Models\QuotationItem;
use App\Models\Unit;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * One real purchase order from Tawzea (International Water Distribution
 * Co.) to Abdulmajeed Abdullah Al-Zubaidi Est. for Maintenance — PO Number
 * MAKKAH / PO-003347, dated 6/4/2026, PR 006318, referencing the operator's
 * own earlier Quotation Ref 208. Imported from the PDF the operator
 * shared: 40 pump/spare-parts lines (Grundfos CR/SP, KSB Etanorm/Movitec,
 * mechanical seals, bearings), subtotal SAR 334,201.19, VAT 15% SAR
 * 50,130.18, grand total SAR 384,331.37. Terms printed on the PO: 50%
 * advance, 50% before delivery, Delivered Duty Paid, delivery date
 * 10/29/2026.
 *
 * Creates two documents from the same line items:
 * - A Quotation (type=quotation) recording what was agreed, referencing
 *   the client's PO number and the operator's own prior quotation ref.
 * - A Proforma Invoice (type=proforma) for the same order, with its Notes
 *   (rendered as the "Payment Terms" list on every print layout) stating
 *   the 50% advance already received and the 50% balance now due, per the
 *   operator's own request — there's no separate "amount paid" field on a
 *   quotation/proforma, so this is surfaced through the notes text rather
 *   than a new column, matching how payment terms are already shown on
 *   this document type.
 *
 * Deliberately not called from DatabaseSeeder — this is one operator's
 * real customer order, not demo data, same reasoning as
 * ZubaidiClientsSeeder/ZubaidiProductsSeeder. Run explicitly on the
 * operator's own instance: `php artisan db:seed --class=TawzeaPo003347Seeder`.
 * Depends on nothing else having run first — it resolves (or creates) the
 * client itself, so it works whether or not ZubaidiClientsSeeder was run.
 *
 * Idempotent: if a quotation or proforma already carrying this PO number
 * in its notes exists for this client, that document is left alone and
 * skipped rather than duplicated or silently rewritten — re-running this
 * after the operator has started editing the document in the UI must
 * never clobber their edits.
 */
class TawzeaPo003347Seeder extends Seeder
{
    private const PO_NUMBER = 'MAKKAH / PO-003347';

    public function run(): void
    {
        $company = Company::where('vat_number', '310464560600003')->first();

        if (! $company) {
            $this->command?->warn('Zubaidi Maintenance company not found — run RealCompanySeeder first.');

            return;
        }

        $client = Client::firstOrCreate(
            ['company_id' => $company->id, 'vat_number' => '300134471900003'],
            [
                'company_id' => $company->id,
                'type' => 'company',
                'name' => 'International Water Distribution Company',
                'name_ar' => 'الشركة الدولية لتوزيع المياة',
                'vat_number' => '300134471900003',
                'is_vat_registered' => true,
                'street_name' => 'شارع سعد الجنيدل',
                'district' => 'حي الروضة',
                'city' => 'جدة',
                'postal_code' => '23435',
                'phone' => '126068008',
                'country' => 'Saudi Arabia',
            ]
        );

        $unit = Unit::firstOrCreate(
            ['company_id' => $company->id, 'name' => 'Each'],
            ['symbol' => 'EA', 'code' => 'EA']
        );

        $items = $this->lineItems();

        $expectedSubtotal = 334201.19;
        $expectedVat = 50130.18;
        $expectedTotal = 384331.37;

        $computedSubtotal = round(collect($items)->sum(fn ($i) => $i['quantity'] * $i['unit_price']), 2);
        $computedVat = round($computedSubtotal * 0.15, 2);

        if (abs($computedSubtotal - $expectedSubtotal) > 0.02 || abs($computedVat - $expectedVat) > 0.02) {
            $this->command?->error(sprintf(
                'Line items do not reconcile with the PO totals (computed subtotal %.2f / vat %.2f vs expected %.2f / %.2f) — aborting without creating anything.',
                $computedSubtotal, $computedVat, $expectedSubtotal, $expectedVat
            ));

            return;
        }

        $this->createDocument(
            $company, $client, $unit, $items, 'quotation',
            fn () => "Client PO Ref: ".self::PO_NUMBER." (PR 006318)\n".
                "Our Quotation Ref: 208\n".
                "Payment Terms: 50% advance, 50% before delivery\n".
                "Delivery Terms: Delivered Duty Paid\n".
                "Requested Delivery Date: 29 Oct 2026"
        );

        // The 50/50 split is derived from this document's own computed
        // total (built after recalculateTotals() runs), not the PO's
        // hand-typed grand total — per-line VAT rounding can land a cent
        // or two off the buyer's own aggregate calculation, and the split
        // shown to the client must always foot to the total actually
        // printed on this proforma, not the source PO.
        $this->createDocument(
            $company, $client, $unit, $items, 'proforma',
            function (Quotation $quotation) {
                $advance = round($quotation->total / 2, 2);
                $balance = round($quotation->total - $advance, 2);

                return "Client PO Ref: ".self::PO_NUMBER."\n".
                    "Total Order Value (incl. VAT): SAR {$this->money($quotation->total)}\n".
                    "Advance Payment Received (50%): SAR {$this->money($advance)}\n".
                    "Balance Due (remaining 50%): SAR {$this->money($balance)}\n".
                    "Payment Terms: Balance due before delivery\n".
                    "Delivery Terms: Delivered Duty Paid | Requested Delivery Date: 29 Oct 2026";
            }
        );
    }

    private function createDocument(Company $company, Client $client, Unit $unit, array $items, string $type, \Closure $notesFor): void
    {
        $label = $type === 'proforma' ? 'Proforma invoice' : 'Quotation';

        $exists = Quotation::where('company_id', $company->id)
            ->where('client_id', $client->id)
            ->where('type', $type)
            ->where('notes', 'like', '%'.self::PO_NUMBER.'%')
            ->exists();

        if ($exists) {
            $this->command?->info("{$label} for ".self::PO_NUMBER.' already exists — skipping.');

            return;
        }

        DB::transaction(function () use ($company, $client, $unit, $items, $type, $notesFor, $label) {
            $quotation = Quotation::create([
                'company_id' => $company->id,
                'client_id' => $client->id,
                'branch_id' => $company->default_branch_id,
                'quotation_number' => $company->nextQuotationNumber($type),
                'type' => $type,
                'status' => 'draft',
                'issue_date' => now()->toDateString(),
                'expiry_date' => '2026-10-29',
                'currency' => $company->currency ?? 'SAR',
            ]);

            foreach ($items as $sort => $row) {
                $line = new QuotationItem([
                    'quotation_id' => $quotation->id,
                    'unit_id' => $unit->id,
                    'description' => $row['description'],
                    'quantity' => $row['quantity'],
                    'unit_price' => $row['unit_price'],
                    'vat_rate' => 15,
                    'sort_order' => $sort,
                ]);
                $line->recalculate();
                $line->save();
            }

            $quotation->recalculateTotals();

            $quotation->notes = $notesFor($quotation);
            $quotation->save();

            $this->command?->info("{$label} {$quotation->quotation_number} created for {$client->name} — total SAR ".$this->money($quotation->total).'.');
        });
    }

    private function money(float $value): string
    {
        return number_format($value, 2);
    }

    /**
     * @return array<int, array{description: string, quantity: float, unit_price: float}>
     */
    private function lineItems(): array
    {
        return [
            ['description' => 'Bulk, shaft seal HQQE - CR 32 to 150', 'quantity' => 3, 'unit_price' => 16000.00],
            ['description' => 'Spare, Shaft SP215-4 8"', 'quantity' => 2, 'unit_price' => 3537.00],
            ['description' => 'Kit, impeller standard CR90', 'quantity' => 20, 'unit_price' => 884.50],
            ['description' => 'Spare, Impeller 65-250/259', 'quantity' => 1, 'unit_price' => 9916.00],
            ['description' => 'Spare, Wear Ring D122/D136x10 BS', 'quantity' => 1, 'unit_price' => 443.00],
            ['description' => 'Kit, Shaft D32 single bearing', 'quantity' => 1, 'unit_price' => 1183.50],
            ['description' => 'Kit, Rubber for Std. Coupl. M20 Grundfos', 'quantity' => 1, 'unit_price' => 287.50],
            ['description' => 'Spare, O-ring EPDM 278.99x3.53', 'quantity' => 1, 'unit_price' => 144.11],
            ['description' => 'Kit, Bearings 6308 D40/D90x23 EPDM', 'quantity' => 1, 'unit_price' => 744.00],
            ['description' => 'Kit, Nut M18 D32 1.4401', 'quantity' => 1, 'unit_price' => 196.50],
            ['description' => 'Spare, Shaft seal BAQE GG N D38', 'quantity' => 1, 'unit_price' => 784.00],
            ['description' => 'Kit, Cover 65/80/100-250 D32 GG W/O WR', 'quantity' => 1, 'unit_price' => 1520.00],
            ['description' => 'Kit, Wear Parts SP125-4 Stages NBRmod.D', 'quantity' => 15, 'unit_price' => 2005.00],
            ['description' => 'Spare, Shaft SP125/160-4 8"', 'quantity' => 4, 'unit_price' => 2112.00],
            ['description' => 'Kit, Strainer mtr. 6" SP125/160 DOL Std.', 'quantity' => 5, 'unit_price' => 777.50],
            ['description' => 'Kit, Rep. NRV SP125/160 NBR', 'quantity' => 4, 'unit_price' => 3269.00],
            ['description' => 'Spare, Shaft SP215-4 8"', 'quantity' => 4, 'unit_price' => 2112.00],
            ['description' => 'Kit, Wear Parts SP125-4 Stages NBRmod. D', 'quantity' => 4, 'unit_price' => 2005.00],
            ['description' => 'Spare, Interm. Chamber NBR SP125', 'quantity' => 10, 'unit_price' => 2231.00],
            ['description' => 'Kit, Rep. NRV SP215 NBR', 'quantity' => 2, 'unit_price' => 2746.00],
            ['description' => 'Spare, Strainer 6" SP215', 'quantity' => 2, 'unit_price' => 907.50],
            ['description' => 'Spare, Shaft SP215-3 8"', 'quantity' => 2, 'unit_price' => 2668.00],
            ['description' => 'Spare, O-ring EPDM 329.79x3.53', 'quantity' => 2, 'unit_price' => 84.70],
            ['description' => 'Spare, Shaft seal BQQE GG N D48', 'quantity' => 4, 'unit_price' => 1366.00],
            ['description' => 'Kit, Bearings 6310 D50/D110x27 CPDM', 'quantity' => 4, 'unit_price' => 1018.50],
            ['description' => 'Kit, Bolt for Coupling 8xM20 w/Rubber', 'quantity' => 2, 'unit_price' => 923.00],
            ['description' => 'Seal, Mechanical F/MTV 24/32/2Stage 433', 'quantity' => 3, 'unit_price' => 1020.00],
            ['description' => 'KSB Movitec VF 40/4 - Shaft Impeller Set', 'quantity' => 1, 'unit_price' => 2500.00],
            ['description' => 'Seal, Mechanical F/MTV 24/32/2Stage 433', 'quantity' => 6, 'unit_price' => 1120.00],
            ['description' => 'KSB Movitec VF 90/3-2B - Shaft Impeller Set', 'quantity' => 2, 'unit_price' => 5020.00],
            ['description' => 'KSB Etanorm 080-065-250 - Shaft Sleeve Kit Bearing Set, 2 Rubber', 'quantity' => 2, 'unit_price' => 4550.00],
            ['description' => 'Mechanical Seal ETN 080-065-250', 'quantity' => 4, 'unit_price' => 2540.00],
            ['description' => 'KSB Etanorm 065-040-250 - Shaft Sleeve Kit Bearing Set, 2 Rubber', 'quantity' => 1, 'unit_price' => 4050.00],
            ['description' => 'Mechanical Seal ETN 065-040-250', 'quantity' => 1, 'unit_price' => 2150.00],
            ['description' => 'KSB Etanorm 100-080-250 - Shaft Sleeve Kit Bearing Set, 2 Rubber', 'quantity' => 5, 'unit_price' => 4650.00],
            ['description' => 'Mechanical Seal ETN 100-080-250', 'quantity' => 10, 'unit_price' => 2740.00],
            ['description' => 'KSB Etanorm 100-080-200 - Shaft Sleeve Kit Bearing Set, 2 Rubber', 'quantity' => 1, 'unit_price' => 4580.00],
            ['description' => 'Mechanical Seal ETN 100-080-200', 'quantity' => 2, 'unit_price' => 2632.00],
            ['description' => 'SKF Bearing 7315 B', 'quantity' => 6, 'unit_price' => 1250.00],
            ['description' => 'INQ002 Mechanical Seal', 'quantity' => 4, 'unit_price' => 2995.92],
        ];
    }
}
