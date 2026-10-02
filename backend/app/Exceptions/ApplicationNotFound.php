<?php

declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;

final class ApplicationNotFound extends RuntimeException
{
    public static function withId(string $applicationId): self
    {
        return new self(sprintf('Application "%s" was not found.', $applicationId));
    }
}
