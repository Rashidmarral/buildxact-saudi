<?php

namespace App\Services;

use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Renders a Blade view to PDF through a real Chromium engine (via
 * scripts/render-pdf.mjs) instead of dompdf. dompdf is a pure-PHP PDF
 * library that cannot correctly shape Arabic text — letters render
 * disconnected/reversed — while Chromium renders it perfectly, matching
 * exactly what the same view produces on screen and via the browser's own
 * Print/PDF button. Requires Node.js + `npm install` (playwright) and, in
 * production, `npx playwright install --with-deps chromium` once.
 */
class ChromiumPdfRenderer
{
    /**
     * Renders the view with the 'public' disk's URL temporarily pointed at
     * a file:// path instead of the app's HTTP URL, so Storage::url() calls
     * inside the shared print partial (logo/stamp images) resolve to files
     * Chromium can load directly during a file:// navigation — no network
     * round-trip back into the app, and no changes needed to that partial.
     */
    public function renderDocument(string $view, array $data = []): string
    {
        $originalUrl = Config::get('filesystems.disks.public.url');

        Config::set('filesystems.disks.public.url', 'file://'.storage_path('app/public'));

        try {
            $html = view($view, $data + ['inlineCss' => $this->compiledCss()])->render();
        } finally {
            Config::set('filesystems.disks.public.url', $originalUrl);
        }

        return $this->renderHtml($html);
    }

    private function compiledCss(): string
    {
        $manifest = json_decode(file_get_contents(public_path('build/manifest.json')), true);
        $cssFile = $manifest['resources/css/app.css']['file'] ?? null;

        return $cssFile ? file_get_contents(public_path('build/'.$cssFile)) : '';
    }

    public function renderHtml(string $html): string
    {
        $id = (string) Str::uuid();
        $htmlPath = storage_path("app/tmp-pdf-{$id}.html");
        $pdfPath = storage_path("app/tmp-pdf-{$id}.pdf");

        file_put_contents($htmlPath, $html);

        try {
            $result = Process::timeout(60)->env(array_filter([
                'CHROMIUM_EXECUTABLE_PATH' => env('CHROMIUM_EXECUTABLE_PATH'),
                'PLAYWRIGHT_BROWSERS_PATH' => env('PLAYWRIGHT_BROWSERS_PATH'),
            ]))->path(base_path())->run([
                'node', 'scripts/render-pdf.mjs', $htmlPath, $pdfPath,
            ]);

            if (! $result->successful() || ! file_exists($pdfPath)) {
                throw new RuntimeException('Chromium PDF render failed: '.$result->errorOutput());
            }

            return file_get_contents($pdfPath);
        } finally {
            @unlink($htmlPath);
            @unlink($pdfPath);
        }
    }
}
