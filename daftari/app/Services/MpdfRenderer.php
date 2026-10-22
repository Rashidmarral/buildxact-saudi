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
            $template = $data['template'] ?? null;
            $pageSize = $this->resolvePageSize($template);

            $mpdf = $this->makeMpdf($pageSize, $this->resolvePageMargins($template, $pageSize));
            $embed = $this->embedHelper();

            // A template's background watermark is an mPDF page feature
            // (drawn once per page, behind the HTML content), not something
            // that can be expressed as regular HTML/CSS the way the logo,
            // stamp and footer banner are — it has to be configured on the
            // Mpdf instance itself before WriteHTML() runs.
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

    /**
     * Appends every given PDF's pages, in order, onto one combined
     * document — used to fold a Purchase Order's PDF attachments (a
     * sub-vendor's own quotation, required approval documents, ...)
     * directly into the downloaded PO instead of leaving them as
     * separate files the recipient has to open one by one. mPDF ships
     * setasign/fpdi (see Mpdf\FpdiTrait) specifically for this, so no
     * extra package is needed. An entry that isn't a valid/readable PDF
     * is skipped rather than failing the whole download — one corrupt
     * attachment shouldn't block the PO itself.
     */
    public function mergePdfs(array $pdfByteStrings): string
    {
        $pdfByteStrings = array_values(array_filter($pdfByteStrings));

        if (count($pdfByteStrings) <= 1) {
            return $pdfByteStrings[0] ?? '';
        }

        $tempDir = storage_path('app/mpdf-tmp');
        File::ensureDirectoryExists($tempDir);

        $merged = new Mpdf(['tempDir' => $tempDir]);

        foreach ($pdfByteStrings as $bytes) {
            try {
                $pageCount = $merged->setSourceFile(\setasign\Fpdi\PdfParser\StreamReader::createByString($bytes));
            } catch (\Throwable) {
                continue;
            }

            for ($i = 1; $i <= $pageCount; $i++) {
                $templateId = $merged->importPage($i);
                $size = $merged->getTemplateSize($templateId);
                $merged->AddPageByArray([
                    'orientation' => $size['orientation'],
                    'newformat' => [$size['width'], $size['height']],
                ]);
                $merged->useTemplate($templateId);
            }
        }

        return $merged->Output('', 'S');
    }

    /**
     * A receipt layout ('receipt_compact'/'receipt_detailed') gets a
     * narrow, thermal-style page instead of a full A4/Letter sheet — mPDF
     * accepts [width_mm, height_mm] as a 'format' value just as readily
     * as the named constants below.
     */
    private function resolvePageSize(mixed $template): string|array
    {
        if ($template instanceof \App\Models\InvoiceTemplate && str_starts_with((string) $template->layout, 'receipt_')) {
            return [80, 250];
        }

        return ($template instanceof \App\Models\InvoiceTemplate && $template->page_size === 'letter') ? 'Letter' : 'A4';
    }

    /**
     * mPDF's own auto-margin recalculation (setAutoTopMargin/
     * setAutoBottomMargin: 'stretch', triggered by a view's own
     * <sethtmlpageheader>/<sethtmlpagefooter>) only grows the page margin
     * from the point in the HTML stream where it's processed onward — by
     * the time that tag runs, page 1 has already started with whatever
     * margin the Mpdf constructor set, so a letterhead/footer image
     * taller than the default margin overlapped the body content on page
     * 1 specifically (confirmed: page 2+ always had the correct, larger
     * margin, since AddPage() for those happens after the recalculation).
     * Measuring the real image dimensions and sizing margin_top/
     * margin_bottom to fit BEFORE the Mpdf object (and therefore page 1)
     * is ever created fixes this at the source; 'stretch' stays on as a
     * safety net for a measurement that somehow still comes up short.
     *
     * The top margin only needs this for the 'custom_letterhead' layout
     * (the only one with a full-width header image, used by letter-pdf.
     * blade.php and pdf.blade.php). The bottom margin needs it whenever
     * footer_path is set at all, independent of layout — pdf.blade.php,
     * project-cash-flow-pdf.blade.php, voucher-pdf.blade.php and
     * voucher-summary-pdf.blade.php all offer a footer banner on every
     * layout, not just custom_letterhead.
     */
    private function resolvePageMargins(mixed $template, string|array $pageSize): array
    {
        $defaults = ['top' => 12.0, 'bottom' => 15.0];

        if (! $template instanceof \App\Models\InvoiceTemplate) {
            return $defaults;
        }

        // A sensible floor for a configured letterhead/footer whose real
        // height couldn't be measured at all (seen in practice: an SVG
        // upload with no explicit width/height/viewBox on its root
        // element, which getimagesizefromstring() can't read either —
        // it has no standard raster header to parse). This is strictly
        // better than silently falling back to the plain-text $defaults
        // above, which is exactly what reproduced the overlap this is
        // fixing: a real image is known to be there, so erring generous
        // beats erring tiny.
        $unmeasuredFallback = ['top' => 40.0, 'bottom' => 35.0];

        $printableWidthMm = $this->pageWidthMm($pageSize) - 12 - 12;

        if ($template->layout === 'custom_letterhead' && $template->letterhead_path) {
            $headerHeight = $this->imageHeightMm($template->letterhead_path, $printableWidthMm);
            $defaults['top'] = $headerHeight > 0 ? max($defaults['top'], $headerHeight + 6) : $unmeasuredFallback['top'];
        }

        if ($template->footer_path) {
            $footerHeight = $this->imageHeightMm($template->footer_path, $printableWidthMm);
            $defaults['bottom'] = $footerHeight > 0 ? max($defaults['bottom'], $footerHeight + 6) : $unmeasuredFallback['bottom'];
        }

        return $defaults;
    }

    private function pageWidthMm(string|array $pageSize): float
    {
        if (is_array($pageSize)) {
            return (float) $pageSize[0];
        }

        return $pageSize === 'Letter' ? 215.9 : 210.0;
    }

    private function imageHeightMm(string $path, float $printableWidthMm): float
    {
        if (! Storage::disk('public')->exists($path)) {
            return 0.0;
        }

        $bytes = Storage::disk('public')->get($path);
        $size = @getimagesizefromstring($bytes);

        if ($size && $size[0] > 0 && $size[1] > 0) {
            return ($size[1] / $size[0]) * $printableWidthMm;
        }

        if (str_ends_with(strtolower($path), '.svg') || str_contains(substr($bytes, 0, 500), '<svg')) {
            return $this->svgHeightMm($bytes, $printableWidthMm);
        }

        return 0.0;
    }

    /**
     * getimagesizefromstring() doesn't reliably read SVG dimensions (no
     * standard raster header) — read them straight out of the root
     * <svg> element's width/height attributes instead, falling back to
     * viewBox (the two most common ways a real-world SVG export states
     * its own aspect ratio).
     */
    private function svgHeightMm(string $svg, float $printableWidthMm): float
    {
        preg_match('/<svg\b[^>]*>/i', $svg, $tagMatch);
        $tag = $tagMatch[0] ?? '';

        $width = $this->svgAttrValue($tag, 'width');
        $height = $this->svgAttrValue($tag, 'height');

        if ($width > 0 && $height > 0) {
            return ($height / $width) * $printableWidthMm;
        }

        if (preg_match('/viewBox\s*=\s*"\s*[\d.-]+\s+[\d.-]+\s+([\d.]+)\s+([\d.]+)\s*"/i', $tag, $viewBox)) {
            $viewBoxWidth = (float) $viewBox[1];
            $viewBoxHeight = (float) $viewBox[2];

            if ($viewBoxWidth > 0 && $viewBoxHeight > 0) {
                return ($viewBoxHeight / $viewBoxWidth) * $printableWidthMm;
            }
        }

        return 0.0;
    }

    private function svgAttrValue(string $tag, string $attribute): float
    {
        return preg_match('/\b'.$attribute.'\s*=\s*"([\d.]+)/i', $tag, $match) ? (float) $match[1] : 0.0;
    }

    private function makeMpdf(string|array $pageSize = 'A4', array $margins = ['top' => 12.0, 'bottom' => 15.0]): Mpdf
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
            'format' => $pageSize,
            'margin_left' => 12,
            'margin_right' => 12,
            'margin_top' => $margins['top'],
            'margin_bottom' => $margins['bottom'],
            // Only takes effect for a document that actually defines a
            // repeating HTML header/footer via mPDF's own
            // <htmlpageheader>/<sethtmlpageheader> tags — it auto-grows
            // the page's top/bottom margin to fit whatever that header/
            // footer's real rendered height turns out to be, so body
            // content never overlaps it. A no-op for every other
            // document, which never sets an HTML header/footer at all.
            // Acts only as a safety net now that margin_top/margin_bottom
            // above are already pre-sized for a letterhead/footer image
            // (see resolvePageMargins()) — that's what fixes page 1.
            'setAutoTopMargin' => 'stretch',
            'setAutoBottomMargin' => 'stretch',
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
