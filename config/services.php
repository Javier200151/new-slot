<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],




    'discord_oauth' => [
        'client_id' => env('DISCORD_OAUTH_CLIENT_ID'),
        'client_secret' => env('DISCORD_OAUTH_CLIENT_SECRET'),
        'redirect_uri' => env('DISCORD_OAUTH_REDIRECT_URI'),
        'api_base_url' => env('DISCORD_API_BASE_URL', 'https://discord.com/api/v10'),
        'timeout' => (int) env('DISCORD_TIMEOUT', 10),
    ],

    'steam_openid' => [
        // Steam documents /openid/ as the OP endpoint, but browser authentication
        // is performed through /openid/login. Using the discovery endpoint directly
        // can make browsers download the XRDS document instead of showing login.
        'endpoint' => 'https://steamcommunity.com/openid/login',
        'timeout' => (int) env('STEAM_OPENID_TIMEOUT', 10),
    ],

    'twitch' => [
        'client_id' => env('TWITCH_CLIENT_ID'),
        'client_secret' => env('TWITCH_CLIENT_SECRET'),
    ],

    'instagram' => [
        'access_token' => env('INSTAGRAM_ACCESS_TOKEN'),
        'username' => env('INSTAGRAM_USERNAME', 'squadalpha_es'),
    ],

    'google_photos' => [
        'album_url' => env(
            'GOOGLE_PHOTOS_PUBLIC_ALBUM_URL',
            'https://photos.google.com/share/AF1QipNdq-gzduALgaiw4sLbUdtIhVqnU4BzSBXFqKgTg-PA5rADUy5nzNY9Meg2VY67Kw?key=WG9wYVUzWWxWeEFNR1YwUHctYU8wbjF6OWFkTkJn'
        ),
    ],
];
