<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Allowed values for the JSON:API `sort` query parameter on the applicant
 * summary. A leading "-" means descending, as defined by the JSON:API spec.
 * Aliases: `a` = application, `p` = applicant (person).
 */
enum ApplicationSort: string
{
    case ScoreDesc = '-aiScore';
    case ScoreAsc = 'aiScore';
    case AppliedDesc = '-appliedAt';
    case AppliedAsc = 'appliedAt';
    case NameAsc = 'name';
    case NameDesc = '-name';

    public const DEFAULT = self::ScoreDesc;

    /**
     * ORDER BY clauses (DQL field => direction). Every sort ends with the
     * application id as a tiebreaker so pagination is deterministic.
     *
     * @return array<string, 'ASC'|'DESC'>
     */
    public function orderBy(): array
    {
        return match ($this) {
            self::ScoreDesc => ['a.aiScore' => 'DESC', 'a.appliedAt' => 'ASC', 'a.id' => 'ASC'],
            self::ScoreAsc => ['a.aiScore' => 'ASC', 'a.appliedAt' => 'ASC', 'a.id' => 'ASC'],
            self::AppliedDesc => ['a.appliedAt' => 'DESC', 'a.id' => 'ASC'],
            self::AppliedAsc => ['a.appliedAt' => 'ASC', 'a.id' => 'ASC'],
            // Names are encrypted at rest, so the database cannot order them;
            // the repository sorts these in PHP (see isNameSort()).
            self::NameAsc, self::NameDesc => ['a.id' => 'ASC'],
        };
    }

    /**
     * "name" sorts like a directory: last name, then first, then middle, then
     * application id. Done in the application because the name columns hold
     * ciphertext (TASK_REPORT.md, "Additions and decisions beyond the brief").
     */
    public function isNameSort(): bool
    {
        return $this === self::NameAsc || $this === self::NameDesc;
    }

    public function isDescending(): bool
    {
        return str_starts_with($this->value, '-');
    }

    /** @return list<string> */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
