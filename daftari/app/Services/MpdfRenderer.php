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
            $embed = $this->embedHelper();

            // A template's background watermark is an mPDF page feature
            // (drawn once per page, behind the HTML content), not something
            // that can be expressed as regular HTML/CSS the way the logo,
            // stamp and footer banner are — it has to be configured on the
            // Mpdf instance itself before WriteHTML() runs.
            $template = $data['template'] ?? null;
            if ($template instanceof \App\Models\InvoiceTemplate && $template->watermark_path) {
                $watermarkData = $embed($template->watermark_path);
                if ($watermarkData) {
                    $mpdf->SetWatermarkImage($watermarkData, max(1, $template->watermark_opacity ?? 10) / 100);
                    $mpdf->showWatermarkImage = true;
                }
            }

            // mPDF parses HTML/CSS internally with PCRE regexes, and our own
            // diacritic-stripping below runs a PCRE pass over the same
            // markup — both need this raised before either runs. Our
            // documents embed the logo/stamp/QR as base64 data URIs
            // directly in the markup, which easily pushes a single
            // preg_replace()/WriteHTML() call past PHP's default
            // 1,000,000-step pcre.backtrack_limit — silently returning
            // null (preg_replace) or throwing (mPDF) instead of rendering.
            // Raising both limits here (scoped to this request; ini_set
            // doesn't persist) is mPDF's own documented fix for this,
            // rather than trying to keep embedded images artificially
            // small.
            ini_set('pcre.backtrack_limit', '10000000');
            ini_set('pcre.recursion_limit', '10000000');

            $html = $this->stripArabicDiacritics(view($view, $data + ['embed' => $embed])->render());

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
            'fontDir' => array_merge($fontDirs, [resource_path('fonts')]),
            'fontdata' => $fontData + [
                'cairo' => [
                    'R' => 'Cairo-Regular.ttf',
                    'B' => 'Cairo-Bold.ttf',
                    'I' => 'Cairo-Regular.ttf',
                    'BI' => 'Cairo-Bold.ttf',
                    // useOTL/useKashida are NOT valid top-level Mpdf
                    // constructor options in this mPDF version (8.3.1) —
                    // they only exist as per-font keys inside fontdata,
                    // exactly like mPDF's own bundled fonts declare them
                    // (see vendor/mpdf/mpdf/src/Config/FontVariables.php).
                    // Setting them at the top level, as an earlier version
                    // of this file did, is silently ignored: no exception,
                    // no effect — Arabic contextual joining (init/medi/fina
                    // glyph substitution) never actually ran despite every
                    // rasterized preview looking fine (the raster path
                    // tolerates it; some real-world PDF viewers, and the
                    // embedded font's own encoding, don't — this is why
                    // copy/pasted text came out correct while the on-page
                    // glyphs still looked like isolated letters).
                    'useOTL' => 0xFF,
                    'useKashida' => 75,
                ],
            ],
            'tempDir' => $tempDir,
        ]);
    }

    /**
     * mPDF's Cairo font throws "This font [cairo] contains MarkGlyphSets -
     * Not tested yet" the moment it has to shape an Arabic diacritic
     * (tanween, fatha, damma, kasra, shadda, sukun, ...) with OTL enabled
     * — a real mPDF/font limitation, not something these templates can
     * work around by avoiding one specific character, since any future
     * Arabic string carrying tashkeel would hit the same crash. Diacritics
     * are near-universally omitted in everyday (non-Quranic) Arabic
     * writing anyway, so stripping them here is invisible in practice and
     * keeps every current and future document's PDF path safe.
     */
    private function stripArabicDiacritics(string $html): string
    {
        return preg_replace('/[\x{0610}-\x{061A}\x{064B}-\x{065F}\x{0670}\x{06D6}-\x{06ED}]/u', '', $html);
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
