<?php

namespace Database\Seeders;

use App\Models\Company;
use App\Models\Item;
use Illuminate\Database\Seeder;

/**
 * Real service/parts catalog for Abdulmajeed Abdullah Al-Zubaidi Est. for
 * Maintenance (see RealCompanySeeder::seedZubaidiMaintenance()), imported
 * from the operator's own POS/inventory export. Deliberately not called
 * from DatabaseSeeder for the same reason RealCompanySeeder and
 * ZubaidiClientsSeeder aren't — this is one operator's real price list, not
 * demo data. Run explicitly on the operator's own instance only:
 * `php artisan db:seed --class=ZubaidiProductsSeeder`.
 *
 * Every row is seeded as item_type "service" (never "physical"), per the
 * operator's own instruction: the business doesn't hold traditional stock —
 * it bills maintenance-contract labor (e.g. National Water Company /
 * Khuraif / Tawzea generator and pump maintenance) and the spare parts it
 * supplies as service line items, not warehouse inventory. track_inventory
 * is therefore always false regardless of what the source export's own
 * IsService flag said for a given row.
 *
 * Idempotent: keyed on (company_id, sku) via updateOrCreate — the source
 * export's SKU numbers are unique across the whole sheet (verified before
 * relying on them, unlike the client import's source "Code" column, which
 * had duplicates and was dropped). Re-running after correcting a price or
 * name updates the row in place instead of duplicating it.
 *
 * Cleanup applied to the raw export:
 * - 2 placeholder/junk rows dropped ("TEST" and "______", both $0 price,
 *   and the only two rows the source itself did NOT flag as a service).
 * - MeasurementUnit values (EA, M2, Ltr, kg, Nos, عدد, لوحه, ...) normalized
 *   into the app's unit vocabulary, with a best-effort ZATCA unit_code
 *   (falls back to EA/"Each" for units — Set, Roll, Lump Sum, Metric Ton —
 *   that have no closer match in Item::UNIT_CODES).
 * - Cost was $0.00 (unpopulated) on 217 of 383 rows; stored as null rather
 *   than a bogus zero purchase price. Where Cost was genuinely populated
 *   it's kept as purchase_price for margin tracking.
 * - IsEnabled=0 rows (22 of them) are kept but seeded as is_active=false,
 *   preserving the source's own discontinued/inactive marking instead of
 *   silently dropping or activating them.
 * - ProductGroup, Barcode, Description and Supplier columns were empty for
 *   every row in the export, so they're left unset here.
 * - A couple of rows (SKUs 234, 235) carry a full work-order writeup in the
 *   Name column — 300+ characters, past the `items.name` varchar(255)
 *   limit. Those overflow the column on a real MySQL install even though
 *   SQLite (used in local dev/testing) silently accepts oversized strings,
 *   which is why this only surfaced on a production database. normalizeLongName()
 *   below truncates any such name to a word boundary and keeps the full
 *   original text as the item's description instead of dropping it —
 *   applied to every row, not just these two, so a future edit to the
 *   source data can't reintroduce the same failure.
 */
class ZubaidiProductsSeeder extends Seeder
{
    /**
     * items.name and items.name_ar are both varchar(255); description is
     * a text column with no practical limit. When a name is too long to
     * fit, truncate it at the last word boundary within the limit and
     * carry the untruncated original into description (never silently
     * discarding it) so the full text still reaches the PDF via the
     * per-template "show item description" option.
     */
    private function normalizeLongName(array $data, int $limit = 250): array
    {
        foreach (['name', 'name_ar'] as $field) {
            $value = $data[$field] ?? null;

            if ($value === null || mb_strlen($value) <= $limit) {
                continue;
            }

            $truncated = mb_substr($value, 0, $limit);
            $lastSpace = mb_strrpos($truncated, ' ');
            if ($lastSpace !== false && $lastSpace > 0) {
                $truncated = mb_substr($truncated, 0, $lastSpace);
            }

            $data[$field] = rtrim($truncated, " \t\n\r\0\x0B,.-").'…';
            $data['description'] = trim(($data['description'] ?? '')."\n".$value);
        }

        return $data;
    }

