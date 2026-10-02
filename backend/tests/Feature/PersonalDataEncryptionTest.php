<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Entities\Applicant;
use App\Security\Pii\BlindIndex;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use PHPUnit\Framework\Attributes\Test;
use Tests\Support\BuildsApplications;
use Tests\Support\RefreshDoctrineSchema;
use Tests\TestCase;

final class PersonalDataEncryptionTest extends TestCase
{
    use RefreshDoctrineSchema;
    use BuildsApplications;

    #[Test]
    public function names_and_email_are_stored_as_ciphertext_and_read_back_as_plaintext(): void
    {
        $applicant = new Applicant(firstName: 'Ada', lastName: 'Lovelace', email: 'ada@example.test', middleName: 'King');
        $this->em->persist($applicant);
        $this->em->flush();

        $row = $this->em->getConnection()->fetchAssociative('SELECT * FROM applicants WHERE id = ?', [$applicant->id()]);
        foreach (['first_name', 'middle_name', 'last_name', 'email'] as $column) {
            $this->assertStringStartsWith('v1.', $row[$column], "{$column} must be encrypted");
        }
        $this->assertStringNotContainsStringIgnoringCase('ada', implode('|', $row));
        $this->assertStringNotContainsStringIgnoringCase('lovelace', implode('|', $row));
        $this->assertSame(app(BlindIndex::class)->of(Applicant::EMAIL_INDEX_FIELD, 'ada@example.test'), $row['email_index']);

        $this->em->clear();
        $reloaded = $this->em->find(Applicant::class, $applicant->id());
        $this->assertSame('Ada King Lovelace', $reloaded->fullName());
        $this->assertSame('ada@example.test', $reloaded->email());
    }

    #[Test]
    public function the_blind_index_finds_an_applicant_by_email_without_decrypting(): void
    {
        $applicant = new Applicant(firstName: 'Ada', lastName: 'Lovelace', email: 'ada@example.test');
        $this->em->persist($applicant);
        $this->em->flush();
        $this->em->clear();

        $found = $this->em->getRepository(Applicant::class)->findOneBy([
            'emailIndex' => app(BlindIndex::class)->of(Applicant::EMAIL_INDEX_FIELD, ' ADA@example.TEST'),
        ]);

        $this->assertSame($applicant->id(), $found?->id());
    }

    #[Test]
    public function the_same_email_in_a_different_case_is_rejected_as_a_duplicate(): void
    {
        $this->em->persist(new Applicant(firstName: 'Ada', lastName: 'Lovelace', email: 'ada@example.test'));
        $this->em->flush();

        $this->expectException(UniqueConstraintViolationException::class);
        $this->em->persist(new Applicant(firstName: 'Ada', lastName: 'Byron', email: 'ADA@Example.test'));
        $this->em->flush();
    }

    #[Test]
    public function the_api_still_returns_plaintext_names_and_sorts_and_pages_by_name(): void
    {
        $campaign = $this->campaign();
        foreach (['Yara Young', 'Bob Brown', 'Eve Evans', 'Cara Clark', 'Ann Adams', 'Dan Davis'] as $i => $name) {
            $this->application($campaign, 60 + $i, name: $name);
        }

        $page1 = $this->getJson($this->summaryUrl($campaign->id(), 'sort=name&page[size]=4'), $this->authHeaders())->assertOk();
        $page2 = $this->getJson($this->summaryUrl($campaign->id(), 'sort=name&page[size]=4&page[number]=2'), $this->authHeaders())->assertOk();

        $this->assertSame(['Ann Adams', 'Bob Brown', 'Cara Clark', 'Dan Davis'], $this->applicantNames($page1));
        $this->assertSame(['Eve Evans', 'Yara Young'], $this->applicantNames($page2));
        $page2->assertJsonPath('meta.page.total', 6);
    }
}
