<?php

namespace App\Services;

use App\Exceptions\PdfRenderingException;
use Illuminate\Support\Facades\File;
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

            // mPDF parses HTML/CSS internally with PCRE regexes. Our
            // documents embed the logo/stamp/QR as base64 data URIs
            // directly in the markup, which easily pushes a single
            // WriteHTML() call past PHP's default 1,000,000-step
            // pcre.backtrack_limit — mPDF then throws instead of
            // rendering. Raising both limits here (scoped to this
            // request; ini_set doesn't persist) is mPDF's own documented
            // fix for this, rather than trying to keep embedded images
            // artificially small.
            ini_set('pcre.backtrack_limit', '10000000');
            ini_set('pcre.recursion_limit', '10000000');

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

        // mPDF writes working files into tempDir and fails outright if it
        // doesn't exist. Git doesn't track empty directories, so a fresh
        // clone/unzip never has this folder — create it defensively here
        // instead of relying on it having been created some other way.
        $tempDir = storage_path('app/mpdf-tmp');
        File::ensureDirectoryExists($tempDir);

        return new Mpdf([
            'mode' => 'utf-8',
            'format' => 'A4',
            'margin_left' => 12,
            'margin_right' => 12,
            'margin_top' => 12,
            'margin_bottom' => 15,
            'default_font' => 'cairo',
            // mPDF only joins Arabic letters into their contextual
            // (initial/medial/final) glyph forms when its OpenType Layout
            // engine is explicitly turned on — it's opt-in, not automatic,
            // regardless of the font. Without this every Arabic string
            // renders as isolated, disconnected letters.
            'useOTL' => 0xFF,
            'useKashida' => 75,
            'fontDir' => array_merge($fontDirs, [resource_path('fonts')]),
            'fontdata' => $fontData + [
                'cairo' => [
                    'R' => 'Cairo-Regular.ttf',
                    'B' => 'Cairo-Bold.ttf',
                    'I' => 'Cairo-Regular.ttf',
                    'BI' => 'Cairo-Bold.ttf',
                ],
                // Cairo is a geometric sans-serif — its Arabic letterforms
                // connect correctly (verified: the font has proper init/
                // medi/fina GSUB tables and mPDF applies them) but with
                // deliberately thin connecting strokes, which reads as
                // "disconnected" at a glance next to a traditional Arabic
                // typeface. Templates set this specifically for Arabic
                // text runs (see the .ar CSS class) so Arabic prints in a
                // typeface built for it, while Cairo keeps the Latin side.
                'naskh' => [
                    'R' => 'NotoNaskhArabic-Regular.ttf',
                    'B' => 'NotoNaskhArabic-Bold.ttf',
                    'I' => 'NotoNaskhArabic-Regular.ttf',
                    'BI' => 'NotoNaskhArabic-Bold.ttf',
                ],
            ],
            'tempDir' => $tempDir,
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
