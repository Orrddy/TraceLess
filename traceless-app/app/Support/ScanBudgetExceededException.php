<?php

namespace App\Support;

use RuntimeException;

/**
 * Raised when a deep scan reaches its wall-clock budget.
 *
 * The orchestrator catches it and fails the scan with a clear message, so the
 * queue worker is never killed by its own timeout in the middle of a write.
 */
class ScanBudgetExceededException extends RuntimeException
{
    public function __construct(public readonly int $budgetSeconds)
    {
        parent::__construct("The deep scan exceeded its {$budgetSeconds} second wall-clock budget.");
    }
}
