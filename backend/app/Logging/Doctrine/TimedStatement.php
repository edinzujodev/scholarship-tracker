<?php

declare(strict_types=1);

namespace App\Logging\Doctrine;

use App\Logging\QueryStats;
use Doctrine\DBAL\Driver\Middleware\AbstractStatementMiddleware;
use Doctrine\DBAL\Driver\Result;
use Doctrine\DBAL\Driver\Statement;
use Doctrine\DBAL\ParameterType;

/** @internal */
final class TimedStatement extends AbstractStatementMiddleware
{
    private int $parameters = 0;

    public function __construct(Statement $statement, private readonly string $sql, private readonly QueryStats $stats)
    {
        parent::__construct($statement);
    }

    public function bindValue(int|string $param, mixed $value, ParameterType $type): void
    {
        $this->parameters++;
        parent::bindValue($param, $value, $type);
    }

    public function execute(): Result
    {
        $start = hrtime(true);
        try {
            return parent::execute();
        } finally {
            QueryLoggingMiddleware::observe($this->stats, $this->sql, $this->parameters, $start);
        }
    }
}
