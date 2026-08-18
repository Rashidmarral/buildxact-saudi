<?php

namespace App\Services;

use App\Exceptions\PdfRenderingException;
use Illuminate\Support\Facades\Storage;
use Mpdf\Mpdf;

/**
 * Renders documents to PDF through mPDF — a pure-PHP engine with real
 * Arabic script shaping and bidi support built in, requiring nothing
 * beyond the PHP process itself. This replaced ChromiumPdfRenderer (which
 * shelled out to Node.js + a real browser) as the primary PDF path: that
 * approach is fragile for a product other people will self-host —
 * Node.js/Playwright have to be correctly installed on whatever machine
 * runs the app, and even when they are, Node's own crypto initialization
 * can fail outright on some Windows configurations (a real, user-facing
 * crash this session hit directly: "Assertion failed:
 * ncrypto::CSPRNG(nullptr, 0)"). mPDF has no such external dependency.
 */
class MpdfRenderer
{
    public function render(string $view, array $data = []): string
    {
        try {
            $mpdf = $this->makeMpdf();

            $html = view($view, $data + ['embed' => $this->embedHelper()])->render();

            $mpdf->WriteHTML($html);

            return $mpdf->Output('', 'S');
        } catch (\Throwable $e) {
            throw new PdfRenderingException(
                'PDF generation failed — this has been logged for investigation. Please try again, or contact support if it keeps happening.',
                previous: $e,
            );
        }
    }

    private function makeMpdf(): Mpdf
    {
        $defaultConfig = (new \Mpdf\Config\ConfigVariables())->getDefaults();
        $fontDirs = $defaultConfig['fontDir'];

        $defaultFontConfig = (new \Mpdf\Config\FontVariables())->getDefaults();
        $fontData = $defaultFontConfig['fontdata'];

        return new Mpdf([
            'mode' => 'utf-8',
            'format' => 'A4',
            'margin_left' => 12,
            'margin_right' => 12,
            'margin_top' => 12,
            'margin_bottom' => 15,
            'default_font' => 'cairo',
            'fontDir' => array_merge($fontDirs, [resource_path('fonts')]),
            'fontdata' => $fontData + [
                'cairo' => [
                    'R' => 'Cairo-Regular.ttf',
                    'B' => 'Cairo-Bold.ttf',
                    'I' => 'Cairo-Regular.ttf',
                    'BI' => 'Cairo-Bold.ttf',
                ],
            ],
            'tempDir' => storage_path('app/mpdf-tmp'),
        ]);
    }

    /**
     * mPDF renders standalone (no live app URL, no file:// browser
     * navigation like the old Chromium path needed) — the cleanest way to
     * hand it an image is a base64 data URI read directly from storage,
     * which sidesteps the entire class of "does the storage symlink /
     * file-serving route work" problems for PDFs specifically. Returns
     * null (rather than throwing) for a missing/unreadable file so a
     * stale logo/stamp path never breaks the whole document.
     */
    private function embedHelper(): \Closure
    {
        return function (?string $path): ?string {
            if (! $path || ! Storage::disk('public')->exists($path)) {
                return null;
            }

            $mime = Storage::disk('public')->mimeType($path) ?: 'image/png';
            $contents = Storage::disk('public')->get($path);

            return 'data:'.$mime.';base64,'.base64_encode($contents);
        };
    }
}
