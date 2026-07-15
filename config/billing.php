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
    | Revenue Source Mapping
    |--------------------------------------------------------------------------
    | Maps each fee_categories.key to the NIMR Billing RevenueSourceItem ID
    | that must be pre-registered with NIMR finance for TMSC. These IDs are
    | not known yet — leave null until finance provisions them; requests
    | will fail loudly (not silently) if a category is used before then.
    */
    'mapping' => [
        'participant_east_africa' => [
            'rev_src_id' => env('REV_participant_east_africa'),
            'description' => 'TMSC Participant Registration - East Africa',
        ],
        'participant_non_east_africa' => [
            'rev_src_id' => env('REV_participant_non_east_africa'),
            'description' => 'TMSC Participant Registration - International',
        ],
        'student_east_africa' => [
            'rev_src_id' => env('REV_student_east_africa'),
            'description' => 'TMSC Student Registration - East Africa',
        ],
        'student_non_east_africa' => [
            'rev_src_id' => env('REV_student_non_east_africa'),
            'description' => 'TMSC Student Registration - International',
        ],
    ],
];
