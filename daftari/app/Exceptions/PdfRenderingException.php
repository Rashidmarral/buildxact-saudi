<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Thrown when MpdfRenderer fails to produce a PDF (e.g. a corrupt embedded
 * image or unrecoverable font issue). Handled globally in bootstrap/app.php
 * so every PDF-generating controller gets the same friendly error instead
 * of a raw 500.
 */
class PdfRenderingException extends RuntimeException {}
