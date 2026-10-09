<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Payment providers
    |--------------------------------------------------------------------------
    |
    | Each provider entry holds the configuration a webhook adapter needs to
    | verify signatures. Real adapters are added in later phases; the "fake"
    | provider backs local development and tests.
    |
    */

    'providers' => [
        'fake' => [
            'secret' => env('FAKE_PAYMENT_PROVIDER_SECRET', 'fake-provider-secret'),
        ],
    ],

];
