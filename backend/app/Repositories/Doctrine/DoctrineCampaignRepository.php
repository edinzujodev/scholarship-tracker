<?php

declare(strict_types=1);

namespace App\Repositories\Doctrine;

use App\Entities\Application;
use App\Entities\Campaign;
use App\Enums\ApplicationStatus;
use App\Repositories\CampaignRepository;
use App\Services\Dto\CampaignOverview;
use Doctrine\ORM\EntityManagerInterface;

final class DoctrineCampaignRepository implements CampaignRepository
{
    public function __construct(private readonly EntityManagerInterface $em)
    {
    }

    public function findForPartner(string $campaignId, string $partnerId): ?Campaign
    {
        return $this->em->getRepository(Campaign::class)->findOneBy([
            'id' => $campaignId,
            'partnerId' => $partnerId,
        ]);
    }

    public function overviewForPartner(string $partnerId, int $limit, int $offset): array
    {
        /** @var list<array{0: Campaign, applicantCount: int|string, eligibleCount: int|string|null, averageAiScore: float|string|null, pendingCount: int|string|null, shortlistedCount: int|string|null, rejectedCount: int|string|null}> $rows */
        $rows = $this->em->createQueryBuilder()
            ->select(
                'c',
                'COUNT(a.id) AS applicantCount',
                'SUM(CASE WHEN a.eligible = true THEN 1 ELSE 0 END) AS eligibleCount',
                'AVG(a.aiScore) AS averageAiScore',
                'SUM(CASE WHEN a.status = :pending THEN 1 ELSE 0 END) AS pendingCount',
                'SUM(CASE WHEN a.status = :shortlisted THEN 1 ELSE 0 END) AS shortlistedCount',
                'SUM(CASE WHEN a.status = :rejected THEN 1 ELSE 0 END) AS rejectedCount',
            )
            ->from(Campaign::class, 'c')
            ->leftJoin(Application::class, 'a', 'WITH', 'a.campaign = c')
            ->where('c.partnerId = :partner')
            ->groupBy('c.id')
            ->orderBy('c.createdAt', 'DESC')
            ->addOrderBy('c.name', 'ASC')
            ->addOrderBy('c.id', 'ASC')
            ->setParameter('partner', $partnerId)
            ->setParameter('pending', ApplicationStatus::Pending->value)
            ->setParameter('shortlisted', ApplicationStatus::Shortlisted->value)
            ->setParameter('rejected', ApplicationStatus::Rejected->value)
            ->setFirstResult($offset)
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();

        return array_map(
            static fn (array $row): CampaignOverview => CampaignOverview::fromRow($row[0], $row),
            $rows,
        );
    }

    public function countForPartner(string $partnerId): int
    {
        return (int) $this->em->createQueryBuilder()
            ->select('COUNT(c.id)')
            ->from(Campaign::class, 'c')
            ->where('c.partnerId = :partner')
            ->setParameter('partner', $partnerId)
            ->getQuery()
            ->getSingleScalarResult();
    }
}
