<?php

declare(strict_types=1);

namespace App\Logging;

use Monolog\LogRecord;
use Throwable;
use Monolog\Processor\ProcessorInterface;

/**
 * Last line of defence: nothing secret or personal reaches a log line, even
 * if a developer passes it in context by mistake. Runs on every channel.
 *
 *  - keys that name a secret or PII (authorization, token, password, cookie,
 *    email, ...) have their value replaced, at any depth
 *  - bearer tokens and email addresses inside any string are masked
 */
final class RedactSensitiveData implements ProcessorInterface
{
    public const MASK = '[REDACTED]';

    private const SENSITIVE_KEY = '/(authorization|token|password|passwd|secret|cookie|session|api[_-]?key|email|e-mail)/i';
    private const BEARER = '/Bearer\s+[A-Za-z0-9._~+\/=-]+/i';
    private const QUERY_SECRET = '/([?&](?:token|access_token|api_key|password)=)[^&\s]+/i';
    private const EMAIL = '/[A-Z0-9._%+-]+@[A-Z0-9.-]+\.[A-Z]{2,}/i';

    public function __invoke(LogRecord $record): LogRecord
    {
        return $record->with(
            message: $this->scrubString($record->message),
            context: $this->scrub($record->context),
            extra: $this->scrub($record->extra),
        );
    }

    /**
     * @param array<array-key, mixed> $data
     * @return array<array-key, mixed>
     */
    private function scrub(array $data): array
    {
        foreach ($data as $key => $value) {
            if (is_string($key) && preg_match(self::SENSITIVE_KEY, $key) === 1) {
                $data[$key] = self::MASK;
            } elseif ($value instanceof Throwable) {
                // Laravel attaches the exception object; summarise it here so its
                // message and trace are scrubbed before the JSON formatter sees them.
                $data[$key] = $this->scrub([
                    'class' => $value::class,
                    'message' => $value->getMessage(),
                    'code' => $value->getCode(),
                    'file' => $value->getFile().':'.$value->getLine(),
                    'trace' => array_slice(explode("
", $value->getTraceAsString()), 0, 15),
                ]);
            } elseif (is_array($value)) {
                $data[$key] = $this->scrub($value);
            } elseif (is_string($value)) {
                $data[$key] = $this->scrubString($value);
            }
        }

        return $data;
    }

    private function scrubString(string $value): string
    {
        return (string) preg_replace(
            [self::BEARER, self::QUERY_SECRET, self::EMAIL],
            ['Bearer '.self::MASK, '$1'.self::MASK, self::MASK],
            $value,
        );
    }
}
