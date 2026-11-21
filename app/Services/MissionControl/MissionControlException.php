<?php

namespace App\Services\MissionControl;

use RuntimeException;

final class MissionControlException extends RuntimeException
{
    public function __construct(
        string $message,
        public readonly string $errorCode = 'conexion',
        public readonly int $httpStatus = 0,
        public readonly array $detail = [],
    ) {
        parent::__construct($message);
    }
}
