<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Telegram
    |--------------------------------------------------------------------------
    |
    | Bots are managed from the Instant Messaging settings page: each bot row
    | carries its own token and webhook secret. The default token is only a
    | fallback for outbound delivery that is not tied to a specific bot.
    |
    */
    'telegram' => [
        'default_token' => env('AGENTOOM_TELEGRAM_BOT_TOKEN'),
    ],

    /*
    |--------------------------------------------------------------------------
    | WhatsApp Cloud API
    |--------------------------------------------------------------------------
    |
    | Credentials are entered on the Instant Messaging settings page. The two
    | secrets below verify that an inbound webhook really came from Meta.
    |
    */
    'whatsapp' => [
        'verify_token' => env('AGENTOOM_WHATSAPP_VERIFY_TOKEN'),
        'app_secret' => env('AGENTOOM_WHATSAPP_APP_SECRET'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Inbound Deduplication
    |--------------------------------------------------------------------------
    |
    | Telegram and WhatsApp both retry webhooks. A repeat of the same message
    | is ignored for this long, so a retry cannot produce a second AI reply.
    |
    */
    'dedupe_ttl_minutes' => 60,
];
