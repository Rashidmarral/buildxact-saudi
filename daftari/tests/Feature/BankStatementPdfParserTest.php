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
}
