<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Invoice PDF hosting
    |--------------------------------------------------------------------------
    |
    | Business decision (owner, 2026-07): hovera.app hosts the tenant
    | invoice PDF on its own storage for the calendar year it was issued
    | in, plus a 1-month grace period (an invoice issued in year Y stays
    | hosted through the end of January of year Y+1). After that cutoff
    | the API stops serving the file and points the customer at KSeF
    | instead — see `App\Services\Invoicing\InvoicePdfStorageService`.
    |
    | Disk is deliberately env-driven (not hardcoded to "local") so the
    | backend can move to S3/R2 later without a code change: point
    | INVOICE_PDF_DISK at "s3" and configure the existing `s3` disk in
    | config/filesystems.php (for Cloudflare R2, set AWS_ENDPOINT to the
    | R2 endpoint + AWS_USE_PATH_STYLE_ENDPOINT=true — R2 is S3-compatible).
    |
    */
    'pdf' => [
        'disk' => env('INVOICE_PDF_DISK', 'local'),
        'retention_grace_months' => 1,
    ],

    /*
    |--------------------------------------------------------------------------
    | KSeF portal (post-retention redirect)
    |--------------------------------------------------------------------------
    |
    | Once the local PDF copy expires, the API response points customers at
    | the KSeF taxpayer web portal for their environment instead of a file.
    |
    | NOTE: these are base/home URLs for the KSeF web portal, mirroring the
    | hostname convention already used for the API hosts in
    | `App\Services\Ksef\CentralKsefService::HOST_TEST/DEMO/PROD`. We do
    | NOT have verified knowledge of a deep-link query format that opens a
    | specific invoice directly (it may require NIP + amount + date, and
    | may have changed over time) — do not bolt one on without confirming
    | it with the product owner first. Until then, the API exposes the raw
    | `ksef_reference_number` alongside this base URL so the customer (or
    | a future, verified deep-link) can look the invoice up themselves.
    |
    */
    'ksef' => [
        'portal_url' => [
            'test' => env('KSEF_PORTAL_URL_TEST', 'https://ksef-test.mf.gov.pl'),
            'demo' => env('KSEF_PORTAL_URL_DEMO', 'https://ksef-demo.mf.gov.pl'),
            'production' => env('KSEF_PORTAL_URL_PROD', 'https://ksef.mf.gov.pl'),
        ],
    ],

];
