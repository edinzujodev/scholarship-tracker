<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Entities\Applicant;
use App\Entities\Application;
use App\Entities\Campaign;
use DateTimeImmutable;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Entities reject invalid state wherever it comes from (API, seeders,
 * imports), not only at the HTTP boundary.
 */
final class DomainInvariantsTest extends TestCase
{
    #[Test]
    #[DataProvider('invalidEntities')]
    public function invalid_entities_cannot_be_constructed(callable $build, string $message): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage($message);

        $build();
    }

    /** @return iterable<string, array{callable, string}> */
    public static function invalidEntities(): iterable
    {
        $campaign = static fn () => new Campaign('partner-demo', 'Scholars');
        $person = static fn () => new Applicant('Ada', 'Lovelace', 'ada@example.test');
        $application = static fn (int $score = 90, array $highlights = []) => new Application($person(), $campaign(), $score, true, $highlights, new DateTimeImmutable());

        yield 'campaign without a name' => [static fn () => new Campaign('partner-demo', '   '), 'Campaign name is required.'];
        yield 'campaign name too long' => [static fn () => new Campaign('partner-demo', str_repeat('x', 256)), 'Campaign name must be at most 255 characters.'];
        yield 'campaign without a partner' => [static fn () => new Campaign('', 'Scholars'), 'Partner id is required.'];
        yield 'partner id too long' => [static fn () => new Campaign(str_repeat('p', 65), 'Scholars'), 'Partner id must be at most 64 characters.'];
        yield 'id that is not a UUID' => [static fn () => new Campaign('partner-demo', 'Scholars', 'campaign-1'), 'is not a valid UUID'];
        yield 'first name too long' => [static fn () => new Applicant(str_repeat('a', 101), 'Lovelace', 'ada@example.test'), 'First name must be at most 100 characters.'];
        yield 'middle name too long' => [static fn () => new Applicant('Ada', 'Lovelace', 'ada@example.test', str_repeat('m', 101)), 'Middle name must be at most 100 characters.'];
        yield 'missing last name' => [static fn () => new Applicant('Ada', '', 'ada@example.test'), 'Last name is required.'];
        yield 'invalid email' => [static fn () => new Applicant('Ada', 'Lovelace', 'not-an-email'), 'Email must be a valid address'];
        yield 'score below 0' => [static fn () => $application(-1), 'AI score must be between 0 and 100.'];
        yield 'score above 100' => [static fn () => $application(101), 'AI score must be between 0 and 100.'];
        yield 'highlights as a map' => [static fn () => $application(90, ['a' => 'Strong']), 'AI highlights must be a list'];
        yield 'too many highlights' => [static fn () => $application(90, array_fill(0, 21, 'Strong')), 'AI highlights must be a list of at most 20 items.'];
        yield 'empty highlight' => [static fn () => $application(90, ['  ']), 'AI highlights item is required.'];
        yield 'non-text highlight' => [static fn () => $application(90, [42]), 'AI highlights may only contain text.'];
        yield 'highlight too long' => [static fn () => $application(90, [str_repeat('h', 501)]), 'AI highlights item must be at most 500 characters.'];
    }

    #[Test]
    public function valid_input_is_normalised(): void
    {
        $campaign = new Campaign(' partner-demo ', ' Scholars ', '9B2F0F7E-8D1C-4B2A-9D59-6C1F1B7F7A01');
        $applicant = new Applicant(' Ada ', ' Lovelace ', ' ADA@Example.TEST ', '  ');
        $application = new Application($applicant, $campaign, 100, true, [' Strong STEM background '], new DateTimeImmutable());

        $this->assertSame('partner-demo', $campaign->partnerId());
        $this->assertSame('Scholars', $campaign->name());
        $this->assertSame('9b2f0f7e-8d1c-4b2a-9d59-6c1f1b7f7a01', $campaign->id());
        $this->assertSame('ada@example.test', $applicant->email());
        $this->assertNull($applicant->middleName());
        $this->assertSame(['Strong STEM background'], $application->aiHighlights());
    }
}
