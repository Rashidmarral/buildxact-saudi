<?php

namespace App\Services;

use Illuminate\Http\Client\HttpClientException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;

/**
 * Reads a photographed/scanned receipt via Google Cloud Vision's
 * TEXT_DETECTION and pulls out a best guess at amount/date/vendor, so the
 * Expense form (the surface every spend-tracking flow in this app funnels
 * through — Cash Custody's "Record spend", Kafeel Payments, ordinary
 * purchases) can prefill itself from a snapped photo instead of the user
 * retyping everything by hand. Same failure-mode discipline as every
 * other outbound integration here (SmsService, WhatsApp, payment
 * gateways): a network/API failure degrades to a clean ['success' =>
 * false] result, never an uncaught 500 — this is an optional assist, not
 * something that should ever block recording an expense.
 */
class ReceiptOcrService
{
    private const API_URL = 'https://vision.googleapis.com/v1/images:annotate';

    public function isConfigured(): bool
    {
        return filled(config('services.google_vision.key'));
    }

    public function scan(UploadedFile $file): array
    {
        if (! $this->isConfigured()) {
            return ['success' => false, 'error' => __('Receipt scanning is not set up for this account yet.')];
        }

        try {
            $response = Http::timeout(20)->post(self::API_URL.'?key='.config('services.google_vision.key'), [
                'requests' => [[
                    'image' => ['content' => base64_encode(file_get_contents($file->getRealPath()))],
                    'features' => [['type' => 'TEXT_DETECTION']],
                ]],
            ]);
        } catch (HttpClientException $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }

        if ($response->failed()) {
            return ['success' => false, 'error' => $response->json('error.message') ?? $response->body()];
        }

        $text = $response->json('responses.0.fullTextAnnotation.text')
            ?? $response->json('responses.0.textAnnotations.0.description');

        if (! $text) {
            $visionError = $response->json('responses.0.error.message');

            return ['success' => false, 'error' => $visionError ?? __('No text could be read from this image.')];
        }

        return ['success' => true, 'raw_text' => $text] + $this->extractFields($text);
    }

    /**
     * Best-effort extraction, not OCR-level precision parsing of an
     * arbitrary receipt layout — the same honest limitation
     * BankStatementPdfParser documents for bank statements applies here:
     * every receipt printer lays things out differently. The amount
     * heuristic prefers a line explicitly labeled total/grand total/
     * amount due (in English or Arabic) over just picking the largest
     * number, since a receipt often lists a larger subtotal-before-
     * discount or an item price above the real total.
     */
    private function extractFields(string $text): array
    {
        $lines = array_values(array_filter(array_map('trim', preg_split('/\r\n|\r|\n/', $text))));

        return [
            'amount' => $this->extractAmount($lines),
            'date' => $this->extractDate($text),
            'vendor_name' => $lines[0] ?? null,
        ];
    }

    private function extractAmount(array $lines): ?float
    {
        $amountPattern = '/(?<![A-Za-z0-9])\d{1,3}(?:,\d{3})*(?:\.\d{1,2})?(?![A-Za-z0-9])/';
        $totalLabel = '/total|amount due|grand total|الإجمالي|المجموع/i';

        foreach ($lines as $line) {
            if (preg_match($totalLabel, $line) && preg_match_all($amountPattern, $line, $m) && $m[0]) {
                return (float) str_replace(',', '', end($m[0]));
            }
        }

        $all = [];
        foreach ($lines as $line) {
            if (preg_match_all($amountPattern, $line, $m)) {
                array_push($all, ...array_map(fn ($a) => (float) str_replace(',', '', $a), $m[0]));
            }
        }

        return $all ? max($all) : null;
    }

    /**
     * Same day-first reading as BankStatementPdfParser::normalizeDate(),
     * and for the same reason: strtotime() assumes US month/day order for
     * a slash-or-dash date, which misreads a Saudi dd/mm/yyyy receipt date.
     */
    private function extractDate(string $text): ?string
    {
        if (! preg_match('/\d{4}-\d{1,2}-\d{1,2}|\d{1,2}[\/\-]\d{1,2}[\/\-]\d{2,4}|\d{1,2}\s+[A-Za-z]{3,9}\s+\d{2,4}/', $text, $m)) {
            return null;
        }

        $raw = $m[0];

        if (preg_match('/^(\d{4})-(\d{1,2})-(\d{1,2})$/', $raw, $d)) {
            return sprintf('%04d-%02d-%02d', $d[1], $d[2], $d[3]);
        }

        if (preg_match('#^(\d{1,2})[/\-](\d{1,2})[/\-](\d{2,4})$#', $raw, $d)) {
            $year = strlen($d[3]) === 2 ? '20'.$d[3] : $d[3];

            return sprintf('%04d-%02d-%02d', $year, $d[2], $d[1]);
        }

        $timestamp = strtotime($raw);

        return $timestamp ? date('Y-m-d', $timestamp) : null;
    }
}
