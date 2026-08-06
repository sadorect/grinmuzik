<?php

/*
|--------------------------------------------------------------------------
| Google Play Billing
|--------------------------------------------------------------------------
|
| Play Billing cannot price dynamically. Every product is registered in Play
| Console ahead of time at a fixed price, so the app's "artist picks any
| amount" model cannot be expressed in the Android app at all. The tiers below
| are the fixed set of products that exist in Play Console; an artist choosing
| to lock a track picks one of these rather than typing a number.
|
| The web (desktop browser, non-TWA) keeps the free-form Stripe flow — there is
| no Play involvement there — so `amount` stays the source of truth on the
| Donation row and these tiers are only the in-app on-ramp to it.
|
| IMPORTANT: product_id values must match the in-app products created in Play
| Console EXACTLY. A mismatch fails at purchase time, not at boot, so keep this
| file and Play Console in lockstep.
|
*/

return [

    'enabled' => (bool) env('PLAY_BILLING_ENABLED', false),

    'package_name' => env('PLAY_PACKAGE_NAME', 'com.grinmuzik.app'),

    /*
    | Path to the Google service account JSON used to call the Play Developer
    | API for purchase verification. Keep it OUTSIDE the document root.
    | Verification is mandatory: a client-side purchase result is just a string
    | until the server has confirmed it with Google.
    */
    'service_account_json' => env('PLAY_SERVICE_ACCOUNT_JSON'),

    /*
    | Google auto-refunds any purchase not acknowledged within 3 days. The job
    | that acknowledges runs well inside that, but this is the hard ceiling the
    | monitoring should alert against.
    */
    'acknowledgement_deadline_hours' => 72,

    /*
    | Prices are in the platform currency (NGN). Play Console holds the real
    | per-country pricing; these values exist so the server can reconcile a
    | product_id back to an expected amount and record the correct ledger
    | entry. Keep them in sync with Play Console.
    */
    'currency' => env('PLAY_BILLING_CURRENCY', 'NGN'),

    'tiers' => [

        // One-time purchase granting permanent access to a single track.
        //
        // The lowest tier must not fall below CurrencyHelper::minimum() for the
        // platform currency (NGN 1000), or the app would sell through Play at a
        // price the web flow rejects as too small.
        'unlock' => [
            'unlock_tier_1000'  => 1000,
            'unlock_tier_2000'  => 2000,
            'unlock_tier_5000'  => 5000,
            'unlock_tier_10000' => 10000,
            'unlock_tier_20000' => 20000,
        ],

        // Voluntary gift to an artist. Grants no entitlement — a gift never
        // unlocks anything, which is why it is kept separate from unlock
        // rather than reusing the same products.
        'gift' => [
            'gift_tier_1000'  => 1000,
            'gift_tier_2000'  => 2000,
            'gift_tier_5000'  => 5000,
            'gift_tier_10000' => 10000,
            'gift_tier_20000' => 20000,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Revenue split
    |--------------------------------------------------------------------------
    |
    | Google pays the developer only — there is no equivalent of a Stripe
    | Connect destination charge, so the artist's share becomes a liability the
    | platform owes and settles separately. These rates decide what is written
    | to the ledger.
    |
    | provider_fee_rate is Google's cut. It is recorded rather than assumed so
    | the ledger reflects what actually happened; 0.15 is the common rate but
    | it varies by programme and region, so treat this as an estimate until
    | reconciled against a Play payout report.
    |
    */
    'provider_fee_rate' => (float) env('PLAY_PROVIDER_FEE_RATE', 0.15),

    'platform_fee_rate' => (float) env('PLATFORM_FEE_RATE', 0.10),
];
