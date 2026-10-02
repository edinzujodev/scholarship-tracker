<?php

declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;

final class CampaignNotFound extends RuntimeException
{
    public static function withId(string $campaignId): self
    {
        return new self(sprintf('Campaign "%s" was not found.', $campaignId));
    }
}
