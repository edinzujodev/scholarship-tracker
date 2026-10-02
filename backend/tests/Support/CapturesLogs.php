<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Logging\RedactSensitiveData;
use Monolog\Handler\TestHandler;
use Monolog\Logger;
use Monolog\LogRecord;

/**
 * Routes every structured log channel into one in-memory Monolog TestHandler
 * (with the production redaction processor), so tests can assert exactly what
 * was logged.
 */
trait CapturesLogs
{
    protected TestHandler $logs;

    protected function setUpCapturesLogs(): void
    {
        $this->logs = $handler = new TestHandler();

        // A `custom` driver, because the `monolog` driver always passes a
        // `level` argument and so builds a fresh handler instead of this one.
        foreach (['app', 'http', 'security', 'audit', 'db'] as $channel) {
            config(["logging.channels.{$channel}" => [
                'driver' => 'custom',
                'via' => static fn (array $config): Logger => new Logger($channel, [$handler], [new RedactSensitiveData()]),
            ]]);
        }
    }

    /** @return list<LogRecord> */
    protected function logRecords(string $channel): array
    {
        return array_values(array_filter($this->logs->getRecords(), static fn (LogRecord $r): bool => $r->channel === $channel));
    }

    protected function lastLog(string $channel): LogRecord
    {
        $records = $this->logRecords($channel);
        $this->assertNotEmpty($records, "Expected a log record on the {$channel} channel.");

        return $records[array_key_last($records)];
    }
}
