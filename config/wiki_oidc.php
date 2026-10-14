<?php

return [
    'enabled' => (bool) env('WIKI_OIDC_ENABLED', false),
    'issuer' => rtrim((string) env('WIKI_OIDC_ISSUER', 'https://www.squadalpha.es'), '/'),
    'client_id' => (string) env('WIKI_OIDC_CLIENT_ID', 'bookstack'),
    'client_secret' => (string) env('WIKI_OIDC_CLIENT_SECRET', ''),
    'redirect_uri' => (string) env('WIKI_OIDC_REDIRECT_URI', 'https://wiki.squadalpha.es/oidc/callback'),
    'private_key_path' => (string) env('WIKI_OIDC_PRIVATE_KEY_PATH', storage_path('app/wiki-oidc/private.pem')),
    'public_key_path' => (string) env('WIKI_OIDC_PUBLIC_KEY_PATH', storage_path('app/wiki-oidc/public.pem')),
    'key_id' => (string) env('WIKI_OIDC_KEY_ID', 'wiki-key-v1'),
];
