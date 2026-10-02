<?php

declare(strict_types=1);

namespace App\Repositories\Doctrine;

use App\Entities\Application;
use App\Entities\Campaign;
use App\Enums\ApplicationSort;
use App\Repositories\ApplicationRepository;
use App\Services\Dto\ApplicationCriteria;
use Collator;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\QueryBuilder;

/**
 * All counting, averaging, filtering, sorting and paging happens in the
 * database. Only the rows for the requested page (plus at most N top-tier
 * rows) are hydrated, each with its applicant fetch-joined in the same query.
 *
 * Aliases: `a` = application, `p` = applicant (person).
 */
final class DoctrineApplicationRepository implements ApplicationRepository
{
    public function __construct(private readonly EntityManagerInterface $em)
    {
    }

    public function aggregate(Campaign $campaign, int $topTierScore): array
    {
        /** @var array{total: int|string|null, eligible: int|string|null, average: float|string|null, topTier: int|string|null} $row */
        $row = $this->em->createQueryBuilder()
            ->select(
                'COUNT(a.id) AS total',
                'SUM(CASE WHEN a.eligible = true THEN 1 ELSE 0 END) AS eligible',
                'AVG(a.aiScore) AS average',
                'SUM(CASE WHEN a.aiScore >= :topTierScore THEN 1 ELSE 0 END) AS topTier',
            )
            ->from(Application::class, 'a')
            ->where('a.campaign = :campaign')
            ->setParameter('campaign', $campaign->id())
            ->setParameter('topTierScore', $topTierScore)
            ->getQuery()
            ->getSingleResult();

        return $row;
    }

    public function topTier(Campaign $campaign, int $minScore, int $limit): array
    {
        $qb = $this->withApplicant($campaign)
            ->andWhere('a.aiScore >= :topScore')
            ->setParameter('topScore', $minScore)
            ->setMaxResults($limit);
        $this->applySort($qb, ApplicationSort::ScoreDesc);

        /** @var list<Application> */
        return $qb->getQuery()->getResult();
    }

    public function page(Campaign $campaign, ApplicationCriteria $criteria, ApplicationSort $sort, int $limit, int $offset): array
    {
        if ($sort->isNameSort()) {
            return $this->pageSortedByName($campaign, $criteria, $sort, $limit, $offset);
        }

        $qb = $this->filtered($this->withApplicant($campaign), $criteria)
            ->setFirstResult($offset)
            ->setMaxResults($limit);
        // Every sort ends with an id tiebreaker (see ApplicationSort::orderBy()),
        // otherwise equal values can shuffle between pages.
        $this->applySort($qb, $sort);

        /** @var list<Application> */
        return $qb->getQuery()->getResult();
    }

    /**
     * Names are ciphertext in the database, so ORDER BY on them is meaningless.
     * Two queries instead:
     *  1. ids + name columns of every matching application (no other columns,
     *     no entity hydration), decrypted by the column type and sorted with a
     *     locale-aware collator in PHP;
     *  2. the requested page, hydrated with its applicant, by id.
     * Cost is linear in the campaign's matching rows, which is fine for
     * per-campaign lists; a very large campaign would need a sortable
     * surrogate (TASK_REPORT.md, "Additions and decisions beyond the brief").
     *
     * @return list<Application>
     */
    private function pageSortedByName(Campaign $campaign, ApplicationCriteria $criteria, ApplicationSort $sort, int $limit, int $offset): array
    {
        $qb = $this->em->createQueryBuilder()
            ->select('a.id AS id', 'p.lastName AS lastName', 'p.firstName AS firstName', 'p.middleName AS middleName')
            ->from(Application::class, 'a')
            ->join('a.applicant', 'p')
            ->where('a.campaign = :campaign')
            ->setParameter('campaign', $campaign->id());

        /** @var list<array{id: string, lastName: string, firstName: string, middleName: string|null}> $rows */
        $rows = $this->filtered($qb, $criteria)->getQuery()->getArrayResult();

        $collator = new Collator('en');
        $direction = $sort->isDescending() ? -1 : 1;
        usort($rows, static function (array $x, array $y) use ($collator, $direction): int {
            foreach (['lastName', 'firstName', 'middleName'] as $part) {
                $cmp = (int) $collator->compare((string) $x[$part], (string) $y[$part]);
                if ($cmp !== 0) {
                    return $direction * $cmp;
                }
            }

            return strcmp($x['id'], $y['id']); // tiebreaker, always ascending
        });

        $ids = array_column(array_slice($rows, $offset, $limit), 'id');
        if ($ids === []) {
            return [];
        }

        /** @var list<Application> $page */
        $page = $this->em->createQueryBuilder()
            ->select('a', 'p')
            ->from(Application::class, 'a')
            ->join('a.applicant', 'p')
            ->where('a.id IN (:ids)')
            ->setParameter('ids', $ids)
            ->getQuery()
            ->getResult();

        $position = array_flip($ids);
        usort($page, static fn (Application $x, Application $y): int => $position[$x->id()] <=> $position[$y->id()]);

        return $page;
    }

    public function countMatching(Campaign $campaign, ApplicationCriteria $criteria): int
    {
        $qb = $this->em->createQueryBuilder()
            ->select('COUNT(a.id)')
            ->from(Application::class, 'a')
            ->where('a.campaign = :campaign')
            ->setParameter('campaign', $campaign->id());

        return (int) $this->filtered($qb, $criteria)->getQuery()->getSingleScalarResult();
    }

    public function findForPartner(string $applicationId, string $partnerId): ?Application
    {
        /** @var Application|null */
        return $this->em->createQueryBuilder()
            ->select('a', 'p', 'c')
            ->from(Application::class, 'a')
            ->join('a.applicant', 'p')
            ->join('a.campaign', 'c')
            ->where('a.id = :id')
            ->andWhere('c.partnerId = :partner')
            ->setParameter('id', $applicationId)
            ->setParameter('partner', $partnerId)
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function save(Application $application): void
    {
        $this->em->persist($application);
        $this->em->flush();
    }

    /** Applications of one campaign with the applicant fetch-joined. */
    private function withApplicant(Campaign $campaign): QueryBuilder
    {
        return $this->em->createQueryBuilder()
            ->select('a', 'p')
            ->from(Application::class, 'a')
            ->join('a.applicant', 'p')
            ->where('a.campaign = :campaign')
            ->setParameter('campaign', $campaign->id());
    }

    private function filtered(QueryBuilder $qb, ApplicationCriteria $criteria): QueryBuilder
    {
        if ($criteria->minScore !== null) {
            $qb->andWhere('a.aiScore >= :minScore')->setParameter('minScore', $criteria->minScore);
        }
        if ($criteria->status !== null) {
            $qb->andWhere('a.status = :status')->setParameter('status', $criteria->status->value);
        }
        if ($criteria->appliedFrom !== null) {
            $qb->andWhere('a.appliedAt >= :appliedFrom')
                ->setParameter('appliedFrom', $criteria->appliedFrom, Types::DATETIME_IMMUTABLE);
        }
        if ($criteria->appliedBefore() !== null) {
            $qb->andWhere('a.appliedAt < :appliedBefore')
                ->setParameter('appliedBefore', $criteria->appliedBefore(), Types::DATETIME_IMMUTABLE);
        }

        return $qb;
    }

    private function applySort(QueryBuilder $qb, ApplicationSort $sort): void
    {
        $first = true;
        foreach ($sort->orderBy() as $field => $direction) {
            $first ? $qb->orderBy($field, $direction) : $qb->addOrderBy($field, $direction);
            $first = false;
        }
    }
}
