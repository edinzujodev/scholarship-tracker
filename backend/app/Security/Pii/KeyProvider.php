<?php

declare(strict_types=1);

namespace App\Security\Pii;

/**
 * Supplies the data keys for personal-data encryption. Production uses
 * Cloud KMS (envelope encryption: wrapped data keys, unwrapped by KMS at
 * startup); local development and tests use LocalKeyProvider.
 */
interface KeyProvider
{
    public function keys(): KeyMaterial;
}
