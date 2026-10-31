<?php

namespace App\Support;

use Illuminate\Http\UploadedFile;

class CsvImporter
{
    /**
     * Parses an uploaded CSV into an array of associative rows keyed by a
     * normalized version of the header row (lowercased, spaces/dashes to
     * underscores) so "VAT Number" and "vat_number" both map the same way.
     * Blank rows are skipped.
     */
    public static function parse(UploadedFile $file): array
    {
        $handle = fopen($file->getRealPath(), 'r');

        if ($handle === false) {
            return [];
        }

        // A UTF-8 BOM at the start of the file (common when a CSV is saved
        // from Excel) would otherwise get glued onto the first header name.
        $bom = fread($handle, 3);
        if ($bom !== "\xEF\xBB\xBF") {
            rewind($handle);
        }

        $header = fgetcsv($handle);

        if ($header === false) {
            fclose($handle);

            return [];
        }

        $header = array_map(
            fn ($column) => strtolower(trim(preg_replace('/[\s\-]+/', '_', (string) $column))),
            $header
        );

        $rows = [];

        while (($line = fgetcsv($handle)) !== false) {
            if (count(array_filter($line, fn ($value) => trim((string) $value) !== '')) === 0) {
                continue;
            }

            $row = [];
            foreach ($header as $index => $column) {
                $row[$column] = trim((string) ($line[$index] ?? ''));
            }

            $rows[] = $row;
        }

        fclose($handle);

        return $rows;
    }
}
