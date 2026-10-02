<?php

declare(strict_types=1);

namespace App\Entities;

use App\Entities\Concerns\HasCreatedAt;
use App\Entities\Concerns\HasUuid;
use App\Support\Guard;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'campaigns')]
#[ORM\Index(name: 'idx_campaigns_partner', columns: ['partner_id'])]
class Campaign
{
    use HasUuid;
    use HasCreatedAt;

    public function __construct(
        #[ORM\Column(name: 'partner_id', type: 'string', length: 64)]
        private string $partnerId,
        #[ORM\Column(type: 'string', length: 255)]
        private string $name,
        ?string $id = null,
    ) {
        // Lengths match the column definitions above.
        $this->partnerId = Guard::requiredText($partnerId, 'Partner id', 64);
        $this->name = Guard::requiredText($name, 'Campaign name', 255);
        $this->assignId($id);
        $this->stampCreatedAt();
    }

    public function partnerId(): string
    {
        return $this->partnerId;
    }

    public function name(): string
    {
        return $this->name;
    }
}
