<?php

return [
    'session_revoke_enabled' => (bool) env('WIKI_SESSION_REVOKE_ENABLED', false),
    'enabled' => (bool) env('WIKI_LINK_ENABLED', false),
    'group_sync_enabled' => (bool) env('WIKI_GROUP_SYNC_ENABLED', false),

    'endpoint' => (string) env(
        'WIKI_LINK_ENDPOINT',
        'https://192.168.37.3:8443/internal/sqa-link'
    ),

    'shared_secret' => (string) env('WIKI_LINK_SHARED_SECRET', ''),

    'ca_certificate' => env(
        'WIKI_LINK_CA_CERT',
        '/var/www/html/storage/app/wiki-link/ca.crt'
    ),
];