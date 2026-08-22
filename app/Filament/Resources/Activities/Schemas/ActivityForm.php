<?php

namespace App\Filament\Resources\Activities\Schemas;

use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class ActivityForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('causer.nick')
                    ->label('Usuario que hizo el cambio')
                    ->disabled(),

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

                Textarea::make('attribute_changes')
                    ->label('Cambios realizados')
                    ->rows(14)
                    ->formatStateUsing(function ($state, $record) {
                        $data = is_string($state)
                            ? json_decode($state, true)
                            : $state;

                        if (! is_array($data)) {
                            return 'Sin datos registrados.';
                        }

                        $old = $data['old'] ?? [];
                        $new = $data['attributes'] ?? [];

                        /*
                        * Unimos todos los campos existentes
                        * en antes y después.
                        */
                        $fields = array_unique([
                            ...array_keys($old),
                            ...array_keys($new),
                        ]);

                        /*
                        * updated_at aporta muy poca información
                        * visual y ensucia el detalle.
                        *
                        * Sigue estando guardado en BD.
                        */
                        $fields = array_filter(
                            $fields,
                            fn ($field) =>
                                $field !== 'updated_at'
                        );

                        $formatValue = function ($value): string {
                            if ($value === null) {
                                return 'NULL';
                            }

                            if (is_bool($value)) {
                                return $value
                                    ? 'true'
                                    : 'false';
                            }

                            if (is_array($value) || is_object($value)) {
                                return json_encode(
                                    $value,
                                    JSON_PRETTY_PRINT
                                    | JSON_UNESCAPED_UNICODE
                                    | JSON_UNESCAPED_SLASHES
                                );
                            }

                            return (string) $value;
                        };

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


                            $lines[] = strtoupper($field);


                            /*
                            * CREACIÓN
                            */
                            if (
                                $record->event === 'created'
                                && $newExists
                            ) {
                                $lines[] =
                                    'CREADO: '
                                    . $formatValue(
                                        $new[$field]
                                    );
                            }

                            /*
                            * ELIMINACIÓN
                            */
                            elseif (
                                $record->event === 'deleted'
                                && $oldExists
                            ) {
                                $lines[] =
                                    'ANTES DE ELIMINAR: '
                                    . $formatValue(
                                        $old[$field]
                                    );
                            }

                            /*
                            * UPDATE / RESTORE
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
                    })
                    ->disabled(),
            ]);
    }
}