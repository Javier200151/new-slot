<?php

namespace App\Filament\Resources\MemberProcedureSettings\Schemas;

use App\Models\Metopa;
use App\Models\SqaGroup;
use App\Services\MemberProcedures\DiscordService;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Throwable;

class MemberProcedureSettingForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Configuración interna')
                ->description('Elementos de NewSlot utilizados automáticamente por los procedimientos.')
                ->schema([
                    Select::make('alpha_metopa_id')
                        ->label('Metopa de miembro ALPHA')
                        ->options(fn () => Metopa::query()->orderBy('name')->pluck('name', 'id'))
                        ->searchable()->preload()
                        ->helperText('Se entrega automáticamente al completar el reclutamiento.'),
                    Select::make('treasury_group_id')
                        ->label('Grupo SQA · Tesorería')
                        ->options(fn () => SqaGroup::query()->orderBy('display_order')->orderBy('name')->pluck('name', 'id'))
                        ->searchable()->preload()
                        ->helperText('Los avisos de señales, altas, reservas, reactivaciones, bajas y ceses se dirigirán a los miembros de este grupo.'),
                    Select::make('tutors_group_id')
                        ->label('Grupo SQA · Tutores')
                        ->options(fn () => SqaGroup::query()->orderBy('display_order')->orderBy('name')->pluck('name', 'id'))
                        ->searchable()->preload()
                        ->helperText('El aviso de un nuevo recluta se envía al usuario marcado como coordinador dentro de este grupo.'),
                ])->columns(3),

            Section::make('Google Sheets · registro de miembros')
                ->description('La integración usa una Service Account del servidor. Al completar reclutamiento, NewSlot crea/actualiza la fila por ID Web, la verifica y solo después permite purgar los datos personales del formulario.')
                ->schema([
                    TextInput::make('google_spreadsheet_id')
                        ->label('Spreadsheet ID')
                        ->maxLength(160)
                        ->helperText('ID de la hoja de cálculo que contiene el registro. El valor por defecto corresponde al documento MIEMBROS configurado actualmente.'),
                    TextInput::make('google_general_sheet_gid')
                        ->label('GID de la pestaña General')
                        ->maxLength(32)
                        ->helperText('ID interno de la pestaña General. GOOGLE_MEMBERS_SHEET_NAME puede fijar el nombre explícitamente desde .env.'),
                ])->columns(2),

            Section::make('ArmaSquads')
                ->description('El Squad ID se configura aquí. La API key permanece exclusivamente en el entorno del servidor.')
                ->schema([
                    TextInput::make('armasquads_squad_id')
                        ->label('Squad ID')
                        ->maxLength(80)
                        ->helperText('Identificador numérico del Squad en ArmaSquads. Con ARMASQUADS_ENABLED=true y la API key configurada, el alta/baja se automatiza.'),
                ]),


            Section::make('Discord')
                ->description('Los servidores, roles y canales se consultan directamente con el bot. Solo se guardan sus IDs; el token permanece exclusivamente en .env / secretos del servidor.')
                ->schema([
                    Select::make('discord_guild_id')
                        ->label('Servidor de Discord')
                        ->options(fn (Get $get): array => self::discordGuildOptions($get('discord_guild_id')))
                        ->searchable()
                        ->preload()
                        ->live()
                        ->afterStateUpdated(function ($state, Set $set): void {
                            $set('discord_recruit_role_id', null);
                            $set('discord_alpha_role_id', null);
                            $set('discord_reserve_role_id', null);
                            $set('discord_invite_channel_id', null);
                        })
                        ->helperText('Servidores en los que está instalado el bot. Al cambiar de servidor se limpian los roles y el canal configurados.'),
                    Select::make('discord_recruit_role_id')
                        ->label('Rol · RECLUTA')
                        ->options(fn (Get $get): array => self::discordRoleOptions(
                            $get('discord_guild_id'),
                            $get('discord_recruit_role_id'),
                        ))
                        ->searchable()
                        ->preload()
                        ->disabled(fn (Get $get): bool => blank($get('discord_guild_id')))
                        ->helperText('Rol que se asigna durante el reclutamiento. Los roles gestionados por integraciones de Discord se excluyen de la lista.'),
                    Select::make('discord_alpha_role_id')
                        ->label('Rol · ALPHA')
                        ->options(fn (Get $get): array => self::discordRoleOptions(
                            $get('discord_guild_id'),
                            $get('discord_alpha_role_id'),
                        ))
                        ->searchable()
                        ->preload()
                        ->disabled(fn (Get $get): bool => blank($get('discord_guild_id')))
                        ->helperText('Rol de miembro ACTIVO / ALPHA.'),
                    Select::make('discord_reserve_role_id')
                        ->label('Rol · RESERVA')
                        ->options(fn (Get $get): array => self::discordRoleOptions(
                            $get('discord_guild_id'),
                            $get('discord_reserve_role_id'),
                        ))
                        ->searchable()
                        ->preload()
                        ->disabled(fn (Get $get): bool => blank($get('discord_guild_id')))
                        ->helperText('Rol que se asigna a miembros en RESERVA.'),
                    Select::make('discord_invite_channel_id')
                        ->label('Canal · invitación pública')
                        ->options(fn (Get $get): array => self::discordChannelOptions(
                            $get('discord_guild_id'),
                            $get('discord_invite_channel_id'),
                        ))
                        ->searchable()
                        ->preload()
                        ->disabled(fn (Get $get): bool => blank($get('discord_guild_id')))
                        ->helperText('Canal al que apuntará la invitación pública. Se muestran automáticamente los canales compatibles con invitaciones.'),
                    TextInput::make('discord_bot_nickname')
                        ->label('Apodo del bot en el servidor')
                        ->maxLength(32)
                        ->helperText('Se aplica con el botón «Aplicar apodo del bot». Requiere el permiso Cambiar apodo.'),
                    TextInput::make('discord_alpha_nickname_prefix')
                        ->label('Prefijo de apodo ALPHA')
                        ->default('[=ALPHA=] ')
                        ->maxLength(16)
                        ->helperText('Al activar o reactivar un miembro, el bot deja el apodo como este prefijo + nick de NewSlot. Ejemplo: [=ALPHA=] Rylod.'),
                ])->columns(2),
        ]);
    }

    /** @return array<string, string> */
    private static function discordGuildOptions(?string $currentId): array
    {
        try {
            $options = app(DiscordService::class)->guildOptions();
        } catch (Throwable) {
            $options = [];
        }

        return self::withCurrentDiscordOption($options, $currentId, 'Servidor guardado');
    }

    /** @return array<string, string> */
    private static function discordRoleOptions(?string $guildId, ?string $currentId): array
    {
        if (blank($guildId)) {
            return self::withCurrentDiscordOption([], $currentId, 'Rol guardado');
        }

        try {
            $options = app(DiscordService::class)->roleOptions((string) $guildId);
        } catch (Throwable) {
            $options = [];
        }

        return self::withCurrentDiscordOption($options, $currentId, 'Rol guardado');
    }

    /** @return array<string, string> */
    private static function discordChannelOptions(?string $guildId, ?string $currentId): array
    {
        if (blank($guildId)) {
            return self::withCurrentDiscordOption([], $currentId, 'Canal guardado');
        }

        try {
            $options = app(DiscordService::class)->channelOptions((string) $guildId);
        } catch (Throwable) {
            $options = [];
        }

        return self::withCurrentDiscordOption($options, $currentId, 'Canal guardado');
    }

    /**
     * @param array<string, string> $options
     * @return array<string, string>
     */
    private static function withCurrentDiscordOption(array $options, ?string $currentId, string $fallbackLabel): array
    {
        $currentId = trim((string) $currentId);
        if ($currentId !== '' && ! array_key_exists($currentId, $options)) {
            $options = [$currentId => $fallbackLabel . ' · ' . $currentId] + $options;
        }

        return $options;
    }
}