    public function run(): void
    {
        $company = Company::where('vat_number', '310464560600003')->first();

        if (! $company) {
            $this->command?->warn('Zubaidi Maintenance company not found — run RealCompanySeeder first.');

            return;
        }

        $items = [
            ['sku' => '1', 'name' => 'Removal And Installation of Defence Feather', 'unit' => 'Each', 'unit_code' => 'EA', 'unit_price' => 3650.0, 'purchase_price' => 3650.0, 'is_active' => false],
            ['sku' => '2', 'name' => 'Steel Defence Shaft', 'unit' => 'Each', 'unit_code' => 'EA', 'unit_price' => 1650.0, 'purchase_price' => 1650.0, 'is_active' => false],
            ['sku' => '3', 'name' => 'Bearings SKF رمان بلي', 'unit' => 'Each', 'unit_code' => 'EA', 'unit_price' => 150.0, 'purchase_price' => null, 'is_active' => true],
            ['sku' => '4', 'name' => 'Mechanical Seal 75mm', 'unit' => 'Each', 'unit_code' => 'EA', 'unit_price' => 800.0, 'purchase_price' => 800.0, 'is_active' => false],
            ['sku' => '5', 'name' => 'Welding and lath work for shaft base coupling', 'unit' => 'Each', 'unit_code' => 'EA', 'unit_price' => 400.0, 'purchase_price' => 400.0, 'is_active' => true],
            ['sku' => '6', 'name' => 'Removal and installation charges', 'unit' => 'Each', 'unit_code' => 'EA', 'unit_price' => 600.0, 'purchase_price' => 600.0, 'is_active' => true],
            ['sku' => '7', 'name' => 'Welding, Installation and lath work for Cylinder', 'unit' => 'Each', 'unit_code' => 'EA', 'unit_price' => 600.0, 'purchase_price' => 600.0, 'is_active' => true],
            ['sku' => '8', 'name' => 'Oil Seal', 'unit' => 'Each', 'unit_code' => 'EA', 'unit_price' => 85.0, 'purchase_price' => 85.0, 'is_active' => true],
            ['sku' => '9', 'name' => 'Cummins Seal Kit', 'unit' => 'Each', 'unit_code' => 'EA', 'unit_price' => 500.0, 'purchase_price' => 500.0, 'is_active' => true],
            ['sku' => '10', 'name' => 'Steel Pump Shaft', 'unit' => 'Each', 'unit_code' => 'EA', 'unit_price' => 1550.0, 'purchase_price' => 1550.0, 'is_active' => false],
            ['sku' => '11', 'name' => 'Welding and lath work for coupling', 'unit' => 'Each', 'unit_code' => 'EA', 'unit_price' => 250.0, 'purchase_price' => 250.0, 'is_active' => false],
            ['sku' => '12', 'name' => 'Removal and Installation of Caprari Pump 17 Pipe', 'unit' => 'Each', 'unit_code' => 'EA', 'unit_price' => 2300.0, 'purchase_price' => 2300.0, 'is_active' => false],
            ['sku' => '13', 'name' => 'Supply & installation of New Electric Panel 36 Line 2', 'unit' => 'Each', 'unit_code' => 'EA', 'unit_price' => 2200.0, 'purchase_price' => 2200.0, 'is_active' => false],
            ['sku' => '14', 'name' => 'Panel repairing & scoket Parts 3 (Sleev 8mm 5 , Cable Pipe 2in 3 , 1in 2)', 'unit' => 'Each', 'unit_code' => 'EA', 'unit_price' => 1096.67, 'purchase_price' => 1096.67, 'is_active' => false],
            ['sku' => '15', 'name' => 'Supply & Installation of Split AC 24000 BTU', 'unit' => 'Each', 'unit_code' => 'EA', 'unit_price' => 3500.0, 'purchase_price' => 3500.0, 'is_active' => false],
            ['sku' => '16', 'name' => 'Clindre Coupling', 'unit' => 'Each', 'unit_code' => 'EA', 'unit_price' => 350.0, 'purchase_price' => 350.0, 'is_active' => false],
            ['sku' => '17', 'name' => 'Lath Work for Pump Cylinder', 'unit' => 'Each', 'unit_code' => 'EA', 'unit_price' => 250.0, 'purchase_price' => 250.0, 'is_active' => true],
            ['sku' => '18', 'name' => 'Blotting Powder', 'unit' => 'Each', 'unit_code' => 'EA', 'unit_price' => 2700.0, 'purchase_price' => 2700.0, 'is_active' => false],
            ['sku' => '19', 'name' => 'Varnish Casting Aluminum Fan', 'unit' => 'Each', 'unit_code' => 'EA', 'unit_price' => 450.0, 'purchase_price' => 450.0, 'is_active' => false],
            ['sku' => '20', 'name' => 'Rewinding Dynamo 50kw', 'unit' => 'Each', 'unit_code' => 'EA', 'unit_price' => 5500.0, 'purchase_price' => 5500.0, 'is_active' => true],
            ['sku' => '21', 'name' => 'Eelctric - Work General / Supply and installation As per PO# PO-2022-11-00788', 'unit' => 'Each', 'unit_code' => 'EA', 'unit_price' => 7600.0, 'purchase_price' => 7600.0, 'is_active' => false],
            ['sku' => '22', 'name' => 'Ferraz Shawmut 75v-1600A Feus', 'unit' => 'Each', 'unit_code' => 'EA', 'unit_price' => 1600.0, 'purchase_price' => 1600.0, 'is_active' => false],
            ['sku' => '23', 'name' => 'Rewinding Submersible pump 20HP', 'unit' => 'Each', 'unit_code' => 'EA', 'unit_price' => 5000.0, 'purchase_price' => 5000.0, 'is_active' => true],
            ['sku' => '24', 'name' => 'Mechnical Seal Orignal', 'unit' => 'Each', 'unit_code' => 'EA', 'unit_price' => 1150.0, 'purchase_price' => 1150.0, 'is_active' => true],
            ['sku' => '25', 'name' => 'Pipe Flange Casting', 'unit' => 'Each', 'unit_code' => 'EA', 'unit_price' => 200.0, 'purchase_price' => 200.0, 'is_active' => false],
            ['sku' => '26', 'name' => 'Clean and Paint Pump', 'unit' => 'Each', 'unit_code' => 'EA', 'unit_price' => 150.0, 'purchase_price' => 150.0, 'is_active' => false],
            ['sku' => '27', 'name' => 'Galvanized mesh coated PVC 60x60 H=2.5 4mm', 'unit' => 'Metric Ton', 'unit_code' => 'EA', 'unit_price' => 31.0, 'purchase_price' => 31.0, 'is_active' => false],
            ['sku' => '28', 'name' => 'Pipe 3m ,m Galvanized , Green Color', 'unit' => 'Each', 'unit_code' => 'EA', 'unit_price' => 94.0, 'purchase_price' => 94.0, 'is_active' => false],
            ['sku' => '29', 'name' => 'Tension Wire 5.5mm', 'unit' => 'Metre', 'unit_code' => 'MTR', 'unit_price' => 7.0, 'purchase_price' => 7.0, 'is_active' => false],
            ['sku' => '30', 'name' => 'Rewinding motor 37kw لف موتور', 'unit' => 'Each', 'unit_code' => 'EA', 'unit_price' => 3800.0, 'purchase_price' => null, 'is_active' => true],
            ['sku' => '31', 'name' => 'Motor Maintenance 45kw', 'unit' => 'Each', 'unit_code' => 'EA', 'unit_price' => 2500.0, 'purchase_price' => 2500.0, 'is_active' => true],
            ['sku' => '32', 'name' => 'Bearing change and celender welding of water pump', 'unit' => 'Each', 'unit_code' => 'EA', 'unit_price' => 2700.0, 'purchase_price' => 2700.0, 'is_active' => false],
            ['sku' => '33', 'name' => 'Bearings Orignal', 'unit' => 'Each', 'unit_code' => 'EA', 'unit_price' => 225.0, 'purchase_price' => 225.0, 'is_active' => true],
            ['sku' => '34', 'name' => 'Welding & milling of Cylender', 'unit' => 'Each', 'unit_code' => 'EA', 'unit_price' => 250.0, 'purchase_price' => 250.0, 'is_active' => true],
            ['sku' => '35', 'name' => 'Repairing Fees', 'unit' => 'Each', 'unit_code' => 'EA', 'unit_price' => 500.0, 'purchase_price' => 500.0, 'is_active' => true],
            ['sku' => '36', 'name' => 'Bucline 17m rent', 'unit' => 'Day', 'unit_code' => 'DAY', 'unit_price' => 3500.0, 'purchase_price' => 3500.0, 'is_active' => true],
            ['sku' => '37', 'name' => 'Repair and Maintenence for Pump 6"', 'unit' => 'Each', 'unit_code' => 'EA', 'unit_price' => 700.0, 'purchase_price' => null, 'is_active' => true],
            ['sku' => '38', 'name' => 'Repair Track Base ( Screen) Installing new Series Duct Left & Right', 'unit' => 'Each', 'unit_code' => 'EA', 'unit_price' => 6900.0, 'purchase_price' => 6900.0, 'is_active' => false],
            ['sku' => '39', 'name' => 'Exciter Rewinding 60kw', 'unit' => 'Each', 'unit_code' => 'EA', 'unit_price' => 950.0, 'purchase_price' => 950.0, 'is_active' => true],
            ['sku' => '40', 'name' => 'Visit and Checking Site Charges', 'unit' => 'Each', 'unit_code' => 'EA', 'unit_price' => 500.0, 'purchase_price' => 500.0, 'is_active' => true],
            ['sku' => '41', 'name' => 'Silencer of power Generator 60kw (USED)', 'unit' => 'Each', 'unit_code' => 'EA', 'unit_price' => 28000.0, 'purchase_price' => 28000.0, 'is_active' => true],
            ['sku' => '42', 'name' => 'Generator Trolley', 'unit' => 'Each', 'unit_code' => 'EA', 'unit_price' => 2950.0, 'purchase_price' => 2950.0, 'is_active' => false],
            ['sku' => '43', 'name' => 'Trolley Wheels', 'unit' => 'Each', 'unit_code' => 'EA', 'unit_price' => 275.0, 'purchase_price' => 275.0, 'is_active' => true],
            ['sku' => '44', 'name' => 'Rewinding Motor 22kw 380v-220v RPM1465 لف موتور', 'unit' => 'Each', 'unit_code' => 'EA', 'unit_price' => 3000.0, 'purchase_price' => 300.0, 'is_active' => true],
            ['sku' => '45', 'name' => 'Breaker MasterPact NW25 H1 2500A, 690V AC23A 50/60 Hz ICU-65KA Ui1000V Uimp 12KV', 'unit' => 'Each', 'unit_code' => 'EA', 'unit_price' => 32000.0, 'purchase_price' => 32000.0, 'is_active' => true],
            ['sku' => '46', 'name' => 'Crain one Rental Bases', 'unit' => 'Day', 'unit_code' => 'DAY', 'unit_price' => 700.0, 'purchase_price' => 700.0, 'is_active' => true],
            ['sku' => '47', 'name' => 'CNC Co2 Laser Cutting Machine , Supply and Installation Complete Set', 'unit' => 'Each', 'unit_code' => 'EA', 'unit_price' => 51000.0, 'purchase_price' => 51000.0, 'is_active' => true],
            ['sku' => '48', 'name' => 'Repairing and Maintenence Valve DN800', 'unit' => 'Each', 'unit_code' => 'EA', 'unit_price' => 1500.0, 'purchase_price' => 1500.0, 'is_active' => true],
            ['sku' => '49', 'name' => 'impeller', 'unit' => 'Each', 'unit_code' => 'EA', 'unit_price' => 2500.0, 'purchase_price' => 2500.0, 'is_active' => true],
            ['sku' => '50', 'name' => 'Pump Coupling', 'unit' => 'Each', 'unit_code' => 'EA', 'unit_price' => 550.0, 'purchase_price' => 550.0, 'is_active' => true],
            ['sku' => '51', 'name' => 'Rewinding Water Pump With Bearing & seal', 'unit' => 'Each', 'unit_code' => 'EA', 'unit_price' => 380.0, 'purchase_price' => 380.0, 'is_active' => true],
            ['sku' => '52', 'name' => 'Visit and Check Electricity', 'unit' => 'Each', 'unit_code' => 'EA', 'unit_price' => 500.0, 'purchase_price' => 500.0, 'is_active' => true],
            ['sku' => '53', 'name' => 'Focus Lens', 'unit' => 'Each', 'unit_code' => 'EA', 'unit_price' => 750.0, 'purchase_price' => 750.0, 'is_active' => true],
            ['sku' => '54', 'name' => 'Laser Alignment', 'unit' => 'Each', 'unit_code' => 'EA', 'unit_price' => 1500.0, 'purchase_price' => 1500.0, 'is_active' => true],
            ['sku' => '55', 'name' => 'Laser Tube 100W Supply and Installation', 'unit' => 'Each', 'unit_code' => 'EA', 'unit_price' => 4500.0, 'purchase_price' => 4500.0, 'is_active' => true],
            ['sku' => '56', 'name' => 'Repair of water Pump', 'unit' => 'Each', 'unit_code' => 'EA', 'unit_price' => 192.0, 'purchase_price' => 192.0, 'is_active' => true],
            ['sku' => '57', 'name' => 'Router Bits Single Flute 6mm x 25mm', 'unit' => 'Each', 'unit_code' => 'EA', 'unit_price' => 80.0, 'purchase_price' => 80.0, 'is_active' => true],
            ['sku' => '58', 'name' => 'Router Bits Single Flute 4mm x 25mm', 'unit' => 'Each', 'unit_code' => 'EA', 'unit_price' => 75.0, 'purchase_price' => 75.0, 'is_active' => true],
            ['sku' => '59', 'name' => 'Router Bits Single Flute 3mm x 25mm', 'unit' => 'Each', 'unit_code' => 'EA', 'unit_price' => 75.0, 'purchase_price' => 75.0, 'is_active' => true],
            ['sku' => '60', 'name' => 'Dust Collector Bag Set', 'unit' => 'Each', 'unit_code' => 'EA', 'unit_price' => 300.0, 'purchase_price' => 300.0, 'is_active' => true],
            ['sku' => '61', 'name' => 'Bearing and Seal of Water Pump', 'unit' => 'Each', 'unit_code' => 'EA', 'unit_price' => 180.0, 'purchase_price' => 180.0, 'is_active' => true],
            ['sku' => '62', 'name' => 'Front Cover', 'unit' => 'Each', 'unit_code' => 'EA', 'unit_price' => 160.0, 'purchase_price' => 160.0, 'is_active' => true],
            ['sku' => '63', 'name' => 'CU/XLP/PVC Cable 4x185 mm', 'unit' => 'Metric Ton', 'unit_code' => 'EA', 'unit_price' => 395.0, 'purchase_price' => 395.0, 'is_active' => true],
            ['sku' => '64', 'name' => 'CU/XLP/PVC Cable 3x150+70 mm', 'unit' => 'Metric Ton', 'unit_code' => 'EA', 'unit_price' => 295.0, 'purchase_price' => 295.0, 'is_active' => true],
            ['sku' => '65', 'name' => 'CU/XLP/PVC Cable 3x50+25 mm', 'unit' => 'Metric Ton', 'unit_code' => 'EA', 'unit_price' => 115.0, 'purchase_price' => 115.0, 'is_active' => true],
            ['sku' => '66', 'name' => 'HDM3160S12533XXT MCCB 3P 125A HIMEL', 'unit' => 'Each', 'unit_code' => 'EA', 'unit_price' => 565.0, 'purchase_price' => 565.0, 'is_active' => true],
            ['sku' => '67', 'name' => 'HDM3630S50033XX MCCB 3P 500A HIMEL', 'unit' => 'Each', 'unit_code' => 'EA', 'unit_price' => 1500.0, 'purchase_price' => 1500.0, 'is_active' => true],
            ['sku' => '68', 'name' => 'HDM3630S63033XX MCCB 3P 630A HIMEL', 'unit' => 'Each', 'unit_code' => 'EA', 'unit_price' => 1900.0, 'purchase_price' => 1900.0, 'is_active' => true],
            ['sku' => '69', 'name' => 'Lath Machine Work', 'unit' => 'Each', 'unit_code' => 'EA', 'unit_price' => 150.0, 'purchase_price' => 150.0, 'is_active' => true],
            ['sku' => '70', 'name' => 'Bearing Japani', 'unit' => 'Each', 'unit_code' => 'EA', 'unit_price' => 250.0, 'purchase_price' => 250.0, 'is_active' => true],
            ['sku' => '71', 'name' => 'Rewinding Motor 40HP', 'unit' => 'Each', 'unit_code' => 'EA', 'unit_price' => 1800.0, 'purchase_price' => 1800.0, 'is_active' => true],
            ['sku' => '72', 'name' => 'Steel Defence Shaft', 'unit' => 'Each', 'unit_code' => 'EA', 'unit_price' => 1700.0, 'purchase_price' => 1700.0, 'is_active' => true],
            ['sku' => '73', 'name' => 'GRUNDFO Genuine Spare Impeller cpl-SP 215 (P/N 96903238)', 'unit' => 'Each', 'unit_code' => 'EA', 'unit_price' => 1800.0, 'purchase_price' => 1800.0, 'is_active' => true],
            ['sku' => '74', 'name' => 'Equipment for Workshop', 'unit' => 'Each', 'unit_code' => 'EA', 'unit_price' => 1450.0, 'purchase_price' => 1450.0, 'is_active' => true],
            ['sku' => '75', 'name' => 'Gypsum Board Work 75 meter Double partition', 'unit' => 'Each', 'unit_code' => 'EA', 'unit_price' => 1450.0, 'purchase_price' => 1450.0, 'is_active' => true],
            ['sku' => '76', 'name' => 'Paint Work', 'unit' => 'Each', 'unit_code' => 'EA', 'unit_price' => 1200.0, 'purchase_price' => 1200.0, 'is_active' => true],
            ['sku' => '77', 'name' => 'Rewinding Fan Motor complete with Bearin & Seal', 'unit' => 'Each', 'unit_code' => 'EA', 'unit_price' => 280.0, 'purchase_price' => 280.0, 'is_active' => true],
            ['sku' => '78', 'name' => 'Rewinding Feed Motor', 'unit' => 'Each', 'unit_code' => 'EA', 'unit_price' => 250.0, 'purchase_price' => 250.0, 'is_active' => true],
            ['sku' => '79', 'name' => 'Installation of Bearing', 'unit' => 'Each', 'unit_code' => 'EA', 'unit_price' => 175.0, 'purchase_price' => 175.0, 'is_active' => true],
            ['sku' => '80', 'name' => 'Change Machine Seal 38mm', 'unit' => 'Each', 'unit_code' => 'EA', 'unit_price' => 550.0, 'purchase_price' => 550.0, 'is_active' => true],
            ['sku' => '81', 'name' => 'Change Stainless steel Shaft', 'unit' => 'Each', 'unit_code' => 'EA', 'unit_price' => 1450.0, 'purchase_price' => 1450.0, 'is_active' => true],
            ['sku' => '82', 'name' => 'Repair & Maintenance For Flexible Joint', 'unit' => 'Each', 'unit_code' => 'EA', 'unit_price' => 2500.0, 'purchase_price' => 2500.0, 'is_active' => true],
            ['sku' => '83', 'name' => 'OLD CNC Router Maintenance', 'unit' => 'Each', 'unit_code' => 'EA', 'unit_price' => 2500.0, 'purchase_price' => 2500.0, 'is_active' => true],
            ['sku' => '84', 'name' => 'GRC Pump Machine Re-Wiring and Installation of Invertor', 'unit' => 'Each', 'unit_code' => 'EA', 'unit_price' => 2000.0, 'purchase_price' => 2000.0, 'is_active' => true],
            ['sku' => '85', 'name' => 'CNC Router Maintenence with Parts', 'unit' => 'Each', 'unit_code' => 'EA', 'unit_price' => 1500.0, 'purchase_price' => 1500.0, 'is_active' => true],
            ['sku' => '86', 'name' => 'Laying MC1', 'unit' => 'Square Metre', 'unit_code' => 'MTK', 'unit_price' => 2.5, 'purchase_price' => 2.5, 'is_active' => true],
            ['sku' => '87', 'name' => 'Equipment of Rental Basis', 'unit' => 'Day', 'unit_code' => 'DAY', 'unit_price' => 6000.0, 'purchase_price' => 6000.0, 'is_active' => true],
            ['sku' => '88', 'name' => 'Rewinding Air Blower', 'unit' => 'Each', 'unit_code' => 'EA', 'unit_price' => 380.0, 'purchase_price' => 380.0, 'is_active' => true],
            ['sku' => '89', 'name' => 'DN400 Flexible Joint PN16', 'unit' => 'Each', 'unit_code' => 'EA', 'unit_price' => 4500.0, 'purchase_price' => 4500.0, 'is_active' => true],
            ['sku' => '90', 'name' => 'Rewinding Dynamo 20KW', 'unit' => 'Each', 'unit_code' => 'EA', 'unit_price' => 2850.0, 'purchase_price' => 2850.0, 'is_active' => true],
            ['sku' => '91', 'name' => 'LowBed Rental Basis', 'unit' => 'Day', 'unit_code' => 'DAY', 'unit_price' => 1000.0, 'purchase_price' => 1000.0, 'is_active' => true],
            ['sku' => '92', 'name' => 'Dumping Road Roller with Tyers', 'unit' => 'Day', 'unit_code' => 'DAY', 'unit_price' => 900.0, 'purchase_price' => 900.0, 'is_active' => true],
            ['sku' => '93', 'name' => 'Dumping Road Roller Iron Small', 'unit' => 'Day', 'unit_code' => 'DAY', 'unit_price' => 400.0, 'purchase_price' => 400.0, 'is_active' => true],
            ['sku' => '94', 'name' => 'Transportation of Equipment Roller', 'unit' => 'Each', 'unit_code' => 'EA', 'unit_price' => 100.0, 'purchase_price' => 100.0, 'is_active' => true],
            ['sku' => '95', 'name' => 'Bobcat Shawal', 'unit' => 'Day', 'unit_code' => 'DAY', 'unit_price' => 400.0, 'purchase_price' => 400.0, 'is_active' => true],
            ['sku' => '96', 'name' => 'CNC Router Machine Transfer', 'unit' => 'Each', 'unit_code' => 'EA', 'unit_price' => 1000.0, 'purchase_price' => 1000.0, 'is_active' => true],
            ['sku' => '97', 'name' => 'Aristo Cutter Transfer', 'unit' => 'Each', 'unit_code' => 'EA', 'unit_price' => 2500.0, 'purchase_price' => 2500.0, 'is_active' => true],
            ['sku' => '98', 'name' => 'Durst Printer Transfer', 'unit' => 'Each', 'unit_code' => 'EA', 'unit_price' => 5000.0, 'purchase_price' => 5000.0, 'is_active' => true],
            ['sku' => '99', 'name' => 'Repair Fan Motor', 'unit' => 'Each', 'unit_code' => 'EA', 'unit_price' => 180.0, 'purchase_price' => 180.0, 'is_active' => true],
            ['sku' => '100', 'name' => 'Lath Work Fan Motor', 'unit' => 'Each', 'unit_code' => 'EA', 'unit_price' => 150.0, 'purchase_price' => 150.0, 'is_active' => true],
            ['sku' => '101', 'name' => 'Technical Visit of Co2 Laser Maintenence with repairing', 'unit' => 'Each', 'unit_code' => 'EA', 'unit_price' => 1000.0, 'purchase_price' => 1000.0, 'is_active' => true],
            ['sku' => '102', 'name' => 'Rental of Generator 100KW', 'unit' => 'Each', 'unit_code' => 'EA', 'unit_price' => 5000.0, 'purchase_price' => 5000.0, 'is_active' => true],
            ['sku' => '104', 'name' => 'Supply and Menufecturing of L Shape Pieces of 1.25mm Galvanized Steel Sheet', 'unit' => 'Each', 'unit_code' => 'EA', 'unit_price' => 25.0, 'purchase_price' => 25.0, 'is_active' => true],
            ['sku' => '105', 'name' => 'Lamination Machine Maintenence', 'unit' => 'Each', 'unit_code' => 'EA', 'unit_price' => 2000.0, 'purchase_price' => 2000.0, 'is_active' => true],
            ['sku' => '106', 'name' => 'Pully Motor', 'unit' => 'Each', 'unit_code' => 'EA', 'unit_price' => 80.0, 'purchase_price' => 80.0, 'is_active' => true],
            ['sku' => '107', 'name' => 'Bearing Assy-3311', 'unit' => 'Each', 'unit_code' => 'EA', 'unit_price' => 750.0, 'purchase_price' => 750.0, 'is_active' => true],
            ['sku' => '108', 'name' => 'Cylinder Cutting and Welding', 'unit' => 'Each', 'unit_code' => 'EA', 'unit_price' => 550.0, 'purchase_price' => 550.0, 'is_active' => true],
            ['sku' => '109', 'name' => 'Cylinder Bushing', 'unit' => 'Each', 'unit_code' => 'EA', 'unit_price' => 275.0, 'purchase_price' => 275.0, 'is_active' => true],
            ['sku' => '110', 'name' => 'Pump Impeller Sykes', 'unit' => 'Each', 'unit_code' => 'EA', 'unit_price' => 3000.0, 'purchase_price' => 3000.0, 'is_active' => true],
            ['sku' => '111', 'name' => 'Supply and Manufecturing of Angle Shape Pieces of 1mm Galvanized Steel 8x244 Length', 'unit' => 'Each', 'unit_code' => 'EA', 'unit_price' => 40.0, 'purchase_price' => 40.0, 'is_active' => true],
            ['sku' => '112', 'name' => 'خرط مخرطه هب و ميزان', 'unit' => 'Each', 'unit_code' => 'EA', 'unit_price' => 200.0, 'purchase_price' => 200.0, 'is_active' => true],
            ['sku' => '113', 'name' => 'مخرطة توضيب صره خلفي', 'unit' => 'Each', 'unit_code' => 'EA', 'unit_price' => 280.0, 'purchase_price' => 280.0, 'is_active' => true],
            ['sku' => '114', 'name' => 'توظيب مخرطه كرتيل زيت', 'unit' => 'Each', 'unit_code' => 'EA', 'unit_price' => 250.0, 'purchase_price' => 250.0, 'is_active' => true],
            ['sku' => '115', 'name' => 'CNC Router Maintenence Replacement Motor', 'unit' => 'Each', 'unit_code' => 'EA', 'unit_price' => 600.0, 'purchase_price' => 600.0, 'is_active' => true],
            ['sku' => '116', 'name' => 'توظيب مخرطة بوشنك', 'unit' => 'Each', 'unit_code' => 'EA', 'unit_price' => 150.0, 'purchase_price' => 150.0, 'is_active' => true],
            ['sku' => '117', 'name' => 'توظيب و مخرطة دسك خلفي', 'unit' => 'Each', 'unit_code' => 'EA', 'unit_price' => 180.0, 'purchase_price' => 180.0, 'is_active' => true],
            ['sku' => '118', 'name' => 'Stainless Steel Sheet Cutting with Laser as per Drawing', 'unit' => 'Each', 'unit_code' => 'EA', 'unit_price' => 23.0, 'purchase_price' => 23.0, 'is_active' => true],
            ['sku' => '119', 'name' => 'Filter Diesel', 'unit' => 'Each', 'unit_code' => 'EA', 'unit_price' => 80.0, 'purchase_price' => 80.0, 'is_active' => true],
            ['sku' => '120', 'name' => 'Filter Oil', 'unit' => 'Each', 'unit_code' => 'EA', 'unit_price' => 50.0, 'purchase_price' => 50.0, 'is_active' => true],
            ['sku' => '121', 'name' => 'مخرط عمود كردان', 'unit' => 'Each', 'unit_code' => 'EA', 'unit_price' => 500.0, 'purchase_price' => 500.0, 'is_active' => true],
            ['sku' => '122', 'name' => 'اصلاح لف دينمو كهرباء', 'unit' => 'Each', 'unit_code' => 'EA', 'unit_price' => 500.0, 'purchase_price' => 500.0, 'is_active' => true],
            ['sku' => '123', 'name' => 'Welding and lathe cylinder', 'unit' => 'Each', 'unit_code' => 'EA', 'unit_price' => 450.0, 'purchase_price' => 450.0, 'is_active' => true],
            ['sku' => '124', 'name' => 'Welding and Threading and bearing balance', 'unit' => 'Each', 'unit_code' => 'EA', 'unit_price' => 250.0, 'purchase_price' => 250.0, 'is_active' => true],
            ['sku' => '125', 'name' => 'Bush Installation', 'unit' => 'Each', 'unit_code' => 'EA', 'unit_price' => 125.0, 'purchase_price' => 125.0, 'is_active' => true],
            ['sku' => '126', 'name' => 'كمر حديد', 'unit' => 'Each', 'unit_code' => 'EA', 'unit_price' => 250.0, 'purchase_price' => 250.0, 'is_active' => true],
            ['sku' => '127', 'name' => 'Voltage Regulator 380v 50KVA (China)', 'unit' => 'Each', 'unit_code' => 'EA', 'unit_price' => 10500.0, 'purchase_price' => 10500.0, 'is_active' => true],
            ['sku' => '128', 'name' => 'Stainless Steel Sheet Cutting with Laser (Waslat)', 'unit' => 'Each', 'unit_code' => 'EA', 'unit_price' => 23.0, 'purchase_price' => 23.0, 'is_active' => true],
            ['sku' => '129', 'name' => 'Hanging Steel Chain 30mm', 'unit' => 'Metre', 'unit_code' => 'MTR', 'unit_price' => 1000.0, 'purchase_price' => 1000.0, 'is_active' => true],
            ['sku' => '130', 'name' => 'Hanging Steel Chain 8mm', 'unit' => 'Metre', 'unit_code' => 'MTR', 'unit_price' => 50.0, 'purchase_price' => 50.0, 'is_active' => true],
            ['sku' => '131', 'name' => 'Fiber Laser Machine for Metal installation', 'unit' => 'Each', 'unit_code' => 'EA', 'unit_price' => 2700.0, 'purchase_price' => 2700.0, 'is_active' => true],
            ['sku' => '132', 'name' => 'Co2 Acrylic Laser Machine installation', 'unit' => 'Each', 'unit_code' => 'EA', 'unit_price' => 1500.0, 'purchase_price' => 1500.0, 'is_active' => true],
            ['sku' => '133', 'name' => 'Voltage Stablizer Transportation', 'unit' => 'Each', 'unit_code' => 'EA', 'unit_price' => 100.0, 'purchase_price' => 100.0, 'is_active' => true],
            ['sku' => '134', 'name' => 'Motor with Gear Reducer 36v for Lamination Machine', 'unit' => 'Each', 'unit_code' => 'EA', 'unit_price' => 1500.0, 'purchase_price' => 1500.0, 'is_active' => true],
            ['sku' => '135', 'name' => 'Aristo Tool Bits 7275 5mm', 'unit' => 'Each', 'unit_code' => 'EA', 'unit_price' => 120.0, 'purchase_price' => 120.0, 'is_active' => true],
            ['sku' => '136', 'name' => '24v RXM Relay Switches 14 pins Schnider', 'unit' => 'Each', 'unit_code' => 'EA', 'unit_price' => 210.0, 'purchase_price' => 210.0, 'is_active' => true],
            ['sku' => '137', 'name' => 'Casing Ring - SUC (M003100600110)', 'unit' => 'Set', 'unit_code' => 'EA', 'unit_price' => 2018.14, 'purchase_price' => 2018.14, 'is_active' => true],
            ['sku' => '138', 'name' => 'Wear Ring - DEL (M003090600110)', 'unit' => 'Set', 'unit_code' => 'EA', 'unit_price' => 946.4, 'purchase_price' => 946.4, 'is_active' => true],
            ['sku' => '139', 'name' => 'Int. Bush-Diffuser with Guide Vane (M001090600110)', 'unit' => 'Set', 'unit_code' => 'EA', 'unit_price' => 1805.0, 'purchase_price' => 1805.0, 'is_active' => true],
            ['sku' => '140', 'name' => 'Set of Bearings', 'unit' => 'Set', 'unit_code' => 'EA', 'unit_price' => 16404.0, 'purchase_price' => 16404.0, 'is_active' => true],
            ['sku' => '141', 'name' => 'Mechanical Seal (78605STSD)', 'unit' => 'Set', 'unit_code' => 'EA', 'unit_price' => 30410.0, 'purchase_price' => 30410.0, 'is_active' => true],
            ['sku' => '142', 'name' => 'Co2 Laser Connector supply and installation', 'unit' => 'Each', 'unit_code' => 'EA', 'unit_price' => 100.0, 'purchase_price' => 100.0, 'is_active' => true],
            ['sku' => '143', 'name' => 'Technical visit', 'unit' => 'Each', 'unit_code' => 'EA', 'unit_price' => 500.0, 'purchase_price' => 500.0, 'is_active' => true],
            ['sku' => '144', 'name' => 'Hanging Steel Chain 25mm', 'unit' => 'Metre', 'unit_code' => 'MTR', 'unit_price' => 850.0, 'purchase_price' => 850.0, 'is_active' => true],
            ['sku' => '145', 'name' => 'Hanging Steel Chain 20mm', 'unit' => 'Metre', 'unit_code' => 'MTR', 'unit_price' => 700.0, 'purchase_price' => 700.0, 'is_active' => true],
            ['sku' => '146', 'name' => 'Durst Printer Ink (CMYK-LM-LC)', 'unit' => 'Litre', 'unit_code' => 'LTR', 'unit_price' => 480.0, 'purchase_price' => 480.0, 'is_active' => true],
            ['sku' => '147', 'name' => 'Durst Printer Head Cleaing Solution', 'unit' => 'Litre', 'unit_code' => 'LTR', 'unit_price' => 430.0, 'purchase_price' => 430.0, 'is_active' => true],
            ['sku' => '148', 'name' => 'توضيب حدف', 'unit' => 'Each', 'unit_code' => 'EA', 'unit_price' => 250.0, 'purchase_price' => 250.0, 'is_active' => true],
            ['sku' => '149', 'name' => 'Installation of Water Pump and Motor', 'unit' => 'Each', 'unit_code' => 'EA', 'unit_price' => 1700.0, 'purchase_price' => 1700.0, 'is_active' => true],
            ['sku' => '150', 'name' => 'باب حديد بليزر 3ملي', 'unit' => 'Square Metre', 'unit_code' => 'MTK', 'unit_price' => 390.0, 'purchase_price' => 390.0, 'is_active' => true],
            ['sku' => '151', 'name' => 'غطاء حديد 20-50', 'unit' => 'Each', 'unit_code' => 'EA', 'unit_price' => 161.0, 'purchase_price' => 161.0, 'is_active' => true],
            ['sku' => '152', 'name' => 'Plasma Torch 125A Hypertherm', 'unit' => 'Each', 'unit_code' => 'EA', 'unit_price' => 4945.0, 'purchase_price' => 4945.0, 'is_active' => true],
            ['sku' => '153', 'name' => 'CNC Plasma Machine Maintnence Software installation and Training', 'unit' => 'Each', 'unit_code' => 'EA', 'unit_price' => 2500.0, 'purchase_price' => 2500.0, 'is_active' => true],
            ['sku' => '154', 'name' => 'Crain on Rental Basis (Daily Base)', 'unit' => 'Day', 'unit_code' => 'DAY', 'unit_price' => 900.0, 'purchase_price' => 900.0, 'is_active' => true],
            ['sku' => '155', 'name' => 'توظيب مخرطة راس مكينة', 'unit' => 'Each', 'unit_code' => 'EA', 'unit_price' => 400.0, 'purchase_price' => 400.0, 'is_active' => true],
            ['sku' => '156', 'name' => 'Steel Piller 16cm x 4.20m with paint', 'unit' => 'Each', 'unit_code' => 'EA', 'unit_price' => 500.0, 'purchase_price' => 500.0, 'is_active' => true],
            ['sku' => '157', 'name' => 'Air Conditioner Outer Cover', 'unit' => 'Square Metre', 'unit_code' => 'MTK', 'unit_price' => 300.0, 'purchase_price' => 300.0, 'is_active' => true],
            ['sku' => '158', 'name' => 'قص صاج حديد بليزر 3 ملي سماكة', 'unit' => 'Each', 'unit_code' => 'EA', 'unit_price' => 500.0, 'purchase_price' => 500.0, 'is_active' => true],
            ['sku' => '159', 'name' => 'قص صاج حديد بليزر 5 ملي سماكة', 'unit' => 'Each', 'unit_code' => 'EA', 'unit_price' => 500.0, 'purchase_price' => 500.0, 'is_active' => true],
            ['sku' => '160', 'name' => 'قص مربعه حديد بليزر 80*80', 'unit' => 'Each', 'unit_code' => 'EA', 'unit_price' => 40.0, 'purchase_price' => 40.0, 'is_active' => true],
            ['sku' => '161', 'name' => 'قص فتح مربعه حديد بليزر 80*80', 'unit' => 'Each', 'unit_code' => 'EA', 'unit_price' => 4.0, 'purchase_price' => 4.0, 'is_active' => true],
            ['sku' => '162', 'name' => 'غاويه عمود 6ملي', 'unit' => 'Plate', 'unit_code' => 'EA', 'unit_price' => 600.0, 'purchase_price' => 600.0, 'is_active' => true],
            ['sku' => '163', 'name' => 'قص صاج حديد بليزر 1.5 ملي سماكة 8٭8', 'unit' => 'Each', 'unit_code' => 'EA', 'unit_price' => 1.0, 'purchase_price' => 1.0, 'is_active' => true],
            ['sku' => '164', 'name' => 'بويا فرق صوفه حديد', 'unit' => 'Each', 'unit_code' => 'EA', 'unit_price' => 80.0, 'purchase_price' => 80.0, 'is_active' => true],
            ['sku' => '165', 'name' => 'Flesible Rubber Joint DN400 PN16 Size 30', 'unit' => 'Each', 'unit_code' => 'EA', 'unit_price' => 6020.0, 'purchase_price' => null, 'is_active' => true],
            ['sku' => '166', 'name' => 'قص حديد بليزر', 'unit' => 'Each', 'unit_code' => 'EA', 'unit_price' => 14000.0, 'purchase_price' => null, 'is_active' => true],
            ['sku' => '167', 'name' => 'Maintnence of Chiller Pipe Joints', 'unit' => 'Each', 'unit_code' => 'EA', 'unit_price' => 650.0, 'purchase_price' => 650.0, 'is_active' => true],
            ['sku' => '168', 'name' => 'Panasonic AC Servo Motor 750w', 'unit' => 'Each', 'unit_code' => 'EA', 'unit_price' => 1950.0, 'purchase_price' => 1600.0, 'is_active' => true],
            ['sku' => '169', 'name' => 'DSP Controller Rich Auto A11 Full Pack', 'unit' => 'Each', 'unit_code' => 'EA', 'unit_price' => 2500.0, 'purchase_price' => 2000.0, 'is_active' => true],
            ['sku' => '170', 'name' => 'Fixing Electric Panel Sheet one side only', 'unit' => 'Each', 'unit_code' => 'EA', 'unit_price' => 600.0, 'purchase_price' => 600.0, 'is_active' => true],
            ['sku' => '171', 'name' => 'Supply and manufacturing of Bracket by 1.25mm Galvanized Steel Sheet', 'unit' => 'Each', 'unit_code' => 'EA', 'unit_price' => 38.0, 'purchase_price' => 38.0, 'is_active' => true],
            ['sku' => '172', 'name' => 'Capri Pump - Self Priming & Submersible 7 Impellers 4inch مراوه', 'unit' => 'Each', 'unit_code' => 'EA', 'unit_price' => 5400.0, 'purchase_price' => null, 'is_active' => true],
            ['sku' => '173', 'name' => 'Stainless Steel Pipe Shaft 24mm Caprari عمود', 'unit' => 'Each', 'unit_code' => 'EA', 'unit_price' => 348.0, 'purchase_price' => null, 'is_active' => true],
            ['sku' => '174', 'name' => 'Spider with Rubber Bush Pump 4Inch فلنج', 'unit' => 'Each', 'unit_code' => 'EA', 'unit_price' => 156.0, 'purchase_price' => null, 'is_active' => true],
            ['sku' => '175', 'name' => 'Shaft Socket 24mm جلبه', 'unit' => 'Each', 'unit_code' => 'EA', 'unit_price' => 65.0, 'purchase_price' => null, 'is_active' => true],
            ['sku' => '176', 'name' => 'Steel Pipe 4inch مسوره', 'unit' => 'Each', 'unit_code' => 'EA', 'unit_price' => 150.0, 'purchase_price' => null, 'is_active' => true],
            ['sku' => '177', 'name' => 'Foot Valve with Stainer model P6 4inch شفاط', 'unit' => 'Each', 'unit_code' => 'EA', 'unit_price' => 1320.0, 'purchase_price' => null, 'is_active' => true],
            ['sku' => '178', 'name' => 'head Shaft 22mm SS 420 عمود راس', 'unit' => 'Each', 'unit_code' => 'EA', 'unit_price' => 354.0, 'purchase_price' => null, 'is_active' => true],
            ['sku' => '179', 'name' => 'Head Shaft Balance Nut 24mm ثمولا ميزانيه', 'unit' => 'Each', 'unit_code' => 'EA', 'unit_price' => 114.0, 'purchase_price' => null, 'is_active' => true],
            ['sku' => '180', 'name' => 'Flange Model P6 Mild Steel فلنج راس مسورا', 'unit' => 'Each', 'unit_code' => 'EA', 'unit_price' => 95.0, 'purchase_price' => null, 'is_active' => true],
            ['sku' => '181', 'name' => 'Shaft Socket 24mm صفرا عمود', 'unit' => 'Each', 'unit_code' => 'EA', 'unit_price' => 66.0, 'purchase_price' => null, 'is_active' => true],
            ['sku' => '182', 'name' => 'Supply & Installation of Black Double Glass with 2mm Laminated Sheet', 'unit' => 'Each', 'unit_code' => 'EA', 'unit_price' => 6250.0, 'purchase_price' => null, 'is_active' => true],
            ['sku' => '183', 'name' => 'Remoal of old Black Double Glass with 2mm Laminated sheet', 'unit' => 'Each', 'unit_code' => 'EA', 'unit_price' => 500.0, 'purchase_price' => null, 'is_active' => true],
            ['sku' => '184', 'name' => 'Spider Lock of Glass', 'unit' => 'Each', 'unit_code' => 'EA', 'unit_price' => 240.0, 'purchase_price' => null, 'is_active' => true],
            ['sku' => '185', 'name' => 'Damper Cabinet Unit Plug Fan 40x50x45 Motorise Control 277v-230v', 'unit' => 'Each', 'unit_code' => 'EA', 'unit_price' => 2850.0, 'purchase_price' => null, 'is_active' => true],
            ['sku' => '186', 'name' => 'Laser Cutting Sheet 3mm', 'unit' => 'Each', 'unit_code' => 'EA', 'unit_price' => 500.0, 'purchase_price' => null, 'is_active' => true],
            ['sku' => '187', 'name' => 'Pole Base Outdoor Concrete Saize 40x40x80', 'unit' => 'Each', 'unit_code' => 'EA', 'unit_price' => 120.0, 'purchase_price' => null, 'is_active' => true],
            ['sku' => '188', 'name' => 'Rewinding of Motor', 'unit' => 'Each', 'unit_code' => 'EA', 'unit_price' => 1400.0, 'purchase_price' => null, 'is_active' => true],
            ['sku' => '189', 'name' => 'قص فلنجات حديد بليزر', 'unit' => 'Kilogram', 'unit_code' => 'KGM', 'unit_price' => 6.0, 'purchase_price' => null, 'is_active' => true],
            ['sku' => '190', 'name' => 'Panaflex Roll Up', 'unit' => 'Each', 'unit_code' => 'EA', 'unit_price' => 375.0, 'purchase_price' => null, 'is_active' => true],
            ['sku' => '191', 'name' => 'Sheet Poster', 'unit' => 'Each', 'unit_code' => 'EA', 'unit_price' => 340.0, 'purchase_price' => null, 'is_active' => true],
            ['sku' => '192', 'name' => 'Designing', 'unit' => 'Each', 'unit_code' => 'EA', 'unit_price' => 500.0, 'purchase_price' => null, 'is_active' => true],
            ['sku' => '193', 'name' => 'Febrication Coils size :19cm x 76cm and supply of condenser (1 coil /unit) Tube: OD 1/2" (IGT) WT 0.416mm Fan: Hydrophilic Pre-Coated Aluminum Fins', 'unit' => 'Each', 'unit_code' => 'EA', 'unit_price' => 1210.0, 'purchase_price' => null, 'is_active' => true],
            ['sku' => '194', 'name' => 'Coil Condenser (Each Unit/1 Coil) Size 19cm x 51cm Tube: OD 1/2" (IGT) WT 0.416mm Fins: Hydrophilic Pre-Coated Aluminum Fins WT 0.16mm', 'unit' => 'Each', 'unit_code' => 'EA', 'unit_price' => 1350.0, 'purchase_price' => null, 'is_active' => true],
            ['sku' => '195', 'name' => 'Hande Shower Grohe', 'unit' => 'Each', 'unit_code' => 'EA', 'unit_price' => 80.0, 'purchase_price' => null, 'is_active' => true],
            ['sku' => '196', 'name' => 'Draind Sink Italy Randelly', 'unit' => 'Each', 'unit_code' => 'EA', 'unit_price' => 58.0, 'purchase_price' => null, 'is_active' => true],
            ['sku' => '197', 'name' => 'Drain Extension', 'unit' => 'Each', 'unit_code' => 'EA', 'unit_price' => 12.0, 'purchase_price' => null, 'is_active' => true],
            ['sku' => '198', 'name' => 'Flesh Wall Grohe', 'unit' => 'Each', 'unit_code' => 'EA', 'unit_price' => 530.0, 'purchase_price' => null, 'is_active' => true],
            ['sku' => '199', 'name' => 'Mixer Grohe', 'unit' => 'Each', 'unit_code' => 'EA', 'unit_price' => 220.0, 'purchase_price' => null, 'is_active' => true],
            ['sku' => '200', 'name' => 'Laser Marking Machine Application And Calibration', 'unit' => 'Each', 'unit_code' => 'EA', 'unit_price' => 2000.0, 'purchase_price' => null, 'is_active' => true],
            ['sku' => '201', 'name' => 'Service Charges for Laser Machine', 'unit' => 'Each', 'unit_code' => 'EA', 'unit_price' => 1000.0, 'purchase_price' => null, 'is_active' => true],
            ['sku' => '202', 'name' => 'Ductile Repair coupling 300mm (315-332) كبلين', 'unit' => 'Each', 'unit_code' => 'EA', 'unit_price' => 600.0, 'purchase_price' => null, 'is_active' => true],
            ['sku' => '203', 'name' => 'Ductile Repair coupling 100mm (105-122) كبلين', 'unit' => 'Each', 'unit_code' => 'EA', 'unit_price' => 120.0, 'purchase_price' => null, 'is_active' => true],
            ['sku' => '204', 'name' => 'Laser Resonance Fiber Cable Jointing-Termination', 'unit' => 'Each', 'unit_code' => 'EA', 'unit_price' => 2500.0, 'purchase_price' => null, 'is_active' => true],
            ['sku' => '205', 'name' => 'Machine Service & PC Service with Windows Resetting', 'unit' => 'Each', 'unit_code' => 'EA', 'unit_price' => 600.0, 'purchase_price' => null, 'is_active' => true],
            ['sku' => '206', 'name' => 'Visiting and Delivering Charges', 'unit' => 'Each', 'unit_code' => 'EA', 'unit_price' => 1500.0, 'purchase_price' => null, 'is_active' => true],
            ['sku' => '207', 'name' => 'Co2 Znse Focus Lens USA', 'unit' => 'Each', 'unit_code' => 'EA', 'unit_price' => 450.0, 'purchase_price' => null, 'is_active' => true],
            ['sku' => '208', 'name' => 'Alignment Mirrors 25mm DIA', 'unit' => 'Each', 'unit_code' => 'EA', 'unit_price' => 100.0, 'purchase_price' => null, 'is_active' => true],
            ['sku' => '209', 'name' => 'Co2 Laser Allignment and Calibration Charges', 'unit' => 'Each', 'unit_code' => 'EA', 'unit_price' => 1000.0, 'purchase_price' => null, 'is_active' => true],
            ['sku' => '210', 'name' => 'DN300 Flaxible Joint', 'unit' => 'Each', 'unit_code' => 'EA', 'unit_price' => 3350.0, 'purchase_price' => null, 'is_active' => true],
            ['sku' => '211', 'name' => 'Profile Designing', 'unit' => 'Each', 'unit_code' => 'EA', 'unit_price' => 3500.0, 'purchase_price' => null, 'is_active' => true],
            ['sku' => '212', 'name' => 'Road Maintenence With MC1 & RC2', 'unit' => 'Lump Sum', 'unit_code' => 'EA', 'unit_price' => 10240.0, 'purchase_price' => null, 'is_active' => true],
            ['sku' => '213', 'name' => 'Signboard Installation Size 6.80 x 1.90 Meter', 'unit' => 'Each', 'unit_code' => 'EA', 'unit_price' => 2500.0, 'purchase_price' => null, 'is_active' => true],
            ['sku' => '214', 'name' => 'Engine Water Pump for Doosan Engine D1146T طرمبه مويه', 'unit' => 'Each', 'unit_code' => 'EA', 'unit_price' => 1870.0, 'purchase_price' => null, 'is_active' => true],
            ['sku' => '215', 'name' => 'D.I. Socket Spigot Pipe DN 150mm x L6000mm PN16 ماسوره', 'unit' => 'Each', 'unit_code' => 'EA', 'unit_price' => 1030.0, 'purchase_price' => null, 'is_active' => true],
            ['sku' => '216', 'name' => 'D.I. Socket Spigot Pipe DN 200mm x L6000mm PN16 ماسوره', 'unit' => 'Each', 'unit_code' => 'EA', 'unit_price' => 1350.0, 'purchase_price' => null, 'is_active' => true],
            ['sku' => '217', 'name' => 'D.I. Socket Spigot Pipe DN 300mm x L6000mm PN16 ماسوره', 'unit' => 'Each', 'unit_code' => 'EA', 'unit_price' => 2040.0, 'purchase_price' => null, 'is_active' => true],
            ['sku' => '218', 'name' => 'D.I. Socket Spigot Pipe DN 350mm x L6000mm PN16 ماسوره', 'unit' => 'Each', 'unit_code' => 'EA', 'unit_price' => 2700.0, 'purchase_price' => null, 'is_active' => true],
            ['sku' => '219', 'name' => 'D.I. Socket Spigot Pipe DN 400mm x L6000mm PN16 ماسوره', 'unit' => 'Each', 'unit_code' => 'EA', 'unit_price' => 3120.0, 'purchase_price' => null, 'is_active' => true],
            ['sku' => '220', 'name' => 'صب و خرط قصمة نحاس DN800', 'unit' => 'Each', 'unit_code' => 'EA', 'unit_price' => 3400.0, 'purchase_price' => null, 'is_active' => true],
            ['sku' => '221', 'name' => 'Bearing SKF-7309 BECBP رمان بلي', 'unit' => 'Each', 'unit_code' => 'EA', 'unit_price' => 157.0, 'purchase_price' => null, 'is_active' => true],
            ['sku' => '222', 'name' => 'Bearing SKF-7311 رمان بلي', 'unit' => 'Each', 'unit_code' => 'EA', 'unit_price' => 260.0, 'purchase_price' => null, 'is_active' => true],
            ['sku' => '223', 'name' => 'Bearing SKF-6214 2Z/C3 رمان بلي', 'unit' => 'Each', 'unit_code' => 'EA', 'unit_price' => 190.0, 'purchase_price' => null, 'is_active' => true],
            ['sku' => '224', 'name' => 'Bearing SKF-6311 2Z/C3 رمان بلي', 'unit' => 'Each', 'unit_code' => 'EA', 'unit_price' => 115.0, 'purchase_price' => null, 'is_active' => true],
            ['sku' => '225', 'name' => 'Bearing 6314 2Z/C3 رمان بلي', 'unit' => 'Each', 'unit_code' => 'EA', 'unit_price' => 246.0, 'purchase_price' => null, 'is_active' => true],
            ['sku' => '226', 'name' => 'Bearing SKF-6317 2Z/C3 رمان بلي', 'unit' => 'Each', 'unit_code' => 'EA', 'unit_price' => 512.0, 'purchase_price' => null, 'is_active' => true],
            ['sku' => '227', 'name' => 'Bearing SKF-6306 2Z/C3 رمان بلي', 'unit' => 'Each', 'unit_code' => 'EA', 'unit_price' => 27.0, 'purchase_price' => null, 'is_active' => true],
            ['sku' => '228', 'name' => 'Bearing SKF-3312 A/C3 رمان بلي', 'unit' => 'Each', 'unit_code' => 'EA', 'unit_price' => 625.0, 'purchase_price' => null, 'is_active' => true],
            ['sku' => '229', 'name' => 'Bearing SKF-H313 رمان بلي', 'unit' => 'Each', 'unit_code' => 'EA', 'unit_price' => 85.0, 'purchase_price' => null, 'is_active' => true],
            ['sku' => '230', 'name' => 'Bearing SKF-N318 ECM/C3 رمان بلي', 'unit' => 'Each', 'unit_code' => 'EA', 'unit_price' => 1315.0, 'purchase_price' => null, 'is_active' => true],
            ['sku' => '231', 'name' => 'Bearing SKF-6219 2Z/C3 رمان بلي', 'unit' => 'Each', 'unit_code' => 'EA', 'unit_price' => 500.0, 'purchase_price' => null, 'is_active' => true],
            ['sku' => '232', 'name' => 'Supply and installation of Window AC 2 Ton', 'unit' => 'Each', 'unit_code' => 'EA', 'unit_price' => 2525.0, 'purchase_price' => null, 'is_active' => true],
            ['sku' => '233', 'name' => 'Motor Rewinding 90KW HP-125 RPM-1786 V 380/660 لف موتور', 'unit' => 'Each', 'unit_code' => 'EA', 'unit_price' => 8000.0, 'purchase_price' => null, 'is_active' => true],
            ['sku' => '234', 'name' => 'All Sliding Big doors 6x6 change all wheel down 8 and up side and levelling on both side Jotun or Hempel Paint. All Sliding small door outside wall , change all 6 wheels down side and levelling on both side Oil paint, One Sliding Big Door Outside wall and 2x2 ( as per Purchase Order Mentioned Below)', 'unit' => 'Each', 'unit_code' => 'EA', 'unit_price' => 2928.58, 'purchase_price' => null, 'is_active' => true],
            ['sku' => '235', 'name' => 'Application Warehouse 1 & 2 Supply cable and 2 1 warehouse seprate lighting switch. All lighting checking for both warehouse. Application warehouse 3 & 4 supply cable and 3 1 warehouse seprate lighting switch , All lighting checking for both warehouse. 20 LED inside and 12 LED outside 12 warehouse (as per PO attached)', 'unit' => 'Each', 'unit_code' => 'EA', 'unit_price' => 32400.0, 'purchase_price' => null, 'is_active' => true],
            ['sku' => '236', 'name' => 'Rubber Support Insert -2-1/2" x 1"', 'unit' => 'Each', 'unit_code' => 'EA', 'unit_price' => 12.0, 'purchase_price' => null, 'is_active' => true],
            ['sku' => '237', 'name' => 'U STRAP CLAMP - SUSC126 (2- 1/2"X1")', 'unit' => 'Each', 'unit_code' => 'EA', 'unit_price' => 15.0, 'purchase_price' => null, 'is_active' => true],
            ['sku' => '238', 'name' => 'RUBBER SUPPORT INSERT - 1- 1/2"X1"', 'unit' => 'Each', 'unit_code' => 'EA', 'unit_price' => 6.5, 'purchase_price' => null, 'is_active' => true],
            ['sku' => '239', 'name' => 'U STRAP CLAMP - SUSC108 (1- 1/2"X1")', 'unit' => 'Each', 'unit_code' => 'EA', 'unit_price' => 14.0, 'purchase_price' => null, 'is_active' => true],
            ['sku' => '240', 'name' => 'RUBBER SUPPORT INSERT - 1- 1/4"X1"', 'unit' => 'Each', 'unit_code' => 'EA', 'unit_price' => 6.0, 'purchase_price' => null, 'is_active' => true],
            ['sku' => '241', 'name' => 'U STRAP CLAMP - SUSC140 (1- 1/4"X1")', 'unit' => 'Each', 'unit_code' => 'EA', 'unit_price' => 12.0, 'purchase_price' => null, 'is_active' => true],
            ['sku' => '242', 'name' => 'RUBBER SUPPORT INSERT - 1"X1"', 'unit' => 'Each', 'unit_code' => 'EA', 'unit_price' => 5.5, 'purchase_price' => null, 'is_active' => true],
            ['sku' => '243', 'name' => 'U STRAP CLAMP - SUSC86 (1"X1")', 'unit' => 'Each', 'unit_code' => 'EA', 'unit_price' => 11.0, 'purchase_price' => null, 'is_active' => true],
            ['sku' => '244', 'name' => 'RUBBER SUPPORT INSERT - 3/4"X1"', 'unit' => 'Each', 'unit_code' => 'EA', 'unit_price' => 5.0, 'purchase_price' => null, 'is_active' => true],
            ['sku' => '245', 'name' => 'U STRAP CLAMP - SUSC82 (3/4"X1")', 'unit' => 'Each', 'unit_code' => 'EA', 'unit_price' => 10.0, 'purchase_price' => null, 'is_active' => true],
            ['sku' => '246', 'name' => 'FIBERGLASS PIPE INSULATION 3" X 2" (9PCS/BOX)', 'unit' => 'Each', 'unit_code' => 'EA', 'unit_price' => 33.0, 'purchase_price' => null, 'is_active' => true],
            ['sku' => '247', 'name' => 'FIBERGLASS PIPE INSULATION 2- 1/2" X 2" (9PCS/BOX)', 'unit' => 'Each', 'unit_code' => 'EA', 'unit_price' => 30.0, 'purchase_price' => null, 'is_active' => true],
            ['sku' => '248', 'name' => 'FIBERGLASS PIPE INSULATION 1- 1/2" X 2" (13PCS/BOX)', 'unit' => 'Each', 'unit_code' => 'EA', 'unit_price' => 24.0, 'purchase_price' => null, 'is_active' => true],
            ['sku' => '249', 'name' => 'FIBERGLASS PIPE INSULATION 1- 1/4" X 1" (36PCS/BOX)', 'unit' => 'Each', 'unit_code' => 'EA', 'unit_price' => 13.0, 'purchase_price' => null, 'is_active' => true],
            ['sku' => '250', 'name' => 'FIBERGLASS PIPE INSULATION 1" X 1" (42PCS/BOX)', 'unit' => 'Each', 'unit_code' => 'EA', 'unit_price' => 12.0, 'purchase_price' => null, 'is_active' => true],
            ['sku' => '251', 'name' => 'FIBERGLASS PIPE INSULATION 3/4" X 1" (49PCS/BOX)', 'unit' => 'Each', 'unit_code' => 'EA', 'unit_price' => 11.0, 'purchase_price' => null, 'is_active' => true],
            ['sku' => '252', 'name' => 'TAPE Q FSK 3 INCH (16 PCS / BOX)', 'unit' => 'Each', 'unit_code' => 'EA', 'unit_price' => 15.0, 'purchase_price' => null, 'is_active' => true],
            ['sku' => '253', 'name' => 'STAR CANVAS COATING 30-60 (24 KG)', 'unit' => 'Each', 'unit_code' => 'EA', 'unit_price' => 125.0, 'purchase_price' => null, 'is_active' => true],
            ['sku' => '254', 'name' => 'COTTON CLOTH 6 OZ - 20 YARDS', 'unit' => 'Each', 'unit_code' => 'EA', 'unit_price' => 30.0, 'purchase_price' => null, 'is_active' => true],
            ['sku' => '255', 'name' => 'THINNER MEGAMAR- 1 GALLON', 'unit' => 'Each', 'unit_code' => 'EA', 'unit_price' => 30.0, 'purchase_price' => null, 'is_active' => true],
            ['sku' => '256', 'name' => 'PAINT BRUSH 2"', 'unit' => 'Each', 'unit_code' => 'EA', 'unit_price' => 5.0, 'purchase_price' => null, 'is_active' => true],
            ['sku' => '257', 'name' => 'COTTON GLOVES', 'unit' => 'Each', 'unit_code' => 'EA', 'unit_price' => 10.0, 'purchase_price' => null, 'is_active' => true],
            ['sku' => '258', 'name' => 'تصليع مكينة لحام', 'unit' => 'Each', 'unit_code' => 'EA', 'unit_price' => 165.0, 'purchase_price' => null, 'is_active' => true],
            ['sku' => '259', 'name' => 'Mechanical Seal Set/10320 Part 433.01 Upar ID-01101326 Matrial-Q22Q22VGG1', 'unit' => 'Each', 'unit_code' => 'EA', 'unit_price' => 32550.0, 'purchase_price' => null, 'is_active' => true],
            ['sku' => '260', 'name' => 'Mechanical Seal Set/10320 Part 433.02 Lower ID-01101326 Matrial-Q22Q22VGG1', 'unit' => 'Each', 'unit_code' => 'EA', 'unit_price' => 32550.0, 'purchase_price' => null, 'is_active' => true],
            ['sku' => '261', 'name' => 'Pump Model-Sewatec K 400-820 Serial 9970910086/000100', 'unit' => 'Each', 'unit_code' => 'EA', 'unit_price' => 0.0, 'purchase_price' => null, 'is_active' => true],
            ['sku' => '262', 'name' => 'Oil Seal Set Size 160-190-15A SC-150-180-15', 'unit' => 'Each', 'unit_code' => 'EA', 'unit_price' => 350.0, 'purchase_price' => null, 'is_active' => true],
            ['sku' => '263', 'name' => 'Oring Set', 'unit' => 'Each', 'unit_code' => 'EA', 'unit_price' => 250.0, 'purchase_price' => null, 'is_active' => true],
            ['sku' => '264', 'name' => '1. Supply and installation of sinko Sheet with Thermal for accomodation 2. Warehouse Shink sheet 3.Installation of Water Pump 4. Main Breaker 400amp with ATS Panel 5. Breaker 100amp With ATS Panel 6. 6mm Cable 5 Roll Exchange all Cable.', 'unit' => 'Lump Sum', 'unit_code' => 'EA', 'unit_price' => 37425.0, 'purchase_price' => null, 'is_active' => true],
            ['sku' => '265', 'name' => 'Service Box 6" سيرفس بوكس', 'unit' => 'Each', 'unit_code' => 'EA', 'unit_price' => 125.0, 'purchase_price' => null, 'is_active' => true],
            ['sku' => '266', 'name' => 'Bush Shaft', 'unit' => 'Each', 'unit_code' => 'EA', 'unit_price' => 150.0, 'purchase_price' => null, 'is_active' => true],
            ['sku' => '267', 'name' => 'Bearing', 'unit' => 'Each', 'unit_code' => 'EA', 'unit_price' => 200.0, 'purchase_price' => null, 'is_active' => true],
            ['sku' => '268', 'name' => 'Bearing Plastic Cover', 'unit' => 'Each', 'unit_code' => 'EA', 'unit_price' => 180.0, 'purchase_price' => null, 'is_active' => true],
            ['sku' => '269', 'name' => 'Feather ريشه', 'unit' => 'Each', 'unit_code' => 'EA', 'unit_price' => 550.0, 'purchase_price' => null, 'is_active' => true],
            ['sku' => '270', 'name' => 'Bearing Foundation', 'unit' => 'Each', 'unit_code' => 'EA', 'unit_price' => 400.0, 'purchase_price' => null, 'is_active' => true],
            ['sku' => '271', 'name' => 'Paint', 'unit' => 'Each', 'unit_code' => 'EA', 'unit_price' => 200.0, 'purchase_price' => null, 'is_active' => true],
            ['sku' => '272', 'name' => 'Balance', 'unit' => 'Each', 'unit_code' => 'EA', 'unit_price' => 300.0, 'purchase_price' => null, 'is_active' => true],
            ['sku' => '273', 'name' => 'AHU Unit Blower , Removal of AHU New York Exat Blower Damage Exat Supports Plates and internal pully with impeller Supply & install new Sides with pully and Impeller', 'unit' => 'Lump Sum', 'unit_code' => 'EA', 'unit_price' => 4540.0, 'purchase_price' => null, 'is_active' => true],
            ['sku' => '274', 'name' => 'Rewinding of Motor 15HP with Bearing', 'unit' => 'Each', 'unit_code' => 'EA', 'unit_price' => 850.0, 'purchase_price' => null, 'is_active' => true],
            ['sku' => '275', 'name' => 'Rewinding Motor 5HP with Bearing and Lath work', 'unit' => 'Each', 'unit_code' => 'EA', 'unit_price' => 550.0, 'purchase_price' => null, 'is_active' => true],
            ['sku' => '276', 'name' => 'Steel Store Cabinet. Size 2.65 Meter x 9 Meter with Aluminum Sliver Sliding Door 2mm thick by Al Rajhi Aluminum With Italian Accessories', 'unit' => 'Metre', 'unit_code' => 'MTR', 'unit_price' => 1230.0, 'purchase_price' => null, 'is_active' => true],
            ['sku' => '277', 'name' => 'Bearing SKF 7319 BECPM رمان بلي', 'unit' => 'Each', 'unit_code' => 'EA', 'unit_price' => 1690.0, 'purchase_price' => null, 'is_active' => true],
            ['sku' => '278', 'name' => 'Bearing SKF-NU 219 ECP رمان بلي', 'unit' => 'Each', 'unit_code' => 'EA', 'unit_price' => 810.0, 'purchase_price' => null, 'is_active' => true],
            ['sku' => '279', 'name' => 'Bearing SKF-NU 313 ECP رمان بلي', 'unit' => 'Each', 'unit_code' => 'EA', 'unit_price' => 500.0, 'purchase_price' => null, 'is_active' => true],
            ['sku' => '280', 'name' => 'Mechanical Seal Lower Set (FLYGT) 7261800', 'unit' => 'Each', 'unit_code' => 'EA', 'unit_price' => 11550.0, 'purchase_price' => null, 'is_active' => true],
            ['sku' => '281', 'name' => 'Mechanical Seal Upper Set (FLYGT) 6179902', 'unit' => 'Each', 'unit_code' => 'EA', 'unit_price' => 11550.0, 'purchase_price' => null, 'is_active' => true],
            ['sku' => '282', 'name' => 'Three Phase Submersible Pump Motor 1.00 Nos 14500.00 500.00 14000.00 Flygt Make Sr# 0665.000-1621106 - 104 KW 140 HP 380v 213 Amps 1185 RPM 0.80 PF Make Only Rewinding & Replace Gaskit Oring and Fitting. (Mechanical Seal and Bearing client Provides)', 'unit' => 'Each', 'unit_code' => 'EA', 'unit_price' => 11500.0, 'purchase_price' => null, 'is_active' => true],
            ['sku' => '283', 'name' => 'Three Phase Autotransformer Power Kva 500 Model 03/2020 (ELCA Made in Itlay) (USED) Protection IP23 Power 500KVA Input 220v , 380v , 400v, 480v, Output 220v, 380v, 400v, 480v.', 'unit' => 'Each', 'unit_code' => 'EA', 'unit_price' => 33500.0, 'purchase_price' => null, 'is_active' => true],
            ['sku' => '284', 'name' => 'Block Bearings EG15', 'unit' => 'Each', 'unit_code' => 'EA', 'unit_price' => 200.0, 'purchase_price' => null, 'is_active' => true],
            ['sku' => '285', 'name' => 'Checking , Installation of all parts, Service of machine and calibration after installing parts (complete Service)', 'unit' => 'Each', 'unit_code' => 'EA', 'unit_price' => 4000.0, 'purchase_price' => null, 'is_active' => true],
            ['sku' => '286', 'name' => 'Shaft Bearing Size', 'unit' => 'Each', 'unit_code' => 'EA', 'unit_price' => 450.0, 'purchase_price' => null, 'is_active' => true],
            ['sku' => '287', 'name' => 'Double Layer Bed 5, Matters 10, Pillow 10, BedSheet 10, Blankets 10, Carpet 2m x 1m 2, Transportation 1', 'unit' => 'Lump Sum', 'unit_code' => 'EA', 'unit_price' => 6450.0, 'purchase_price' => null, 'is_active' => true],
            ['sku' => '288', 'name' => 'Co2 Chiller Repair and Maintenence', 'unit' => 'Each', 'unit_code' => 'EA', 'unit_price' => 800.0, 'purchase_price' => null, 'is_active' => true],
            ['sku' => '289', 'name' => 'Rewinding Submersible Pump 63kw', 'unit' => 'Each', 'unit_code' => 'EA', 'unit_price' => 11500.0, 'purchase_price' => 11500.0, 'is_active' => true],
            ['sku' => '290', 'name' => 'Lathe Work for Water Pump', 'unit' => 'Each', 'unit_code' => 'EA', 'unit_price' => 200.0, 'purchase_price' => null, 'is_active' => true],
            ['sku' => '291', 'name' => 'مخرط عمود ميزانية', 'unit' => 'Each', 'unit_code' => 'EA', 'unit_price' => 150.0, 'purchase_price' => null, 'is_active' => true],
            ['sku' => '292', 'name' => 'توظيب مخرطة لمضخة غاطسة 10 مراحل', 'unit' => 'Each', 'unit_code' => 'EA', 'unit_price' => 200.0, 'purchase_price' => null, 'is_active' => true],
            ['sku' => '293', 'name' => 'خرم 55ملي تبه', 'unit' => 'Each', 'unit_code' => 'EA', 'unit_price' => 55.0, 'purchase_price' => null, 'is_active' => true],
            ['sku' => '294', 'name' => 'توظيب مخرط جيربكس', 'unit' => 'Each', 'unit_code' => 'EA', 'unit_price' => 391.0, 'purchase_price' => null, 'is_active' => true],
            ['sku' => '295', 'name' => 'Lath Work & Welding Steel Rod , Plate and Bearing with Bush and Balance Work', 'unit' => 'Each', 'unit_code' => 'EA', 'unit_price' => 550.0, 'purchase_price' => null, 'is_active' => true],
            ['sku' => '296', 'name' => 'Packing Nut', 'unit' => 'Each', 'unit_code' => 'EA', 'unit_price' => 5130.0, 'purchase_price' => null, 'is_active' => true],
            ['sku' => '297', 'name' => 'Bearing SKF 210J 51215', 'unit' => 'Each', 'unit_code' => 'EA', 'unit_price' => 459.0, 'purchase_price' => null, 'is_active' => true],
            ['sku' => '298', 'name' => 'Impeller Flygt Serial # (3301.180-S1570005)', 'unit' => 'Each', 'unit_code' => 'EA', 'unit_price' => 34425.0, 'purchase_price' => null, 'is_active' => true],
            ['sku' => '299', 'name' => 'Exit Door Button With 7mm Box from ZK Brand', 'unit' => 'Each', 'unit_code' => 'EA', 'unit_price' => 100.0, 'purchase_price' => null, 'is_active' => true],
            ['sku' => '300', 'name' => 'Door Access Control System with Fingerprint + Card + Password ZK', 'unit' => 'Each', 'unit_code' => 'EA', 'unit_price' => 970.0, 'purchase_price' => null, 'is_active' => true],
            ['sku' => '301', 'name' => 'Magnet Lock 600 Pound Power & Wooden Door Bracket from ZK Brand', 'unit' => 'Each', 'unit_code' => 'EA', 'unit_price' => 330.0, 'purchase_price' => null, 'is_active' => true],
            ['sku' => '302', 'name' => 'Remote key with Exit Sensor incl. 12v DC Adopter ZK Brand', 'unit' => 'Each', 'unit_code' => 'EA', 'unit_price' => 180.0, 'purchase_price' => null, 'is_active' => true],
            ['sku' => '303', 'name' => '377v to 220 Power Convertor', 'unit' => 'Each', 'unit_code' => 'EA', 'unit_price' => 480.0, 'purchase_price' => null, 'is_active' => true],
            ['sku' => '304', 'name' => 'Installation & Programming Charge', 'unit' => 'Each', 'unit_code' => 'EA', 'unit_price' => 700.0, 'purchase_price' => null, 'is_active' => true],
            ['sku' => '305', 'name' => '4mm Core Wire', 'unit' => 'Roll', 'unit_code' => 'EA', 'unit_price' => 350.0, 'purchase_price' => null, 'is_active' => true],
            ['sku' => '306', 'name' => 'Old Router Remoter Set Replacement', 'unit' => 'Each', 'unit_code' => 'EA', 'unit_price' => 400.0, 'purchase_price' => null, 'is_active' => true],
            ['sku' => '307', 'name' => 'Old Router Remote Button Replacement and setting', 'unit' => 'Each', 'unit_code' => 'EA', 'unit_price' => 400.0, 'purchase_price' => null, 'is_active' => true],
            ['sku' => '308', 'name' => 'CNC Router Mainanence with Remote and changing Control', 'unit' => 'Each', 'unit_code' => 'EA', 'unit_price' => 3000.0, 'purchase_price' => null, 'is_active' => true],
            ['sku' => '309', 'name' => 'حملة تسويقية عبر الرسائل النصية القصيرة لمجتمعات محددة في منطقة آسيوية', 'unit' => 'Each', 'unit_code' => 'EA', 'unit_price' => 0.13, 'purchase_price' => null, 'is_active' => true],
            ['sku' => '310', 'name' => 'توضيب عمود ترس قير جونسون', 'unit' => 'Each', 'unit_code' => 'EA', 'unit_price' => 250.0, 'purchase_price' => null, 'is_active' => true],
            ['sku' => '311', 'name' => 'اعمال اسفلت طبقه ثانيه', 'unit' => 'Square Metre', 'unit_code' => 'MTK', 'unit_price' => 21.0, 'purchase_price' => null, 'is_active' => true],
            ['sku' => '312', 'name' => 'Motor 460v Rewinding 1700 RPM', 'unit' => 'Each', 'unit_code' => 'EA', 'unit_price' => 550.0, 'purchase_price' => null, 'is_active' => true],
            ['sku' => '313', 'name' => 'Motor 127 Rewinding and bearing', 'unit' => 'Each', 'unit_code' => 'EA', 'unit_price' => 350.0, 'purchase_price' => null, 'is_active' => true],
            ['sku' => '314', 'name' => 'Motor 227 Rewinding', 'unit' => 'Each', 'unit_code' => 'EA', 'unit_price' => 300.0, 'purchase_price' => null, 'is_active' => true],
            ['sku' => '315', 'name' => 'New Shaft', 'unit' => 'Each', 'unit_code' => 'EA', 'unit_price' => 1500.0, 'purchase_price' => null, 'is_active' => true],
            ['sku' => '316', 'name' => 'Bearing Change', 'unit' => 'Each', 'unit_code' => 'EA', 'unit_price' => 1000.0, 'purchase_price' => null, 'is_active' => true],
            ['sku' => '317', 'name' => 'Pipe Threding Die SuperGo 1" سوبر ايغو', 'unit' => 'Each', 'unit_code' => 'EA', 'unit_price' => 210.0, 'purchase_price' => null, 'is_active' => true],
            ['sku' => '318', 'name' => 'Pipe Threding Die SuperGo 3/4 سوبر ايضو', 'unit' => 'Each', 'unit_code' => 'EA', 'unit_price' => 180.0, 'purchase_price' => null, 'is_active' => true],
            ['sku' => '319', 'name' => 'Pipe Threding Die SuperGo 1/2 سوبر ايغو', 'unit' => 'Each', 'unit_code' => 'EA', 'unit_price' => 170.0, 'purchase_price' => null, 'is_active' => true],
            ['sku' => '320', 'name' => '2 Meter Umbrella with Stand مظلات', 'unit' => 'Each', 'unit_code' => 'EA', 'unit_price' => 240.0, 'purchase_price' => null, 'is_active' => true],
            ['sku' => '321', 'name' => 'SKF Bearing', 'unit' => 'Each', 'unit_code' => 'EA', 'unit_price' => 300.0, 'purchase_price' => null, 'is_active' => true],
            ['sku' => '322', 'name' => 'Welding Shaft', 'unit' => 'Each', 'unit_code' => 'EA', 'unit_price' => 100.0, 'purchase_price' => null, 'is_active' => true],
            ['sku' => '323', 'name' => 'Blower Side Plat', 'unit' => 'Each', 'unit_code' => 'EA', 'unit_price' => 200.0, 'purchase_price' => null, 'is_active' => true],
            ['sku' => '324', 'name' => 'Blower Side Plate', 'unit' => 'Each', 'unit_code' => 'EA', 'unit_price' => 200.0, 'purchase_price' => null, 'is_active' => true],
            ['sku' => '325', 'name' => 'Shaft Bearing Size', 'unit' => 'Each', 'unit_code' => 'EA', 'unit_price' => 400.0, 'purchase_price' => null, 'is_active' => true],
            ['sku' => '326', 'name' => 'Bearing Foundation', 'unit' => 'Each', 'unit_code' => 'EA', 'unit_price' => 350.0, 'purchase_price' => null, 'is_active' => true],
            ['sku' => '327', 'name' => 'Paint', 'unit' => 'Each', 'unit_code' => 'EA', 'unit_price' => 150.0, 'purchase_price' => null, 'is_active' => true],
            ['sku' => '328', 'name' => 'Balancing', 'unit' => 'Each', 'unit_code' => 'EA', 'unit_price' => 300.0, 'purchase_price' => null, 'is_active' => true],
            ['sku' => '329', 'name' => 'SS Repair Clamp With Rubber - 700 MM قفيص', 'unit' => 'Each', 'unit_code' => 'EA', 'unit_price' => 3800.0, 'purchase_price' => null, 'is_active' => true],
            ['sku' => '330', 'name' => 'SS Repair Clamp With Rubber - 500MM قفيص', 'unit' => 'Each', 'unit_code' => 'EA', 'unit_price' => 3000.0, 'purchase_price' => null, 'is_active' => true],
            ['sku' => '331', 'name' => 'اصلاح بلاض', 'unit' => 'Each', 'unit_code' => 'EA', 'unit_price' => 700.0, 'purchase_price' => null, 'is_active' => true],
            ['sku' => '332', 'name' => 'خراطة عمود ميزانية ٣٢ ملي', 'unit' => 'Each', 'unit_code' => 'EA', 'unit_price' => 250.0, 'purchase_price' => null, 'is_active' => true],
            ['sku' => '333', 'name' => 'خراطة صامولة ميزانية', 'unit' => 'Each', 'unit_code' => 'EA', 'unit_price' => 200.0, 'purchase_price' => null, 'is_active' => true],
            ['sku' => '334', 'name' => 'غراطة فلنشة قير جونسون', 'unit' => 'Each', 'unit_code' => 'EA', 'unit_price' => 300.0, 'purchase_price' => null, 'is_active' => true],
            ['sku' => '335', 'name' => 'خراطة وتوضيب عمود ترس', 'unit' => 'Each', 'unit_code' => 'EA', 'unit_price' => 400.0, 'purchase_price' => null, 'is_active' => true],
            ['sku' => '336', 'name' => 'خراطة وتوضيب قير ماكينة افيكو', 'unit' => 'Each', 'unit_code' => 'EA', 'unit_price' => 500.0, 'purchase_price' => null, 'is_active' => true],
            ['sku' => '337', 'name' => 'Supply & change Bearing (SKF)', 'unit' => 'Each', 'unit_code' => 'EA', 'unit_price' => 300.0, 'purchase_price' => null, 'is_active' => true],
            ['sku' => '338', 'name' => 'Lathing & welding for pump cylinder', 'unit' => 'Each', 'unit_code' => 'EA', 'unit_price' => 300.0, 'purchase_price' => null, 'is_active' => true],
            ['sku' => '339', 'name' => 'Supply & Installation of SS Shaft for pump', 'unit' => 'Each', 'unit_code' => 'EA', 'unit_price' => 1700.0, 'purchase_price' => null, 'is_active' => true],
            ['sku' => '340', 'name' => 'خراطة جلبة فلنشة وخراطة مسامير عمو؏ الدوران', 'unit' => 'Each', 'unit_code' => 'EA', 'unit_price' => 250.0, 'purchase_price' => null, 'is_active' => true],
            ['sku' => '341', 'name' => 'Repair of KSB pump / maintenance', 'unit' => 'Each', 'unit_code' => 'EA', 'unit_price' => 5200.0, 'purchase_price' => null, 'is_active' => true],
            ['sku' => '342', 'name' => 'Pully ,press 200mm', 'unit' => 'Each', 'unit_code' => 'EA', 'unit_price' => 500.0, 'purchase_price' => null, 'is_active' => true],
            ['sku' => '343', 'name' => 'Pully ,press 300mm', 'unit' => 'Each', 'unit_code' => 'EA', 'unit_price' => 700.0, 'purchase_price' => null, 'is_active' => true],
            ['sku' => '344', 'name' => 'Replace rediator mesh for generator', 'unit' => 'Each', 'unit_code' => 'EA', 'unit_price' => 9000.0, 'purchase_price' => null, 'is_active' => true],
            ['sku' => '345', 'name' => 'XLPE / PVC 1*25 mm2 yellow /green Earth Connection كيبل نحاس', 'unit' => 'Metre', 'unit_code' => 'MTR', 'unit_price' => 19.0, 'purchase_price' => null, 'is_active' => true],
            ['sku' => '346', 'name' => 'XLPE / PVC 4*35 mm2 not Armed كيبل نحاس', 'unit' => 'Metre', 'unit_code' => 'MTR', 'unit_price' => 90.0, 'purchase_price' => null, 'is_active' => true],
            ['sku' => '347', 'name' => 'لحام قير كبراري 3 بوصة لحام ظهر', 'unit' => 'Each', 'unit_code' => 'EA', 'unit_price' => 200.0, 'purchase_price' => null, 'is_active' => true],
            ['sku' => '348', 'name' => 'توضيب وتركيب فلنشة عمود كردان جونسون', 'unit' => 'Each', 'unit_code' => 'EA', 'unit_price' => 150.0, 'purchase_price' => null, 'is_active' => true],
            ['sku' => '349', 'name' => 'توضيب عدد 2 ترس قير مضخة جونسون', 'unit' => 'Each', 'unit_code' => 'EA', 'unit_price' => 400.0, 'purchase_price' => null, 'is_active' => true],
            ['sku' => '350', 'name' => 'توضيب مضخة ٣ بوصة', 'unit' => 'Each', 'unit_code' => 'EA', 'unit_price' => 400.0, 'purchase_price' => null, 'is_active' => true],
            ['sku' => '351', 'name' => 'خراطة وتوضيب ٦ مراوح مضخة', 'unit' => 'Each', 'unit_code' => 'EA', 'unit_price' => 200.0, 'purchase_price' => null, 'is_active' => true],
            ['sku' => '352', 'name' => 'توضيب عمود مضخة ٣ بوصة', 'unit' => 'Each', 'unit_code' => 'EA', 'unit_price' => 300.0, 'purchase_price' => null, 'is_active' => true],
            ['sku' => '353', 'name' => 'خراطة وصيانة عدد 4 فلنشة ماسورية', 'unit' => 'Each', 'unit_code' => 'EA', 'unit_price' => 100.0, 'purchase_price' => null, 'is_active' => true],
            ['sku' => '354', 'name' => 'توضيب وخراطة مضخة 4', 'unit' => 'Each', 'unit_code' => 'EA', 'unit_price' => 500.0, 'purchase_price' => null, 'is_active' => true],
            ['sku' => '355', 'name' => 'خراطة عمود ميزانية', 'unit' => 'Each', 'unit_code' => 'EA', 'unit_price' => 450.0, 'purchase_price' => null, 'is_active' => true],
            ['sku' => '356', 'name' => 'توضيب عمود مع تركيب ٢ صليبة', 'unit' => 'Each', 'unit_code' => 'EA', 'unit_price' => 350.0, 'purchase_price' => null, 'is_active' => true],
            ['sku' => '357', 'name' => 'خراطة فلنشة', 'unit' => 'Each', 'unit_code' => 'EA', 'unit_price' => 250.0, 'purchase_price' => null, 'is_active' => true],
            ['sku' => '358', 'name' => 'خراطة وتركيب فلنشة عمود وتركيب بلي', 'unit' => 'Each', 'unit_code' => 'EA', 'unit_price' => 150.0, 'purchase_price' => null, 'is_active' => true],
            ['sku' => '359', 'name' => 'Motor Fan Aluminum 58mm', 'unit' => 'Each', 'unit_code' => 'EA', 'unit_price' => 650.0, 'purchase_price' => null, 'is_active' => true],
            ['sku' => '360', 'name' => '400W/220V LED STREET LIGHT FIXTURE ضوء الشارع', 'unit' => 'Each', 'unit_code' => 'EA', 'unit_price' => 300.0, 'purchase_price' => null, 'is_active' => true],
            ['sku' => '361', 'name' => '4X10MM CU/PVC FLEXIBLE CABLE WHITE الكابل', 'unit' => 'Each', 'unit_code' => 'EA', 'unit_price' => 1210.0, 'purchase_price' => null, 'is_active' => true],
            ['sku' => '362', 'name' => '3X1.5MM CU/PVC FLEXIBLE CABLE WHITE الكابل', 'unit' => 'Each', 'unit_code' => 'EA', 'unit_price' => 185.0, 'purchase_price' => null, 'is_active' => true],
            ['sku' => '363', 'name' => 'ENCLOSURE 250mm X 200mm X 150mm صندوق مغلق ارتفاع', 'unit' => 'Each', 'unit_code' => 'EA', 'unit_price' => 80.0, 'purchase_price' => null, 'is_active' => true],
            ['sku' => '364', 'name' => '3 POLE/100AMP LIGHTING PANEL MANUAL + TIMER PHOTOCELL MAKE:SCHNEIDER لوحة إضاءة', 'unit' => 'Each', 'unit_code' => 'EA', 'unit_price' => 2504.0, 'purchase_price' => null, 'is_active' => true],
            ['sku' => '365', 'name' => 'Flurescent Tube TL-D 36W/54-765 (120cm)', 'unit' => 'Each', 'unit_code' => 'EA', 'unit_price' => 36.0, 'purchase_price' => null, 'is_active' => true],
            ['sku' => '366', 'name' => 'Flurescent Tube TL-D 18W/840 (59cm)', 'unit' => 'Each', 'unit_code' => 'EA', 'unit_price' => 22.0, 'purchase_price' => null, 'is_active' => true],
            ['sku' => '367', 'name' => 'Exit light LED', 'unit' => 'Each', 'unit_code' => 'EA', 'unit_price' => 55.0, 'purchase_price' => null, 'is_active' => true],
            ['sku' => '368', 'name' => 'LED 60w(60 x 60)cm', 'unit' => 'Each', 'unit_code' => 'EA', 'unit_price' => 55.0, 'purchase_price' => null, 'is_active' => true],
            ['sku' => '369', 'name' => 'OSRAM POWER STAR HQI-BT 400W/D PRO DAY LIGHT', 'unit' => 'Each', 'unit_code' => 'EA', 'unit_price' => 65.0, 'purchase_price' => null, 'is_active' => true],
            ['sku' => '370', 'name' => 'BALLAST 400W, HIS- SAFI', 'unit' => 'Each', 'unit_code' => 'EA', 'unit_price' => 45.0, 'purchase_price' => null, 'is_active' => true],
            ['sku' => '371', 'name' => 'IGNITOR 400W', 'unit' => 'Each', 'unit_code' => 'EA', 'unit_price' => 45.0, 'purchase_price' => null, 'is_active' => true],
            ['sku' => '372', 'name' => 'capacitor 25µfcapacitor 25µf', 'unit' => 'Each', 'unit_code' => 'EA', 'unit_price' => 35.0, 'purchase_price' => null, 'is_active' => true],
            ['sku' => '373', 'name' => 'capacitor 40µf', 'unit' => 'Each', 'unit_code' => 'EA', 'unit_price' => 40.0, 'purchase_price' => null, 'is_active' => true],
            ['sku' => '374', 'name' => 'OSRAM VIALOX NAV-TS 250W', 'unit' => 'Each', 'unit_code' => 'EA', 'unit_price' => 190.0, 'purchase_price' => null, 'is_active' => true],
            ['sku' => '375', 'name' => 'Ballast 250W HIS - SAPI 25/22/6', 'unit' => 'Each', 'unit_code' => 'EA', 'unit_price' => 85.0, 'purchase_price' => null, 'is_active' => true],
            ['sku' => '376', 'name' => 'OSRAM POWER STARHQI-TS 250W/WDL', 'unit' => 'Each', 'unit_code' => 'EA', 'unit_price' => 109.0, 'purchase_price' => null, 'is_active' => true],
            ['sku' => '377', 'name' => 'OSRAM POWER STAR70W HQI-E BULB', 'unit' => 'Each', 'unit_code' => 'EA', 'unit_price' => 75.0, 'purchase_price' => null, 'is_active' => true],
            ['sku' => '378', 'name' => '70WATTS IGNITOR', 'unit' => 'Each', 'unit_code' => 'EA', 'unit_price' => 40.0, 'purchase_price' => null, 'is_active' => true],
            ['sku' => '379', 'name' => '70WATTS BALLAST', 'unit' => 'Each', 'unit_code' => 'EA', 'unit_price' => 45.0, 'purchase_price' => null, 'is_active' => true],
            ['sku' => '380', 'name' => '70 WATTS CAPACITOR 16µf', 'unit' => 'Each', 'unit_code' => 'EA', 'unit_price' => 40.0, 'purchase_price' => null, 'is_active' => true],
            ['sku' => '381', 'name' => 'OSRAM POWER STAR HQI-E 400W/D PRO DAY LIGHT', 'unit' => 'Each', 'unit_code' => 'EA', 'unit_price' => 65.0, 'purchase_price' => null, 'is_active' => true],
            ['sku' => '382', 'name' => 'Schneider Galaxy 5000 (UPS calibration/Service 100KVA)', 'unit' => 'Each', 'unit_code' => 'EA', 'unit_price' => 5000.0, 'purchase_price' => null, 'is_active' => true],
        ];

        foreach ($items as $data) {
            $data = $this->normalizeLongName($data);

            Item::updateOrCreate(
                ['company_id' => $company->id, 'sku' => $data['sku']],
                $data + [
                    'company_id' => $company->id,
                    'item_type' => 'service',
                    'track_inventory' => false,
                    'vat_rate' => 15,
                ]
            );
        }

        $this->command?->info(count($items).' Zubaidi products/services seeded.');
    }
}
