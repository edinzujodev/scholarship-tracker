<?php

declare(strict_types=1);

namespace App\Logging\Doctrine;

use App\Logging\QueryStats;
use Doctrine\DBAL\Driver;
use Doctrine\DBAL\Driver\Connection;
use Doctrine\DBAL\Driver\Middleware;
use Doctrine\DBAL\Driver\Middleware\AbstractDriverMiddleware;
use Illuminate\Support\Facades\Log;
use SensitiveParameter;

/**
 * DBAL middleware: counts every SQL execution for the access log and writes
 * queries slower than `logging.slow_query_ms` to the `db` channel.
 *
 * Only the SQL text is logged, never bound parameter values (they can hold
 * personal data); the parameter count is logged instead.
 */
final class QueryLoggingMiddleware implements Middleware
{
    public function __construct(private readonly QueryStats $stats)
    {
    }

    public function wrap(Driver $driver): Driver
    {
        $stats = $this->stats;

        return new class ($driver, $stats) extends AbstractDriverMiddleware {
            public function __construct(Driver $driver, private readonly QueryStats $stats)
            {
                parent::__construct($driver);
            }

            public function connect(#[SensitiveParameter] array $params): Connection
            {
                return new TimedConnection(parent::connect($params), $this->stats);
            }
        };
    }

    /** @internal */
    public static function observe(QueryStats $stats, string $sql, int $parameters, float $startedAt): void
    {
        $durationMs = (hrtime(true) - $startedAt) / 1e6;
        $stats->record($durationMs);

        $threshold = (float) config('logging.slow_query_ms', 200);
        if ($durationMs >= $threshold) {
            Log::channel('db')->warning('Slow query', [
                'sql' => preg_replace('/\s+/', ' ', trim($sql)),
                'parameter_count' => $parameters,
                'duration_ms' => round($durationMs, 2),
                'threshold_ms' => $threshold,
            ]);
        }
    }
}
