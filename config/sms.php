<?php

return [
    // Master switch — false disables every outbound SMS. Email is unaffected.
    'enabled' => env('SMS_ENABLED', false),

    /*
    | eGA has not issued TMSC's mGov gateway credentials yet, so while this is
    | true nothing is sent over the wire — messages are logged instead, with
    | the exact recipient and body that would have gone out. Flip to false once
    | the credentials below are in place.
    */
    'sandbox' => env('SMS_SANDBOX', true),

    /*
    |--------------------------------------------------------------------------
    | mGov SMS Gateway (see "SMS Documentation/code-example.php")
    |--------------------------------------------------------------------------
    | Requests are signed with HMAC-SHA256 over the exact JSON body using the
    | api_key, base64-encoded into a `hash` header alongside a `sysId` header.
    | api_key, system_id, and mobile_service_id all come from eGA when the
    | application is registered on the mGov Portal.
    */
    'url' => env('SMS_GATEWAY_URL', 'https://mgov.gov.go.tz/gateway/sms/v2/send-sms'),
    'api_key' => env('SMS_API_KEY', ''),
    'system_id' => env('SMS_SYSTEM_ID', ''),
    'mobile_service_id' => env('SMS_MOBILE_SERVICE_ID', ''),
    'sender_id' => env('SMS_SENDER_ID', '15200'),

    'connect_timeout' => (int) env('SMS_CONNECT_TIMEOUT', 5),
    'request_timeout' => (int) env('SMS_REQUEST_TIMEOUT', 20),

    /*
    |--------------------------------------------------------------------------
    | Per-event toggles
    |--------------------------------------------------------------------------
    | Each key matches a template below and a method on SmsNotifier. Turning one
    | off stops that message without touching code — useful for keeping the
    | per-SMS spend down once volumes are known.
    */
    'events' => [
        'registered' => env('SMS_ON_REGISTERED', true),
        'control_number' => env('SMS_ON_CONTROL_NUMBER', true),
        'payment_confirmed' => env('SMS_ON_PAYMENT_CONFIRMED', true),
        'abstract_decision' => env('SMS_ON_ABSTRACT_DECISION', true),
    ],

    /*
    |--------------------------------------------------------------------------
    | Message templates
    |--------------------------------------------------------------------------
    | Placeholders are :name style and filled by SmsNotifier. Keep each under
    | 160 characters — the mGov gateway bills per 160-character part, and long
    | messages split unpredictably across handsets.
    |
    | To add a new message (a payment reminder, a venue announcement), add a
    | template here, a matching key in `events` above, and a method on
    | App\Services\Sms\SmsNotifier that renders it. Nothing else needs to change.
    */
    'templates' => [
        'registered' => 'TMSC 2026: Hi :name, your registration is received. Sign in at :url any time to check your status.',
        'control_number' => 'TMSC 2026: Control number :control_number. Pay :currency :amount via bank or mobile money (GePG) to complete your registration.',
        'payment_confirmed' => 'TMSC 2026: Payment received and your registration is confirmed. Thank you, we look forward to seeing you at the conference.',
        // No abstract title in these three. Titles are long, and transliterating
        // the Greek and accented characters common in scientific titles down to
        // the 7-bit SMS alphabet mangles them ("beta-glucan" arrives as
        // "v-glucan"). The registrant knows what they submitted, and the site
        // names the abstract if they have more than one.
        'abstract_accepted' => 'TMSC 2026: Good news, your abstract has been accepted. Sign in at :url for the reviewers\' feedback and presentation details.',
        'abstract_rejected' => 'TMSC 2026: The decision on your abstract is ready. Sign in at :url to read the reviewers\' feedback.',
        'abstract_revision_requested' => 'TMSC 2026: Your abstract needs revision before it can be accepted. Sign in at :url to read the feedback and resubmit.',
    ],
];
