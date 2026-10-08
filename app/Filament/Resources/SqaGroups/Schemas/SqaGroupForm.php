<?php

namespace App\Filament\Resources\SqaGroups\Schemas;

use App\Filament\Forms\BbcodeTextarea;
use App\Models\MemberProcedureSetting;
use App\Models\SqaGroup;
use App\Services\MemberProcedures\DiscordService;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\ColorPicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;
use Throwable;

class SqaGroupForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->label('Nombre')
                    ->required()
                    ->maxLength(255),

                TextInput::make('large_name')
                    ->label('Nombre largo')
                    ->maxLength(255),

                ColorPicker::make('color')
                    ->label('Color'),

                TextInput::make('display_order')
                    ->label('Orden')
                    ->numeric()
                    ->minValue(0),

                Checkbox::make('show_in_organization')
                    ->label('Mostrar en el organigrama')
                    ->helperText('Si se desactiva, el grupo seguirá existiendo y mantendrá sus miembros, pero no aparecerá en /comunidad/organigrama.')
                    ->default(true),

                Checkbox::make('has_coordinator_role')
                    ->label('El grupo tiene figura de coordinador')
                    ->helperText('Si se desactiva, el organigrama no mostrará ningún puesto de coordinador para este grupo. Úsalo en grupos donde las decisiones se toman de forma colegiada.')
                    ->default(true),

                Select::make('discord_role_id')
                    ->label('Rol de Discord')
                    ->options(fn (?SqaGroup $record): array => self::discordRoleOptions($record?->discord_role_id))
                    ->searchable()
                    ->preload()
                    ->nullable()
                    ->helperText('Opcional. Los roles se cargan del servidor seleccionado en Config. procedimientos. Al añadir o quitar usuarios de este Grupo SQA, el sistema sincroniza automáticamente este rol en Discord.'),

                BbcodeTextarea::make('description')
                    ->label('Descripción')
                    ->rows(4)
                    ->columnSpanFull(),

                FileUpload::make('image')
                    ->label('Imagen general')
                    ->helperText('Imagen del grupo para otros usos. El organigrama utiliza el icono específico de abajo.')
                    ->image()
                    ->disk('public')
                    ->directory('sqa-groups')
                    ->visibility('public')
                    ->preserveFilenames()
                    ->deletable()
                    ->columnSpanFull(),

                FileUpload::make('icon')
                    ->label('Icono del organigrama')
                    ->helperText('Icono cuadrado o transparente que se mostrará junto al nombre del grupo en el organigrama. Puedes quitarlo desde el propio campo o usando la casilla inferior.')
                    ->image()
                    ->disk('public')
                    ->directory('sqa-groups/icons')
                    ->visibility('public')
                    ->preserveFilenames()
                    ->deletable()
                    ->columnSpanFull(),

                Checkbox::make('remove_icon')
                    ->label('Quitar el icono actual al guardar')
                    ->helperText('Úsalo si el control de subida no permite retirar correctamente un icono ya guardado. Se eliminará la referencia del grupo y el archivo del disco público.')
                    ->visible(
                        fn (?SqaGroup $record): bool => $record !== null && filled($record->icon),
                    )
                    ->dehydrated(
                        fn (?SqaGroup $record): bool => $record !== null,
                    )
                    ->default(false)
                    ->columnSpanFull(),
            ]);
    }

    /** @return array<string, string> */
    private static function discordRoleOptions(?string $currentId): array
    {
        try {
            $guildId = MemberProcedureSetting::current()->discord_guild_id;
            $options = filled($guildId)
                ? app(DiscordService::class)->roleOptions((string) $guildId)
                : [];
        } catch (Throwable) {
            $options = [];
        }

        $currentId = trim((string) $currentId);
        if ($currentId !== '' && ! array_key_exists($currentId, $options)) {
            $options = [$currentId => 'Rol guardado · ' . $currentId] + $options;
        }

        return $options;
    }
}
