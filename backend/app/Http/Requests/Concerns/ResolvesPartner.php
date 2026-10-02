<?php

declare(strict_types=1);

namespace App\Http\Requests\Concerns;

use App\Http\Middleware\AuthenticatePartner;

/**
 * The partner id resolved by the AuthenticatePartner middleware. Every
 * request that touches partner data reads it from here, so there is one
 * place that knows where the id lives.
 */
trait ResolvesPartner
{
    public function partnerId(): string
    {
        return (string) $this->attributes->get(AuthenticatePartner::ATTRIBUTE);
    }
}
