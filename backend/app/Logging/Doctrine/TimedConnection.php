<?php

declare(strict_types=1);

namespace App\Logging\Doctrine;

use App\Logging\QueryStats;
use Doctrine\DBAL\Driver\Connection;
use Doctrine\DBAL\Driver\Middleware\AbstractConnectionMiddleware;
use Doctrine\DBAL\Driver\Result;
use Doctrine\DBAL\Driver\Statement;

/** @internal */
final class TimedConnection extends AbstractConnectionMiddleware
{
    public function __construct(Connection $connection, private readonly QueryStats $stats)
    {
        parent::__construct($connection);
    }

    public function prepare(string $sql): Statement
    {
        return new TimedStatement(parent::prepare($sql), $sql, $this->stats);
    }

    public function query(string $sql): Result
    {
        $start = hrtime(true);
        try {
            return parent::query($sql);
        } finally {
            QueryLoggingMiddleware::observe($this->stats, $sql, 0, $start);
        }
    }

    public function exec(string $sql): int|string
    {
        $start = hrtime(true);
        try {
            return parent::exec($sql);
        } finally {
            QueryLoggingMiddleware::observe($this->stats, $sql, 0, $start);
        }
    }
}
