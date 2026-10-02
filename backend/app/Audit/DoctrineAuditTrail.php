<?php

declare(strict_types=1);

namespace App\Audit;

use App\Entities\AuditEntry;
use Doctrine\ORM\EntityManagerInterface;

final class DoctrineAuditTrail implements AuditTrail
{
    public function __construct(private readonly EntityManagerInterface $em)
    {
    }

    public function record(AuditEntry $entry): void
    {
        $this->em->persist($entry);
    }
}
