<?php

namespace App\Support;

use Illuminate\Support\Str;

class AuditContext
{
    private ?string $correlationId = null;

    public function start(?string $correlationId = null): string
    {
        $this->correlationId =
            $correlationId ?: (string) Str::uuid();

        return $this->correlationId;
    }

    public function id(): string
    {
        return $this->correlationId
            ?? $this->start();
    }
}