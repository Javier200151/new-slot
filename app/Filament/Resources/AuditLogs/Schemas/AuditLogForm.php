<?php

namespace App\Filament\Resources\AuditLogs\Schemas;

use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class AuditLogForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('event')
                    ->label('Tipo de cambio')
                    ->disabled(),

                TextInput::make('subject_type')
                    ->label('Modelo afectado')
                    ->formatStateUsing(fn ($state) => class_basename($state))
                    ->disabled(),

                TextInput::make('subject_id')
                    ->label('ID afectado')
                    ->disabled(),

                DateTimePicker::make('created_at')
                    ->label('Fecha y hora')
                    ->disabled(),

                TextInput::make('ip_address')
                    ->label('IP')
                    ->disabled(),

                Textarea::make('user_agent')
                    ->label('Navegador / Dispositivo')
                    ->rows(3)
                    ->disabled(),

                Textarea::make('url')
                    ->label('URL')
                    ->rows(2)
                    ->disabled(),
                TextInput::make('log_name')
                    ->label('Registro')
                    ->disabled(),

                TextInput::make('actor_nick')
                ->label('Usuario que hizo el cambio')
                ->formatStateUsing(
                    function ($state, $record): string {
                        if (filled($state)) {
                            return (string) $state;
                        }

                        if (
                            $record?->causer
                            && filled($record->causer->nick)
                        ) {
                            return (string) $record->causer->nick;
                        }

                        return 'Sistema';
                    }
                )
                ->disabled(),
                TextInput::make('subject_table')
                    ->label('Tabla afectada')
                    ->disabled(),

                TextInput::make('subject_label')
                    ->label('Objeto afectado')
                    ->disabled(),

                TextInput::make('source')
                    ->label('Origen')
                    ->disabled(),

                TextInput::make('request_method')
                    ->label('Método HTTP')
                    ->disabled(),

                TextInput::make('route_name')
                    ->label('Ruta Laravel')
                    ->disabled(),

                TextInput::make('correlation_id')
                    ->label('Correlation ID')
                    ->disabled(),
                Textarea::make('attribute_changes')
                    ->label('Cambios realizados')
                    ->rows(14)
                    ->formatStateUsing(
                        function ($state, $record): string {
                            /*
                            |--------------------------------------------------------------------------
                            | Normalizar datos
                            |--------------------------------------------------------------------------
                            */

                            if (
                                $state === null
                                || $state === ''
                            ) {
                                return 'Sin cambios registrados.';
                            }


                            if (is_array($state)) {
                                $data = $state;

                            } elseif (is_string($state)) {
                                $decoded =
                                    json_decode(
                                        $state,
                                        true
                                    );

                                $data =
                                    is_array($decoded)
                                        ? $decoded
                                        : [];

                            } elseif (
                                is_object($state)
                                && method_exists(
                                    $state,
                                    'toArray'
                                )
                            ) {
                                $data =
                                    $state->toArray();

                            } elseif (is_object($state)) {
                                $data =
                                    (array) $state;

                            } else {
                                $data = [];
                            }


                            if ($data === []) {
                                return 'Sin cambios registrados.';
                            }


                            /*
                            |--------------------------------------------------------------------------
                            | Valores anteriores y posteriores
                            |--------------------------------------------------------------------------
                            */

                            $old =
                                is_array(
                                    $data['old']
                                    ?? null
                                )
                                    ? $data['old']
                                    : [];

                            $new =
                                is_array(
                                    $data['attributes']
                                    ?? null
                                )
                                    ? $data['attributes']
                                    : [];


                            /*
                            * Todos los campos que aparezcan
                            * en ANTES o DESPUÉS.
                            */
                            $fields =
                                array_values(
                                    array_unique([
                                        ...array_keys($old),
                                        ...array_keys($new),
                                    ])
                                );


                            if ($fields === []) {
                                return 'Sin cambios registrados.';
                            }


                            /*
                            |--------------------------------------------------------------------------
                            | Formateador de valores
                            |--------------------------------------------------------------------------
                            */

                            $formatValue =
                                function ($value): string {
                                    if ($value === null) {
                                        return 'NULL';
                                    }

                                    if (is_bool($value)) {
                                        return $value
                                            ? 'true'
                                            : 'false';
                                    }

                                    if (
                                        is_array($value)
                                        || is_object($value)
                                    ) {
                                        $json =
                                            json_encode(
                                                $value,
                                                JSON_PRETTY_PRINT
                                                | JSON_UNESCAPED_UNICODE
                                                | JSON_UNESCAPED_SLASHES
                                            );

                                        return $json !== false
                                            ? $json
                                            : '[valor no representable]';
                                    }

                                    return (string) $value;
                                };


                            /*
                            |--------------------------------------------------------------------------
                            | Construcción del detalle
                            |--------------------------------------------------------------------------
                            */

                            $lines = [];


                            foreach ($fields as $field) {
                                $oldExists =
                                    array_key_exists(
                                        $field,
                                        $old
                                    );

                                $newExists =
                                    array_key_exists(
                                        $field,
                                        $new
                                    );


                                $lines[] =
                                    strtoupper($field);


                                /*
                                * CREACIÓN
                                */
                                if (
                                    $record->event === 'created'
                                ) {
                                    $lines[] =
                                        'CREADO: '
                                        . (
                                            $newExists
                                                ? $formatValue(
                                                    $new[$field]
                                                )
                                                : 'NULL'
                                        );
                                }

                                /*
                                * ELIMINACIÓN
                                */
                                elseif (
                                    $record->event === 'deleted'
                                ) {
                                    $lines[] =
                                        'ANTES DE ELIMINAR: '
                                        . (
                                            $oldExists
                                                ? $formatValue(
                                                    $old[$field]
                                                )
                                                : 'NULL'
                                        );
                                }

                                /*
                                * CUALQUIER OTRO CAMBIO
                                */
                                else {
                                    $lines[] =
                                        'ANTES: '
                                        . (
                                            $oldExists
                                                ? $formatValue(
                                                    $old[$field]
                                                )
                                                : 'NULL'
                                        );

                                    $lines[] =
                                        'DESPUÉS: '
                                        . (
                                            $newExists
                                                ? $formatValue(
                                                    $new[$field]
                                                )
                                                : 'NULL'
                                        );
                                }


                                $lines[] = '';
                            }


                            return implode(
                                PHP_EOL,
                                $lines
                            );
                        }
                    )
                    ->disabled(),
                    Textarea::make('properties')
                    ->label('Información técnica')
                    ->rows(16)
                    ->formatStateUsing(
                        function ($state): string {
                            /*
                            |--------------------------------------------------------------------------
                            | Sin información
                            |--------------------------------------------------------------------------
                            */

                            if (
                                $state === null
                                || $state === ''
                            ) {
                                return 'Sin información adicional.';
                            }


                            /*
                            |--------------------------------------------------------------------------
                            | Convertir a array
                            |--------------------------------------------------------------------------
                            */

                            if (is_array($state)) {
                                /*
                                * Spatie / Filament puede entregarlo
                                * directamente como array.
                                */
                                $data = $state;

                            } elseif (is_string($state)) {
                                /*
                                * O puede venir como JSON.
                                */
                                $decoded =
                                    json_decode(
                                        $state,
                                        true
                                    );

                                $data =
                                    is_array($decoded)
                                        ? $decoded
                                        : [
                                            'value' => $state,
                                        ];

                            } elseif (
                                is_object($state)
                                && method_exists(
                                    $state,
                                    'toArray'
                                )
                            ) {
                                /*
                                * Collection u otro objeto
                                * convertible a array.
                                */
                                $data =
                                    $state->toArray();

                            } elseif (is_object($state)) {
                                /*
                                * Cualquier otro objeto.
                                */
                                $data =
                                    (array) $state;

                            } else {
                                $data = [
                                    'value' => $state,
                                ];
                            }


                            /*
                            |--------------------------------------------------------------------------
                            | Mostrar JSON legible
                            |--------------------------------------------------------------------------
                            */

                            $json =
                                json_encode(
                                    $data,
                                    JSON_PRETTY_PRINT
                                    | JSON_UNESCAPED_UNICODE
                                    | JSON_UNESCAPED_SLASHES
                                );


                            return $json !== false
                                ? $json
                                : 'No se pudo representar la información técnica.';
                        }
                    )
                    ->disabled()
                    ->columnSpanFull(),
            ]);
    }
}