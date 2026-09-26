<?php

namespace App\Http\Controllers\User\Concerns;

use App\Support\CsvImporter;
use Closure;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\ValidationException;

trait ImportsCsv
{
    /**
     * Runs a CSV import row by row rather than all-or-nothing: one bad row
     * (missing name, invalid VAT format, ...) is skipped and reported, not
     * a reason to reject the whole file. $mapRow validates/normalizes a
     * raw CSV row into model-ready data (throwing ValidationException on
     * bad input); $createRow persists it.
     *
     * $maxRows caps how many rows get created — used to stop partway
     * through the file once a plan limit would be exceeded, rather than
     * creating an unbounded number of records the plan doesn't allow.
     */
    protected function runCsvImport(UploadedFile $file, Closure $mapRow, Closure $createRow, ?int $maxRows = null): array
    {
        $rows = CsvImporter::parse($file);
        $imported = 0;
        $errors = [];

        foreach ($rows as $index => $row) {
            $lineNumber = $index + 2; // +1 for header row, +1 for 1-based line numbers

            if ($maxRows !== null && $imported >= $maxRows) {
                $errors[] = __('Row :line: skipped — plan limit reached.', ['line' => $lineNumber]);

                continue;
            }

            try {
                $data = $mapRow($row);
                $createRow($data);
                $imported++;
            } catch (ValidationException $e) {
                $errors[] = __('Row :line: :error', ['line' => $lineNumber, 'error' => collect($e->errors())->flatten()->first()]);
            }
        }

        return ['imported' => $imported, 'total' => count($rows), 'errors' => $errors];
    }
}
