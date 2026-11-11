<?php

namespace Tests\Feature;

use App\Support\BankStatementPdfParser;
use Illuminate\Http\UploadedFile;
use Mpdf\Mpdf;
use Tests\TestCase;

/**
 * Unit coverage for the PDF statement line-reading rules themselves (see
 * the parser's own docblock for why this can only ever be best-effort):
 * DR/CR labels, a plain two-number amount+balance line, a three-number
 * line too ambiguous to read, and non-transaction lines (headers,
 * footers, opening balance) being silently skipped rather than guessed
 * at as a long way to avoid a bad import.
 */
class BankStatementPdfParserTest extends TestCase
{
    private function pdfFile(string $html): UploadedFile
    {
        $mpdf = new Mpdf(['tempDir' => storage_path('app/mpdf-tmp')]);
        $mpdf->WriteHTML($html);
        $bytes = $mpdf->Output('', 'S');

        $path = tempnam(sys_get_temp_dir(), 'stmt').'.pdf';
        file_put_contents($path, $bytes);

        return new UploadedFile($path, 'statement.pdf', 'application/pdf', null, true);
    }

    public function test_a_two_number_line_reads_the_first_number_as_the_amount(): void
    {
        $file = $this->pdfFile('<p>2026-06-11 Deposit from Client A 1,000.00 34,500.00</p>');

        $rows = BankStatementPdfParser::parse($file);

        $this->assertCount(1, $rows);
        $this->assertSame('2026-06-11', $rows[0]['date']);
        $this->assertSame('1000', (string) (float) $rows[0]['amount']);
        $this->assertStringContainsString('Deposit from Client A', $rows[0]['description']);
    }

    public function test_a_dr_labeled_line_is_read_as_negative(): void
    {
        $file = $this->pdfFile('<p>11 Jun 2026 Transfer to Supplier X 250.00 DR 33,000.00</p>');

        $rows = BankStatementPdfParser::parse($file);

        $this->assertCount(1, $rows);
        $this->assertSame(-250.0, (float) $rows[0]['amount']);
    }

    public function test_a_cr_labeled_line_is_read_as_positive(): void
    {
        $file = $this->pdfFile('<p>12 Jun 2026 Salary Credit 5,000.00 CR 38,000.00</p>');

        $rows = BankStatementPdfParser::parse($file);

        $this->assertCount(1, $rows);
        $this->assertSame(5000.0, (float) $rows[0]['amount']);
    }

    public function test_an_ambiguous_three_number_line_with_no_label_is_skipped(): void
    {
        $file = $this->pdfFile('<p>13 Jun 2026 Fee 15.00 50.00 33,015.00</p>');

        $rows = BankStatementPdfParser::parse($file);

        $this->assertCount(0, $rows);
    }

    public function test_header_and_summary_lines_with_no_leading_date_are_skipped(): void
    {
        $file = $this->pdfFile('<p>Statement Period: 01 Jun 2026 to 30 Jun 2026</p><p>Opening Balance 35,000.00</p>');

        $rows = BankStatementPdfParser::parse($file);

        $this->assertCount(0, $rows);
    }

    public function test_a_reference_code_with_embedded_digits_is_not_mistaken_for_an_amount(): void
    {
        $file = $this->pdfFile('<p>11/06/2026 SADAD BILL PAYMENT REF123 -1,250.00 33,250.00</p>');

        $rows = BankStatementPdfParser::parse($file);

        $this->assertCount(1, $rows);
        $this->assertSame(-1250.0, (float) $rows[0]['amount']);
        $this->assertStringContainsString('REF123', $rows[0]['description']);
    }

    /**
     * A transaction's description cell routinely wraps across several
     * physical lines once a PDF's table structure is flattened into
     * plain text — the real-world case this was built against is an
     * "Outgoing IPS local transfer" row from an actual Saudi bank
     * statement, whose description alone spans 5 separate lines before
     * the reference/type/amount/balance line is reached.
     */
    public function test_a_description_wrapped_across_several_lines_is_still_read_correctly(): void
    {
        $file = $this->pdfFile('<p>28/10/2026<br>Outgoing IPS local transfer<br>Services -Training<br>20261028SANCBKNCBK6B8241182<br>132792232 JM -4,000.00 79,577.18</p>');

        $rows = BankStatementPdfParser::parse($file);

        $this->assertCount(1, $rows);
        $this->assertSame('2026-10-28', $rows[0]['date']);
        $this->assertSame(-4000.0, (float) $rows[0]['amount']);
        $this->assertStringContainsString('Outgoing IPS local transfer', $rows[0]['description']);
        $this->assertStringContainsString('Services -Training', $rows[0]['description']);
    }

    /**
     * A wrapped description can mention its own incidental bank-charge
     * figure inline (observed verbatim on a real statement: "...
     * Charges: 1.00 REMBK:THE SAUDI NATIONAL BANK...") well before the
     * row's actual amount/balance line. Reading the first decimal-
     * looking number found, forwards, would lock in that 1 SAR fee as
     * the transaction's amount instead of the real figure one line
     * later — silently corrupting the import rather than just skipping
     * an unparseable row.
     */
    public function test_an_inline_charges_mention_in_the_description_is_not_read_as_the_amount(): void
    {
        $file = $this->pdfFile('<p>28/10/2026<br>Outgoing IPS local transfer<br>5594424 Charges: 1.00 REMBK:THE SAUDI NATIONAL BANK<br>132792232 JM -4,000.00 79,577.18</p>');

        $rows = BankStatementPdfParser::parse($file);

        $this->assertCount(1, $rows);
        $this->assertSame(-4000.0, (float) $rows[0]['amount']);
    }

    /**
     * The statement's own trailing summary totals ("Total Debit
     * Transactions 105 ... Total Debit Amount -477,308.15 Total Credit
     * Amount 531,068.06") sit right after the very last transaction's
     * description with no further date-leading line to bound it — so a
     * naive "keep reading until a number resolves" scan for the LAST
     * transaction in a statement can run straight past its own
     * reference/type/amount/balance line and resolve against the
     * summary line instead, corrupting the final row with the
     * statement-wide total rather than its own amount.
     */
    public function test_trailing_summary_totals_after_the_last_transaction_are_not_read_as_its_amount(): void
    {
        $file = $this->pdfFile(
            '<p>19/05/2026<br>Incoming Internal transfer BA:Payroll<br>16600000449800 DEP<br>121094210 U9 500.00 500.00</p>'.
            '<p>Total Debit Transactions 105 Total Credit Transactions 18</p>'.
            '<p>Total Debit Amount -477,308.15 Total Credit Amount 531,068.06</p>'
        );

        $rows = BankStatementPdfParser::parse($file);

        $this->assertCount(1, $rows);
        $this->assertSame(500.0, (float) $rows[0]['amount']);
    }
}
