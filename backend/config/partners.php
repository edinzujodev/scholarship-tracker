<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Mock partner authentication
|--------------------------------------------------------------------------
|
| Stand-in for real OAuth2 token introspection: each demo partner has one
| static bearer token. The partner ids match the seeded campaigns.
|
| To change a token, set it in .env (optional):
|   PARTNER_DEMO_TOKEN=...   (partner-demo: three campaigns)
|   PARTNER_OTHER_TOKEN=...  (partner-other: Northbridge Bursary)
|
| Tokens must be non-empty and different; the app refuses to boot otherwise.
| Never commit real tokens here.
|
*/

return [
    // partner id => bearer token
    'partners' => [
        'partner-demo' => env('PARTNER_DEMO_TOKEN', 'demo-partner-token'),
        'partner-other' => env('PARTNER_OTHER_TOKEN', 'other-partner-token'),
    ],
];
