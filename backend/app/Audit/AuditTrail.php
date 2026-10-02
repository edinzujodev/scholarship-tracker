<?php

declare(strict_types=1);

namespace App\Audit;

use App\Entities\AuditEntry;

interface AuditTrail
{
    /**
     * Stages an entry in the current unit of work. It is written by the same
     * flush (transaction) as the change it describes.
     */
    public function record(AuditEntry $entry): void;
}
