<?php

declare(strict_types=1);

namespace App\Logging;

/**
 * Per-request database statistics, filled by the Doctrine logging middleware
 * and written into the access log. A query count that grows with the page
 * size is the signature of an N+1 problem.
 *
 * Bound as a singleton and reset at the start of every request.
 */
final class QueryStats
{
    private int $count = 0;
    private float $totalMs = 0.0;

    public function record(float $durationMs): void
    {
        $this->count++;
        $this->totalMs += $durationMs;
    }

    public function count(): int
    {
        return $this->count;
    }

    public function totalMs(): float
    {
        return round($this->totalMs, 2);
    }

    public function reset(): void
    {
        $this->count = 0;
        $this->totalMs = 0.0;
    }
}
