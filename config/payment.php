<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Payment providers
    |--------------------------------------------------------------------------
    |
    | Configuration for the provider adapters behind
    | PaymentProviderInterface / CollectableProvider / PayoutProviderInterface.
    | The "fake" provider backs local development and tests.
    |
    | Endpoints mirror the merchant sandboxes used by the industry references
    | (myanmar-payments). Swap them for the live values your
    | merchant agreements provide. Payout (disbursement) adapters are
    | config-gated: they refuse to run until a payout endpoint is configured.
    |
    */

    // Where the customer is sent after a payment attempt completes.
    'redirect_url' => env('PAYMENT_REDIRECT_URL', ''),

    'providers' => [
        'fake' => [
            'secret' => env('FAKE_PAYMENT_PROVIDER_SECRET', 'fake-provider-secret'),
        ],

        'kbzpay' => [
            'app_id' => env('KBZ_PAY_APP_ID'),
            'merchant_code' => env('KBZ_PAY_MERCHANT_CODE'),
            'secret' => env('KBZ_PAY_SECRET'),
            'pay_type' => env('KBZ_PAY_PAY_TYPE', 'pwa'),
            'title' => env('KBZ_PAY_TITLE', 'Payment'),
            'timeout_express' => env('KBZ_PAY_TIMEOUT_EXPRESS', '120m'),
            'timeout' => (int) env('KBZ_PAY_TIMEOUT', 30),
            'endpoints' => [
                'precreate' => env('KBZ_PAY_ENDPOINT_PRECREATE', 'https://api.kbzpay.com/payment/gateway/precreate'),
                'queryorder' => env('KBZ_PAY_ENDPOINT_QUERYORDER', 'https://api.kbzpay.com/payment/gateway/queryorder'),
                'refund' => env('KBZ_PAY_ENDPOINT_REFUND', 'https://api.kbzpay.com:8008/payment/gateway/refund'),
                'pwa' => env('KBZ_PAY_ENDPOINT_PWA', 'https://static.kbzpay.com/pgw/pwa'),
            ],
            'versions' => [
                'precreate' => env('KBZ_PAY_VERSION_PRECREATE', '1.0'),
                'queryorder' => env('KBZ_PAY_VERSION_QUERYORDER', '3.0'),
            ],
            'payout' => [
                'endpoint' => env('KBZ_PAY_PAYOUT_ENDPOINT'),
            ],
        ],

        'wavemoney' => [
            'merchant_id' => env('WAVE_MONEY_MERCHANT_ID'),
            'merchant_name' => env('WAVE_MONEY_MERCHANT_NAME'),
            'secret_key' => env('WAVE_MONEY_SECRET_KEY'),
            'payment_description' => env('WAVE_MONEY_PAYMENT_DESCRIPTION', 'Payment'),
            'time_to_live_in_seconds' => (int) env('WAVE_MONEY_TTL_SECONDS', 60),
            'timeout' => (int) env('WAVE_MONEY_TIMEOUT', 30),
            'endpoints' => [
                'payment' => env('WAVE_MONEY_ENDPOINT_PAYMENT', 'https://testpayments.wavemoney.io:8107/payment'),
                'authenticate' => env('WAVE_MONEY_ENDPOINT_AUTHENTICATE', 'https://testpayments.wavemoney.io/authenticate'),
            ],
            'payout' => [
                'endpoint' => env('WAVE_MONEY_PAYOUT_ENDPOINT'),
            ],
        ],

        'aya' => [
            // Shared key for the `apikey: Basic ...` login header.
            'basic_token' => env('AYA_PAY_BASIC_TOKEN'),
            'phone' => env('AYA_PAY_PHONE'),
            'password' => env('AYA_PAY_PASSWORD'),
            'service_code' => env('AYA_PAY_SERVICE_CODE'),
            'encrypt_key' => env('AYA_PAY_ENCRYPT_KEY'),
            'time_limit' => env('AYA_PAY_TIME_LIMIT'),
            'timeout' => (int) env('AYA_PAY_TIMEOUT', 30),
            'endpoints' => [
                'login' => env('AYA_PAY_ENDPOINT_LOGIN', 'https://opensandbox.ayainnovation.com/merchant/1.0.0/thirdparty/merchant/login'),
                'push_payment' => env('AYA_PAY_ENDPOINT_PUSH_PAYMENT', 'https://opensandbox.ayainnovation.com/merchant/1.0.0/thirdparty/merchant/requestPushPayment'),
                'push_payment_v2' => env('AYA_PAY_ENDPOINT_PUSH_PAYMENT_V2', 'https://opensandbox.ayainnovation.com/merchant/1.0.0/thirdparty/merchant/v2/requestPushPayment'),
                'qr_payment' => env('AYA_PAY_ENDPOINT_QR_PAYMENT', 'https://opensandbox.ayainnovation.com/merchant/1.0.0/thirdparty/merchant/requestQRPayment'),
                'query_payment' => env('AYA_PAY_ENDPOINT_QUERY_PAYMENT', 'https://opensandbox.ayainnovation.com/merchant/1.0.0/thirdparty/merchant/checkRequestPayment'),
            ],
            'payout' => [
                'endpoint' => env('AYA_PAY_PAYOUT_ENDPOINT'),
            ],
        ],

        '2c2p' => [
            'merchant_id' => env('TWOC2P_MERCHANT_ID'),
            'secret_key' => env('TWOC2P_SECRET_KEY'),
            'locale' => env('TWOC2P_LOCALE', 'en'),
            'maintenance_version' => env('TWOC2P_MAINTENANCE_VERSION', '4.3'),
            'timeout' => (int) env('TWOC2P_TIMEOUT', 30),
            'endpoints' => [
                'payment_token' => env('TWOC2P_ENDPOINT_PAYMENT_TOKEN', 'https://sandbox-pgw.2c2p.com/payment/4.3/paymentToken'),
                'transaction_status' => env('TWOC2P_ENDPOINT_TRANSACTION_STATUS', 'https://sandbox-pgw.2c2p.com/payment/4.3/transactionStatus'),
            ],
            'payout' => [
                'endpoint' => env('TWOC2P_PAYOUT_ENDPOINT'),
            ],
        ],
    ],

];
