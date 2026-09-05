<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Thrown by LedgerPostingService::post() when a transaction's date falls
 * on or before the company's accounting_lock_date. Handled globally in
 * bootstrap/app.php so every document controller that posts to the ledger
 * gets the same friendly "period is closed" error instead of a raw 500.
 */
class PeriodLockedException extends RuntimeException {}
