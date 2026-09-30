<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Spatie\Activitylog\Support\ActivityLogStatus;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // La auditoría se prueba por separado del comportamiento funcional.
        // Desactivarla en la suite evita que los tests con SQLite en memoria
        // necesiten recrear la tabla activity_log en cada esquema mínimo.
        app(ActivityLogStatus::class)->disable();
    }
}
