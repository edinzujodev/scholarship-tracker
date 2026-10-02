<?php

declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;

/** JSON:API requires 409 when the document's type or id disagrees with the endpoint. */
final class ResourceConflict extends RuntimeException
{
}
