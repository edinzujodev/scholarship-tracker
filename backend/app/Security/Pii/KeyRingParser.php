<?php

declare(strict_types=1);

namespace App\Security\Pii;

use Closure;
use RuntimeException;

/** Parses "id:value[,id:value...]" key ring settings; the first entry is the current key. */
final class KeyRingParser
{
    /**
     * @param Closure(string): string $toKey turns the configured value into raw key bytes
     * @return array<string, string> id => raw key, in configured order
     */
    public static function parse(string $setting, Closure $toKey): array
    {
        $ring = [];
        foreach (array_filter(array_map('trim', explode(',', $setting))) as $entry) {
            [$id, $value] = array_pad(explode(':', $entry, 2), 2, '');
            if ($id === '' || $value === '') {
                throw new RuntimeException('PII key ring entries must look like "id:value".');
            }
            if (isset($ring[$id])) {
                throw new RuntimeException("PII key id \"{$id}\" appears twice.");
            }
            $ring[$id] = $toKey($value);
        }
        if ($ring === []) {
            throw new RuntimeException('The PII key ring is empty.');
        }

        return $ring;
    }
}
