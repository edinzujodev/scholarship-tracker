<?php

declare(strict_types=1);

namespace App\Entities;

use App\Doctrine\Types\EncryptedStringType;
use App\Entities\Concerns\HasCreatedAt;
use App\Entities\Concerns\HasUuid;
use App\Security\Pii\BlindIndex;
use App\Support\Guard;
use Doctrine\ORM\Mapping as ORM;
use LogicException;

/**
 * A person. Holds only who they are; everything about a submission to a
 * specific campaign (score, status, highlights, date) lives on Application,
 * so the same applicant can apply to several campaigns.
 *
 * Personal data is encrypted at rest (TASK_REPORT.md, "Additions and decisions beyond the brief"): names and email are
 * `encrypted_string` columns, and the email is unique through a keyed blind
 * index instead of the plaintext. Code using this entity sees plaintext.
 */
#[ORM\Entity]
#[ORM\Table(name: 'applicants')]
#[ORM\UniqueConstraint(name: 'uniq_applicants_email_index', columns: ['email_index'])]
class Applicant
{
    use HasUuid;
    use HasCreatedAt;

    /** Blind-index field name: fingerprints are scoped to this field. */
    public const EMAIL_INDEX_FIELD = 'applicants.email';

    #[ORM\Column(name: 'first_name', type: EncryptedStringType::NAME)]
    private string $firstName;

    #[ORM\Column(name: 'middle_name', type: EncryptedStringType::NAME, nullable: true)]
    private ?string $middleName;

    #[ORM\Column(name: 'last_name', type: EncryptedStringType::NAME)]
    private string $lastName;

    #[ORM\Column(type: EncryptedStringType::NAME)]
    private string $email;

    /** HMAC-SHA256 of the normalised email (hex), set before INSERT by ApplicantEmailIndexer. */
    #[ORM\Column(name: 'email_index', type: 'string', length: 64, options: ['fixed' => true])]
    private ?string $emailIndex = null;

    public function __construct(
        string $firstName,
        string $lastName,
        string $email,
        ?string $middleName = null,
        ?string $id = null,
    ) {
        // Plaintext limits (the encrypted columns are TEXT).
        $this->firstName = Guard::requiredText($firstName, 'First name', 100);
        $this->lastName = Guard::requiredText($lastName, 'Last name', 100);
        $this->middleName = Guard::optionalText($middleName, 'Middle name', 100);
        $this->email = Guard::email($email);
        $this->assignId($id);
        $this->stampCreatedAt();
    }

    public function firstName(): string
    {
        return $this->firstName;
    }

    public function middleName(): ?string
    {
        return $this->middleName;
    }

    public function lastName(): string
    {
        return $this->lastName;
    }

    /** Display name: "First Middle Last", skipping an empty middle name. */
    public function fullName(): string
    {
        return implode(' ', array_filter([$this->firstName, $this->middleName, $this->lastName]));
    }

    public function email(): string
    {
        return $this->email;
    }

    public function emailIndex(): string
    {
        return $this->emailIndex ?? throw new LogicException('The email blind index is set when the applicant is persisted.');
    }

    /** Called by ApplicantEmailIndexer; the index key is a service, not entity data. */
    public function indexEmail(BlindIndex $index): void
    {
        $this->emailIndex = $index->of(self::EMAIL_INDEX_FIELD, $this->email);
    }
}
