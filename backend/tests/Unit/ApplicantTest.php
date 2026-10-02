<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Entities\Applicant;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class ApplicantTest extends TestCase
{
    #[Test]
    public function the_middle_name_is_optional_and_skipped_in_the_full_name(): void
    {
        $applicant = new Applicant('Ada', 'Lovelace', 'ada@example.test');

        $this->assertNull($applicant->middleName());
        $this->assertSame('Ada Lovelace', $applicant->fullName());
    }

    #[Test]
    public function a_middle_name_appears_between_first_and_last(): void
    {
        $applicant = new Applicant('Ada', 'Lovelace', 'ada@example.test', middleName: 'King');

        $this->assertSame('Ada King Lovelace', $applicant->fullName());
    }

    #[Test]
    public function names_are_trimmed_and_a_blank_middle_name_becomes_null(): void
    {
        $applicant = new Applicant('  Ada ', ' Lovelace ', ' ADA@Example.TEST ', middleName: '   ');

        $this->assertSame('Ada', $applicant->firstName());
        $this->assertSame('Lovelace', $applicant->lastName());
        $this->assertNull($applicant->middleName());
        $this->assertSame('ada@example.test', $applicant->email());
    }

    #[Test]
    public function first_and_last_name_are_required(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new Applicant('Ada', '  ', 'ada@example.test');
    }
}
