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
                    ->formatStateUsing(function ($state) {
                        $data = is_string($state) ? json_decode($state, true) : $state;

                        if (! is_array($data)) {
                            return '';
                        }

                        $old = $data['old'] ?? [];
                        $new = $data['attributes'] ?? [];

                        $lines = [];

                        foreach ($new as $field => $newValue) {
                            $oldValue = $old[$field] ?? null;

                            $lines[] = "{$field}:";
                            $lines[] = "ANTES: " . ($oldValue ?? 'null');
                            $lines[] = "DESPUÉS: " . ($newValue ?? 'null');
                            $lines[] = "";
                        }

                        return implode("\n", $lines);
                    })
                    ->disabled(),
            ]);
    }
}