<?php
// config/stripe.php

return [
    /*
    |--------------------------------------------------------------------------
    | Stripe API Keys
    |--------------------------------------------------------------------------
    |
    | The Stripe publishable key and secret key can be generated from
    | the Stripe dashboard. These keys are used to interact with the Stripe API.
    |
    */

    'public_key' => env('STRIPE_KEY', ''),
    'secret_key' => env('STRIPE_SECRET', ''),

    /*
    |--------------------------------------------------------------------------
    | Stripe Webhook Secret
    |--------------------------------------------------------------------------
    |
    | The Stripe webhook secret is used to validate that the webhook
    | requests are coming from Stripe.
    |
    */

    'webhook_secret' => env('STRIPE_WEBHOOK_SECRET', ''),

    /*
    |--------------------------------------------------------------------------
    | Default Currency
    |--------------------------------------------------------------------------
    |
    | This is the default currency that will be used when generating charges
    | from your application. Of course, you are welcome to use any of the
    | various currencies supported by Stripe.
    |
    */

    'currency' => env('STRIPE_CURRENCY', 'usd'),

    /*
    |--------------------------------------------------------------------------
    | Payment Mode
    |--------------------------------------------------------------------------
    |
    | This setting helps determine which payment method to use by default.
    | Available options: 'payment_intents', 'payment_methods'.
    |
    */

    'payment_mode' => 'payment_intents',

    /*
    |--------------------------------------------------------------------------
    | Logging
    |--------------------------------------------------------------------------
    |
    | Enable or disable logging of Stripe API calls. This is useful for debugging.
    |
    */

    'log_api_calls' => env('STRIPE_LOG_API_CALLS', false),
];
