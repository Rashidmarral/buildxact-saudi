// Renders an HTML file to a PDF file using a real Chromium engine, invoked
// from PHP (see App\Services\ChromiumPdfRenderer). Exists because dompdf
// (a pure-PHP PDF library) cannot correctly shape Arabic text — letters
// render disconnected/reversed — while a real browser engine renders it
// perfectly, matching exactly what users already see on screen and in
// their browser's own Print/PDF output.
//
// Usage: node render-pdf.mjs <input-html-file> <output-pdf-file>
import { chromium } from 'playwright';
import { readFileSync } from 'node:fs';
import { pathToFileURL } from 'node:url';

const [, , inputPath, outputPath] = process.argv;

if (!inputPath || !outputPath) {
    console.error('Usage: node render-pdf.mjs <input-html-file> <output-pdf-file>');
    process.exit(1);
}

const launchOptions = {};
if (process.env.CHROMIUM_EXECUTABLE_PATH) {
    launchOptions.executablePath = process.env.CHROMIUM_EXECUTABLE_PATH;
}

const browser = await chromium.launch(launchOptions);
const page = await browser.newPage();

// file:// navigation (rather than setContent) so relative asset paths in
// the HTML (if any) resolve normally, and so page.pdf() has a stable base.
await page.goto(pathToFileURL(inputPath).href, { waitUntil: 'networkidle' });
await page.emulateMedia({ media: 'print' });

await page.pdf({
    path: outputPath,
    format: 'A4',
    printBackground: true,
    margin: { top: '10mm', bottom: '10mm', left: '10mm', right: '10mm' },
});

await browser.close();

// Touch the file to prove it's non-empty before PHP reads it.
if (readFileSync(outputPath).length === 0) {
    console.error('Rendered PDF is empty');
    process.exit(1);
}
