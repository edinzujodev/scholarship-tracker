<?php

declare(strict_types=1);

namespace App\Http\JsonApi;

use Illuminate\Http\Request;

/** JSON:API pagination links that keep every other query parameter (filters, sort). */
final class PaginationLinks
{
    /** @return array{self: string, first: string, prev: string|null, next: string|null, last: string} */
    public static function for(Request $request, int $page, int $size, int $lastPage): array
    {
        $base = $request->url();
        $params = $request->query();

        $to = static function (int $number) use ($base, $params, $size): string {
            $params['page'] = ['size' => $size, 'number' => $number];

            return $base.'?'.http_build_query($params);
        };

        return [
            'self' => $to($page),
            'first' => $to(1),
            'prev' => $page > 1 ? $to(min($page - 1, $lastPage)) : null,
            'next' => $page < $lastPage ? $to($page + 1) : null,
            'last' => $to($lastPage),
        ];
    }
}
