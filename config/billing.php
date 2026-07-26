<?php

return [
    // Master switch — set false to close the payment portal temporarily.
    'enabled' => env('BILLING_ENABLED', true),

    // While GePG credentials aren't provisioned yet, requests are handled by
    // a sandbox service that mimics the NIMR billing system's async
    // control-number assignment without calling the real API. Flip to false
    // once real credentials are in place below.
    'sandbox' => env('BILLING_SANDBOX', true),

    // NIMR Billing System (see api.md) connection details.
    'system_url' => env('BILLING_SYSTEM_URL'),
    'system_code' => env('BILLING_SYSTEM_CODE', 'TMSC-Test'),
    'api_key' => env('BILLING_API_KEY', ''),
    'bill_dept' => env('BILLING_DEPT', 'NIMR HQ'),
    'payee_name' => env('GEPG_PAYEE_NAME', 'NATIONAL INSTITUTE FOR MEDICAL RESEARCH'),
    /*
    |--------------------------------------------------------------------------
    | Callback Authentication
    |--------------------------------------------------------------------------
    | Guards the inbound /api/billing/* callbacks. The NIMR Billing System
    | dispatches both callbacks with only a Content-Type header (see
    | billing/tasks.py and billing/payment_notifications.py in its source) —
    | there is no shared secret and no way to configure one on its side. So
    | an IP allowlist is the only verification available in practice; setting
    | a token would reject every genuine callback with a 403.
    |
    | callback_allowed_ips: comma-separated IPs the billing system calls from.
    | callback_token: only usable if NIMR ever adds header support. Leave unset.
    | Neither set = checks disabled, and a warning is logged per callback.
    */
    'callback_allowed_ips' => env('BILLING_CALLBACK_ALLOWED_IPS', ''),
    'callback_token' => env('BILLING_CALLBACK_TOKEN'),
    'request_retry_minutes' => (int) env('BILLING_REQUEST_RETRY_MINUTES', 10),
    'connect_timeout' => (int) env('BILLING_CONNECT_TIMEOUT', 5),
    'request_timeout' => (int) env('BILLING_REQUEST_TIMEOUT', 20),

    /*
    |--------------------------------------------------------------------------
    | Revenue Source Mapping
    |--------------------------------------------------------------------------
    | Maps each fee_categories.key to a NIMR Billing RevenueSourceItem ID.
    |
    | The defaults are the items AJSC already uses (39-42). TMSC's fee
    | categories are a 1:1 match for AJSC's on both amount and currency, and
    | these items are fee_type=VARIABLE — the amount is taken from the
    | unit_amt_override we send per bill, not from the item — so they carry
    | TMSC's fees correctly as-is. The only thing that differs is the
    | `description` printed on the GePG bill and in NIMR's revenue reporting,
    | which still says AJSC. Ask finance to clone these four with TMSC
    | descriptions when convenient and override the IDs in .env; nothing
    | breaks in the meantime.
    |
    | Note for v2 SystemAPIKeys: the billing system scopes items to the calling
    | system via `system_access`, so these four must be linked to TMSC's
    | SystemInfo before a v2 key can use them. A v1 key is unscoped.
    */
    'mapping' => [
        'participant_east_africa' => [
            'rev_src_id' => env('REV_participant_east_africa') ?: 39,
            'description' => 'TMSC Participant Registration - East Africa',
        ],
        'participant_non_east_africa' => [
            'rev_src_id' => env('REV_participant_non_east_africa') ?: 40,
            'description' => 'TMSC Participant Registration - International',
        ],
        'student_east_africa' => [
            'rev_src_id' => env('REV_student_east_africa') ?: 41,
            'description' => 'TMSC Student Registration - East Africa',
        ],
        'student_non_east_africa' => [
            'rev_src_id' => env('REV_student_non_east_africa') ?: 42,
            'description' => 'TMSC Student Registration - International',
        ],
    ],
];
