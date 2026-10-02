<?php

declare(strict_types=1);

namespace App\Http\Requests\Concerns;

use Illuminate\Validation\Validator;

/**
 * Rejects members a JSON:API request document must not contain, one 422 per
 * member with a JSON pointer (e.g. /data/attributes/aiScore). Without this, a
 * client could send a read-only attribute and believe it was changed.
 */
trait RejectsUnknownMembers
{
    /**
     * Allowed member names per object path ('' = top level, 'data',
     * 'data.attributes', ...).
     *
     * @return array<string, list<string>>
     */
    abstract protected function allowedMembers(): array;

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $document = $this->json()->all();

            foreach ($this->allowedMembers() as $path => $allowed) {
                $object = $path === '' ? $document : data_get($document, $path);
                if (! is_array($object) || array_is_list($object) && $object !== []) {
                    continue; // shape errors are reported by the regular rules
                }

                foreach (array_keys($object) as $member) {
                    if (! in_array((string) $member, $allowed, true)) {
                        $key = ltrim($path.'.'.$member, '.');
                        $validator->errors()->add($key, sprintf('The member "%s" is not allowed here.', $member));
                    }
                }
            }
        });
    }
}
