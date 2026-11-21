<?php

return [
    // No connection to Windows is attempted until an administrator explicitly enables it.
    'enabled' => (bool) env('SQA_MC_ENABLED', false),
    'url' => env('SQA_MC_URL', 'https://192.168.37.1:8443/api/v1'),
    'secret' => env('SQA_MC_SECRET', ''),
    'ca_certificate' => env('SQA_MC_CA_CERT', '/opt/new-slot-secrets/sqa-mc-ca.crt'),
    'timeout' => max(1, min(10, (int) env('SQA_MC_TIMEOUT', 5))),
    'refresh_seconds' => max(10, (int) env('SQA_MC_REFRESH_SECONDS', 10)),
];
