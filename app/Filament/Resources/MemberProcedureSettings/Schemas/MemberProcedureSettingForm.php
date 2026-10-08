<?php

namespace App\Filament\Resources\MemberProcedureSettings\Schemas;

use App\Filament\Resources\MemberProcedureSettings\Support\MemberProcedureSettingActions;
use App\Models\ActivityType;
use App\Models\EventStatus;
use App\Models\Metopa;
use App\Models\MemberProcedureSetting;
use App\Models\SqaGroup;
use App\Services\MemberProcedures\CommunicationPreviewService;
use App\Services\MemberProcedures\DiscordService;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\View;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Throwable;

class MemberProcedureSettingForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Herramientas de integración')
                ->description('Acciones de diagnóstico y mantenimiento de las integraciones. Guarda primero cualquier cambio de configuración antes de probarlo.')
                ->schema([
                    Actions::make(MemberProcedureSettingActions::discord())
                        ->label('Discord')
                        ->fullWidth()
                        ->extraAttributes(['class' => 'ns-procedure-tool-actions ns-procedure-tool-actions--discord']),
                    Actions::make(MemberProcedureSettingActions::telegram())
                        ->label('Telegram')
                        ->fullWidth()
                        ->extraAttributes(['class' => 'ns-procedure-tool-actions ns-procedure-tool-actions--telegram']),
                    Actions::make(MemberProcedureSettingActions::googleSheets())
                        ->label('Google Sheets')
                        ->fullWidth()
                        ->extraAttributes(['class' => 'ns-procedure-tool-actions ns-procedure-tool-actions--google']),
                ])
                ->columns([
                    'default' => 1,
                    'lg' => 3,
                ])
                ->columnSpanFull()
                ->extraAttributes(['class' => 'ns-procedure-tools-section']),

            Grid::make([
                'default' => 1,
                'lg' => 2,
            ])
                ->schema([
                    Grid::make(1)
                        ->schema([
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

                            Section::make('ArmaSquads')
                                ->description('El Squad ID se configura aquí. La API key permanece exclusivamente en el entorno del servidor.')
                                ->schema([
                                    TextInput::make('armasquads_squad_id')
                                        ->label('Squad ID')
                                        ->maxLength(80)
                                        ->helperText('Identificador numérico del Squad en ArmaSquads. Con ARMASQUADS_ENABLED=true y la API key configurada, el alta/baja se automatiza.'),
                                ]),

                            Section::make('TeamSpeak 3')
                                ->description('Gestión manual dentro de los Procedimientos. No se utilizan ServerQuery, WebQuery, credenciales ni identificadores TS3 en NewSlot.')
                                ->schema([
                                    Placeholder::make('_teamspeak_manual_mode')
                                        ->hiddenLabel()
                                        ->content('Los procedimientos incluyen tareas manuales para asignar RECLUTA, cambiar RECLUTA/RESERVA a ALPHA, pasar ALPHA a RESERVA y retirar los grupos de TeamSpeak en NO PROMOCIONADO, BAJA o CESE. Cada tarea se confirma desde el checklist del procedimiento.'),
                                ]),

                            Section::make('Telegram')
                                ->description('El bot publica por ahora únicamente en = ALPHA FORCE NETWORK =. Los tres enlaces de invitación se guardan manualmente y se reutilizan en los correos automáticos. Los reclutas siguen estando únicamente en WhatsApp.')
                                ->schema([
                                    TextInput::make('telegram_network_chat_id')
                                        ->label('Destino de mensajes · = ALPHA FORCE NETWORK =')
                                        ->maxLength(80)
                                        ->helperText('ID interno del canal/grupo de Telegram donde el bot publicará los mensajes automáticos (normalmente empieza por -100…). No es el enlace de invitación ni el @usuario del bot. «Detectar chats Telegram» puede ayudarte a localizarlo.'),
                                    TextInput::make('telegram_cantina_invite_url')
                                        ->label('Invitación · ALPHA Cantina')
                                        ->url()
                                        ->maxLength(500)
                                        ->placeholder('https://t.me/+…')
                                        ->live(debounce: 600)
                                        ->helperText('Enlace de invitación vigente que se incluirá en los correos automáticos.'),
                                    TextInput::make('telegram_official_invite_url')
                                        ->label('Invitación · ALPHA Oficial')
                                        ->url()
                                        ->maxLength(500)
                                        ->placeholder('https://t.me/+…')
                                        ->live(debounce: 600)
                                        ->helperText('Enlace de invitación vigente que se incluirá en los correos automáticos.'),
                                    TextInput::make('telegram_network_invite_url')
                                        ->label('Invitación · = ALPHA FORCE NETWORK =')
                                        ->url()
                                        ->maxLength(500)
                                        ->placeholder('https://t.me/+…')
                                        ->live(debounce: 600)
                                        ->helperText('Enlace de invitación vigente que se incluirá en los correos automáticos.'),
                                ])
                                ->columns(2),

                            Section::make('Telegram · mensajes automáticos')
                                ->description('Plantillas enviadas a = ALPHA FORCE NETWORK =. Admiten MarkdownV2. Los cierres aleatorios funcionan como una ruleta: si hay varios, NewSlot escoge uno al realizar el envío real.')
                                ->schema([
                                    Textarea::make('telegram_recruit_update_template')
                                        ->label('Plantilla · actualización de reclutas')
                                        ->rows(6)
                                        ->maxLength(6000)
                                        ->live(debounce: 600)
                                        ->helperText('Variables: {{nick}}, {{tipo_cambio}}, {{cambio}}, {{cierre}}. Se usa tanto al entrar un nuevo recluta como al finalizar como NO PROMOCIONADO.')
                                        ->columnSpanFull(),
                                    Repeater::make('telegram_recruit_entry_endings')
                                        ->label('Cierres aleatorios · nuevo recluta')
                                        ->schema([
                                            Textarea::make('text')
                                                ->label('Texto')
                                                ->rows(2)
                                                ->maxLength(1000)
                                                ->helperText('Puedes usar {{nick}}.'),
                                        ])
                                        ->defaultItems(0)
                                        ->reorderable()
                                        ->collapsible()
                                        ->live()
                                        ->itemLabel(fn (array $state): ?string => filled($state['text'] ?? null) ? mb_strimwidth((string) $state['text'], 0, 70, '…') : 'Cierre')
                                        ->columnSpanFull(),
                                    Repeater::make('telegram_recruit_exit_endings')
                                        ->label('Cierres aleatorios · recluta no promocionado')
                                        ->schema([
                                            Textarea::make('text')
                                                ->label('Texto')
                                                ->rows(2)
                                                ->maxLength(1000)
                                                ->helperText('Puedes usar {{nick}}.'),
                                        ])
                                        ->defaultItems(0)
                                        ->reorderable()
                                        ->collapsible()
                                        ->live()
                                        ->itemLabel(fn (array $state): ?string => filled($state['text'] ?? null) ? mb_strimwidth((string) $state['text'], 0, 70, '…') : 'Cierre')
                                        ->columnSpanFull(),
                                    Textarea::make('telegram_veterancy_template')
                                        ->label('Plantilla · veteranías')
                                        ->rows(7)
                                        ->maxLength(6000)
                                        ->live(debounce: 600)
                                        ->helperText('Variables: {{foro_url}}, {{veteranias}}, {{cierre}}. {{veteranias}} agrupa automáticamente oro, plata y bronce.')
                                        ->columnSpanFull(),
                                    Repeater::make('telegram_veterancy_endings')
                                        ->label('Cierres aleatorios · veteranías')
                                        ->schema([
                                            Textarea::make('text')->label('Texto')->rows(2)->maxLength(1000),
                                        ])
                                        ->defaultItems(0)
                                        ->reorderable()
                                        ->collapsible()
                                        ->live()
                                        ->itemLabel(fn (array $state): ?string => filled($state['text'] ?? null) ? mb_strimwidth((string) $state['text'], 0, 70, '…') : 'Cierre')
                                        ->columnSpanFull(),
                                ]),

                            Section::make('Telegram · actividad semanal')
                                ->description('El botón del Dashboard se habilita cada domingo a las 00:00 y se deshabilita el lunes a las 18:00. Revisa la semana que comienza ese lunes.')
                                ->schema([
                                    Select::make('telegram_weekly_required_weekdays')
                                        ->label('Días obligatorios')
                                        ->multiple()
                                        ->options([
                                            1 => 'Lunes',
                                            2 => 'Martes',
                                            3 => 'Miércoles',
                                            4 => 'Jueves',
                                            5 => 'Viernes',
                                            6 => 'Sábado',
                                        ])
                                        ->default([2, 5])
                                        ->required()
                                        ->live()
                                        ->helperText('Si falta una actividad válida en cualquiera de estos días, NewSlot no permite enviar la actividad semanal.'),
                                    Select::make('telegram_weekly_required_activity_type_ids')
                                        ->label('Tipos válidos en días obligatorios')
                                        ->multiple()
                                        ->options(fn () => ActivityType::query()->orderBy('name')->pluck('name', 'id'))
                                        ->searchable()
                                        ->preload()
                                        ->required()
                                        ->helperText('Por ejemplo OPERATIVO. Un día obligatorio debe tener al menos un evento ACTIVO de uno de estos tipos.'),
                                    Select::make('telegram_weekly_active_event_status_id')
                                        ->label('Estado que cuenta como ACTIVO')
                                        ->options(fn () => EventStatus::query()->orderBy('name')->pluck('name', 'id'))
                                        ->searchable()
                                        ->preload()
                                        ->required()
                                        ->helperText('Solo los eventos con este estado aparecen en el mensaje. Los demás se muestran como aviso o bloqueo según el día.'),
                                    Textarea::make('telegram_weekly_template')
                                        ->label('Plantilla · actividad semanal')
                                        ->rows(7)
                                        ->maxLength(8000)
                                        ->live(debounce: 600)
                                        ->helperText('Variables: {{eventos}}, {{semana_inicio}}, {{semana_fin}}, {{cierre}}. Los días obligatorios se resaltan automáticamente en negrita.')
                                        ->columnSpanFull(),
                                    Repeater::make('telegram_weekly_endings')
                                        ->label('Cierres aleatorios · actividad semanal')
                                        ->schema([
                                            Textarea::make('text')->label('Texto')->rows(2)->maxLength(1000),
                                        ])
                                        ->defaultItems(0)
                                        ->reorderable()
                                        ->collapsible()
                                        ->live()
                                        ->itemLabel(fn (array $state): ?string => filled($state['text'] ?? null) ? mb_strimwidth((string) $state['text'], 0, 70, '…') : 'Cierre')
                                        ->columnSpanFull(),
                                ])
                                ->columns(3)
                        ])
                        ->columnSpan(1),

                    Grid::make(1)
                        ->schema([
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

                            Section::make('Emails automáticos')
                                ->description('Los correos de alta y reactivación tienen un diseño fijo de Squad ALPHA. El texto se edita desde la propia previsualización; no se puede modificar el estilo, las tarjetas ni la estructura.')
                                ->schema([
                                    View::make('filament.components.member-email-previews')
                                        ->columnSpanFull(),
                                ]),

                            Section::make('Previsualización Telegram')
                                ->description('Simulación visual de = ALPHA FORCE NETWORK =. No envía nada al bot. Los datos son de ejemplo y la previsualización utiliza el primer cierre configurado; en el envío real se escoge uno al azar.')
                                ->schema([
                                    Select::make('_telegram_preview_kind')
                                        ->label('Mensaje a previsualizar')
                                        ->options([
                                            'recruit_entry' => 'Entrada de recluta',
                                            'recruit_exit' => 'Recluta no promocionado',
                                            'veterancy' => 'Veteranías',
                                            'weekly' => 'Actividad semanal',
                                        ])
                                        ->default('recruit_entry')
                                        ->dehydrated(false)
                                        ->live(),
                                    View::make('filament.components.telegram-message-preview')
                                        ->viewData(fn (Get $get): array => [
                                            'preview' => app(CommunicationPreviewService::class)->telegramPreview(
                                                self::previewSetting($get),
                                                (string) ($get('_telegram_preview_kind') ?: 'recruit_entry'),
                                            ),
                                        ])
                                        ->columnSpanFull(),
                                ])
                        ])
                        ->columnSpan(1),
                ])
                ->columnSpanFull(),
        ]);
    }

    private static function previewSetting(Get $get): MemberProcedureSetting
    {
        return new MemberProcedureSetting([
            'telegram_network_chat_id' => $get('telegram_network_chat_id'),
            'telegram_cantina_invite_url' => $get('telegram_cantina_invite_url'),
            'telegram_official_invite_url' => $get('telegram_official_invite_url'),
            'telegram_network_invite_url' => $get('telegram_network_invite_url'),
            'member_welcome_email_subject' => $get('member_welcome_email_subject'),
            'member_welcome_email_body' => $get('member_welcome_email_body'),
            'reactivation_email_subject' => $get('reactivation_email_subject'),
            'reactivation_email_body' => $get('reactivation_email_body'),
            'telegram_recruit_update_template' => $get('telegram_recruit_update_template'),
            'telegram_recruit_entry_endings' => $get('telegram_recruit_entry_endings') ?? [],
            'telegram_recruit_exit_endings' => $get('telegram_recruit_exit_endings') ?? [],
            'telegram_veterancy_template' => $get('telegram_veterancy_template'),
            'telegram_veterancy_endings' => $get('telegram_veterancy_endings') ?? [],
            'telegram_weekly_template' => $get('telegram_weekly_template'),
            'telegram_weekly_endings' => $get('telegram_weekly_endings') ?? [],
            'telegram_weekly_required_weekdays' => $get('telegram_weekly_required_weekdays') ?? [2, 5],
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
