<?php
return [
    'enabled' => (bool) env('WIKI_LINK_ENABLED', false),
    'endpoint' => (string) env('WIKI_LINK_ENDPOINT', 'https://wiki.squadalpha.es/internal/sqa-link'),
    'shared_secret' => (string) env('WIKI_LINK_SHARED_SECRET', ''),
];
