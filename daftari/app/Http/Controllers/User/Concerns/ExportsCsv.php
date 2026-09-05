<?php

namespace App\Http\Controllers\User\Concerns;

use Symfony\Component\HttpFoundation\StreamedResponse;

trait ExportsCsv
{
    private function csvResponse(string $filename, array $header, iterable $rows): StreamedResponse
    {
        return response()->streamDownload(function () use ($header, $rows) {
            $handle = fopen('php://output', 'w');
            fwrite($handle, "\xEF\xBB\xBF"); // UTF-8 BOM so Excel renders Arabic correctly
            fputcsv($handle, $this->sanitizeCsvRow($header));
            foreach ($rows as $row) {
                fputcsv($handle, $this->sanitizeCsvRow($row));
            }
            fclose($handle);
        }, $filename, ['Content-Type' => 'text/csv']);
    }

    /**
     * CSV/formula injection guard: a cell starting with =, +, -, or @ is
     * interpreted as a formula by Excel/Sheets/LibreOffice on open, so a
     * client/supplier/item name entered as e.g. "=HYPERLINK(...)" would
     * execute rather than display as text. Prefixing with a single quote
     * neutralizes it (spreadsheet apps show the value as plain text)
     * without changing what a normal name/note looks like.
     */
    private function sanitizeCsvRow(array $row): array
    {
        return array_map(function ($cell) {
            return is_string($cell) && $cell !== '' && in_array($cell[0], ['=', '+', '-', '@'], true)
                ? "'".$cell
                : $cell;
        }, $row);
    }
}
