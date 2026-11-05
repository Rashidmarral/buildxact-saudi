<?php

namespace App\Console\Commands;

use App\Models\Company;
use App\Models\CompanyLetter;
use App\Support\LetterPresets;
use Illuminate\Console\Command;

/**
 * One-command way to generate a sample letter for every Letters &
 * Agreements preset kind, against a real company's own data, so its
 * owner can download each PDF and check the formatting before relying
 * on the feature for real documents.
 *
 * Safe to run against a live database: every letter it creates is
 * tagged with a "(Demo) " title prefix, so they're easy to find in the
 * Letters list and delete individually (or remove them all at once with
 * --fresh, which deletes only letters carrying that same prefix for the
 * matched company before re-seeding — nothing else is ever touched).
 */
class SeedMachineryDemoLetters extends Command
{
    private const PREFIX = '(Demo) ';

    protected $signature = 'demo:machinery-letters
                            {--company=Dynamic Core : Part of the company name to search for}
                            {--fresh : Delete this command\'s previously-seeded demo letters for the matched company first}';

    protected $description = 'Seed one sample letter per Letters & Agreements preset (sale, purchase, rental out, hire-in, handover) for a real company, to check the downloaded PDF';

    public function handle(): int
    {
        $search = $this->option('company');
        $companies = Company::where('name', 'like', "%{$search}%")->get();

        if ($companies->isEmpty()) {
            $this->error("No company found matching \"{$search}\".");

            return self::FAILURE;
        }

        if ($companies->count() > 1) {
            $this->error("More than one company matches \"{$search}\" — be more specific with --company:");
            $companies->each(fn (Company $c) => $this->line("  #{$c->id}: {$c->name}"));

            return self::FAILURE;
        }

        $company = $companies->first();

        if ($this->option('fresh')) {
            $deleted = CompanyLetter::where('company_id', $company->id)
                ->where('title', 'like', self::PREFIX.'%')
                ->delete();
            $this->info("Removed {$deleted} previously-seeded demo letter(s).");
        }

        foreach ($this->demoLetters($company) as $data) {
            $letter = CompanyLetter::create($data + [
                'company_id' => $company->id,
                'reference_number' => $company->nextLetterNumber(),
            ]);
            $this->line("  Created: {$letter->reference_number} — {$letter->title}");
        }

        $this->newLine();
        $this->info('Demo letters are ready — open Machinery > Letters & Agreements for '.$company->name.' to view and download each PDF.');
        $this->line('Remove them again anytime with: php artisan demo:machinery-letters --fresh --company="'.$search.'"');

        return self::SUCCESS;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function demoLetters(Company $company): array
    {
        $today = now()->toDateString();

        $saleAgreement = LetterPresets::blueprint('machinery_sale_agreement');
        $purchaseAgreement = LetterPresets::blueprint('machinery_purchase_agreement');
        $rentalAgreement = LetterPresets::blueprint('machinery_rental_agreement');
        $hireInAgreement = LetterPresets::blueprint('machinery_hire_in_agreement');
        $workHandover = LetterPresets::blueprint('work_handover_letter');

        return [
            // Selling an old machine the company no longer needs.
            [
                'document_type' => 'machinery_sale_agreement',
                'title' => self::PREFIX.$saleAgreement['title'],
                'title_ar' => $saleAgreement['title_ar'],
                'letter_date' => $today,
                'party_a_role' => $saleAgreement['party_a_role'],
                'party_b_role' => $saleAgreement['party_b_role'],
                'party_b_name' => 'Al Bayan Equipment Trading Co.',
                'party_b_details' => __('C.R. No.: :number', ['number' => '1010123456']),
                'language_mode' => 'bilingual',
                'content' => $saleAgreement['content'],
            ],
            // Buying a new machine from a supplier.
            [
                'document_type' => 'machinery_purchase_agreement',
                'title' => self::PREFIX.$purchaseAgreement['title'],
                'title_ar' => $purchaseAgreement['title_ar'],
                'letter_date' => $today,
                'party_a_role' => $purchaseAgreement['party_a_role'],
                'party_b_role' => $purchaseAgreement['party_b_role'],
                'party_b_name' => 'Al Faisaliah Machinery Co.',
                'party_b_details' => __('C.R. No.: :number', ['number' => '1010654321']),
                'language_mode' => 'bilingual',
                'content' => $purchaseAgreement['content'],
            ],
            // Renting OUT the company's own machine to a third party.
            [
                'document_type' => 'machinery_rental_agreement',
                'title' => self::PREFIX.$rentalAgreement['title'],
                'title_ar' => $rentalAgreement['title_ar'],
                'letter_date' => $today,
                'party_a_role' => $rentalAgreement['party_a_role'],
                'party_b_role' => $rentalAgreement['party_b_role'],
                'party_b_name' => 'Al Noor Construction Est.',
                'party_b_details' => __('C.R. No.: :number', ['number' => '1010789012']),
                'language_mode' => 'bilingual',
                'content' => $rentalAgreement['content'],
            ],
            // Hiring equipment IN from a supplier — mirrors the real MC1
            // & RC2 Vehicle/Equipment Agreement the company already uses.
            [
                'document_type' => 'machinery_hire_in_agreement',
                'title' => self::PREFIX.$hireInAgreement['title'],
                'title_ar' => $hireInAgreement['title_ar'],
                'letter_date' => $today,
                'party_a_role' => $hireInAgreement['party_a_role'],
                'party_b_role' => $hireInAgreement['party_b_role'],
                'party_b_name' => 'Simat Al-Rifah Co.',
                'party_b_details' => __('C.R. No.: :number', ['number' => '7053564584']),
                'language_mode' => 'bilingual',
                'content' => $hireInAgreement['content'],
            ],
            // Handing over completed work on a project.
            [
                'document_type' => 'work_handover_letter',
                'title' => self::PREFIX.$workHandover['title'],
                'title_ar' => $workHandover['title_ar'],
                'letter_date' => $today,
                'party_a_role' => $workHandover['party_a_role'],
                'party_b_role' => $workHandover['party_b_role'],
                'party_b_name' => 'Dynamic City Development Co.',
                'party_b_details' => __('C.R. No.: :number', ['number' => '1010345678']),
                'language_mode' => 'bilingual',
                'content' => $workHandover['content'],
            ],
        ];
    }
}
