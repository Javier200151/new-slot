<?php

namespace App\Support;

use Illuminate\Support\Str;

class AuditContext
{
    private ?string $correlationId = null;

    private ?string $systemProcess = null;

    /**
     * Las escrituras institucionales no deben atribuirse a quien inició
     * el procedimiento. Restaurar el contexto siempre, incluso ante errores.
     */
    public function asSystem(string $process, callable $callback): mixed
    {
        $previous = $this->systemProcess;
        $this->systemProcess = $process;

        try {
            return $callback();
        } finally {
            $this->systemProcess = $previous;
        }
    }

    public function systemProcess(): ?string
    {
        return $this->systemProcess;
    }


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