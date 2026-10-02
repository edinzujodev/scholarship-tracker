<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Logging\RedactSensitiveData;
use DateTimeImmutable;
use Monolog\Level;
use Monolog\LogRecord;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class RedactSensitiveDataTest extends TestCase
{
    private function process(string $message, array $context = []): LogRecord
    {
        return (new RedactSensitiveData())(new LogRecord(new DateTimeImmutable(), 'app', Level::Info, $message, $context));
    }

    #[Test]
    public function sensitive_keys_are_masked_at_any_depth(): void
    {
        $record = $this->process('call', [
            'Authorization' => 'Bearer demo-partner-token',
            'headers' => ['cookie' => 'portal_session=abc', 'x-request-id' => 'abc-123-def'],
            'applicant' => ['email' => 'ada@example.test', 'first_name' => 'Ada'],
            'partner_token' => 'demo-partner-token',
        ]);

        $this->assertSame(RedactSensitiveData::MASK, $record->context['Authorization']);
        $this->assertSame(RedactSensitiveData::MASK, $record->context['headers']['cookie']);
        $this->assertSame('abc-123-def', $record->context['headers']['x-request-id']);
        $this->assertSame(RedactSensitiveData::MASK, $record->context['applicant']['email']);
        $this->assertSame(RedactSensitiveData::MASK, $record->context['partner_token']);
    }

    #[Test]
    public function tokens_emails_and_query_secrets_inside_text_are_masked(): void
    {
        $record = $this->process(
            'Upstream rejected Bearer demo-partner-token for ada@example.test',
            ['url' => '/callback?token=s3cret&page=2', 'note' => 'contact grace@example.test'],
        );

        $this->assertSame('Upstream rejected Bearer [REDACTED] for [REDACTED]', $record->message);
        $this->assertSame('/callback?token=[REDACTED]&page=2', $record->context['url']);
        $this->assertSame('contact [REDACTED]', $record->context['note']);
    }

    #[Test]
    public function exceptions_are_summarised_and_scrubbed(): void
    {
        $record = $this->process('Unhandled', ['exception' => new RuntimeException('Bearer abc.def failed for ada@example.test')]);

        $this->assertSame(RuntimeException::class, $record->context['exception']['class']);
        $this->assertSame('Bearer [REDACTED] failed for [REDACTED]', $record->context['exception']['message']);
        $this->assertIsArray($record->context['exception']['trace']);
    }
}
