<?php

declare(strict_types=1);

namespace App\Doctrine\Listeners;

use App\Entities\Applicant;
use App\Security\Pii\BlindIndex;
use Doctrine\ORM\Event\PrePersistEventArgs;

/**
 * Computes the applicant's email blind index before the first INSERT. The
 * entity cannot do it itself because the index key is a service, not data.
 * (Applicant has no way to change its email after creation, so prePersist is
 * the only moment the index can be set.)
 */
final class ApplicantEmailIndexer
{
    public function __construct(private readonly BlindIndex $index)
    {
    }

    public function prePersist(PrePersistEventArgs $args): void
    {
        $entity = $args->getObject();
        if ($entity instanceof Applicant) {
            $entity->indexEmail($this->index);
        }
    }
}
