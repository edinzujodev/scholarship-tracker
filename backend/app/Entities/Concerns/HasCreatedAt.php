<?php

declare(strict_types=1);

namespace App\Entities\Concerns;

use DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;

/** Immutable creation timestamp, stamped when the entity is constructed. */
trait HasCreatedAt
{
    #[ORM\Column(name: 'created_at', type: 'datetime_immutable')]
    private DateTimeImmutable $createdAt;

    public function createdAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }

    private function stampCreatedAt(): void
    {
        $this->createdAt = new DateTimeImmutable();
    }
}
