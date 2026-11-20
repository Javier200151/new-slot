<?php

return [
    // Keep disabled until the Windows SMB share has been safely mounted in Docker.
    'enabled' => env('SQA_PBO_SYNC_ENABLED', false),
    'destination' => env('SQA_PBO_SYNC_DIR', '/mnt/squadalpha-pbos'),
    'expected_share' => env('SQA_PBO_SMB_SOURCE', '//192.168.37.1/SQA_WEB_PBO'),
];
