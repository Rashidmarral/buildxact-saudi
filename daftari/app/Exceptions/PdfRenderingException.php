<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Thrown when ChromiumPdfRenderer's underlying `node scripts/render-pdf.mjs`
 * process fails — almost always a missing local setup step (Node.js not
 * installed, `npm install` not run, or Playwright's Chromium binary never
 * downloaded) rather than an application bug. Handled globally in
 * bootstrap/app.php so every controller that generates a PDF/print view
 * gets the same friendly, actionable redirect instead of a raw stack trace.
 */
class PdfRenderingException extends RuntimeException {}
