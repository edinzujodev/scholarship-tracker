<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Personal data encryption (TASK_REPORT.md, "Additions and decisions beyond the brief")
|--------------------------------------------------------------------------
|
| Applicant names and email are stored encrypted (AES-256-GCM) with a keyed
| blind index for the email. Data keys come from:
|
|   cloud_kms  production. Envelope encryption: the data keys are stored
|              *wrapped* (encrypted by a Cloud KMS key) and unwrapped through
|              the KMS API once per process. The KMS key never leaves KMS.
|
|   local      development and tests only (refused in production). Keys from
|              PII_LOCAL_KEYS / PII_LOCAL_INDEX_KEY, or derived from APP_KEY
|              when those are empty, so a fresh checkout works out of the box.
|
*/

return [
    'driver' => env('PII_KEY_DRIVER', 'local'),

    'local' => [
        // "id:base64key[,id:base64key...]"; the first entry is the current key.
        'keys' => env('PII_LOCAL_KEYS', ''),
        'index_key' => env('PII_LOCAL_INDEX_KEY', ''),
    ],

    'cloud_kms' => [
        // projects/<project>/locations/<location>/keyRings/<ring>/cryptoKeys/<key>
        'key_name' => env('PII_KMS_KEY_NAME', ''),
        // "id:base64wrappedKey[,id:...]" (output of `gcloud kms encrypt`); first is current.
        'wrapped_keys' => env('PII_KMS_WRAPPED_KEYS', ''),
        'wrapped_index_key' => env('PII_KMS_WRAPPED_INDEX_KEY', ''),
        'endpoint' => env('PII_KMS_ENDPOINT', 'https://cloudkms.googleapis.com/v1'),
        // On Cloud Run / GKE / GCE the token comes from the metadata server.
        // For local experiments an access token can be given directly
        // (e.g. `gcloud auth print-access-token`).
        'access_token' => env('PII_KMS_ACCESS_TOKEN', ''),
        'metadata_token_url' => env(
            'PII_KMS_METADATA_TOKEN_URL',
            'http://metadata.google.internal/computeMetadata/v1/instance/service-accounts/default/token',
        ),
    ],
];
