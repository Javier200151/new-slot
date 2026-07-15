<?php

namespace App\Filament\Resources\Operations\Pages;

use App\Filament\Resources\Operations\OperationResource;
use App\Models\Addon;
use App\Models\AddonPreset;
use App\Models\Army;
use App\Models\RadioModel;
use App\Models\SlotType;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Illuminate\Support\Str;

class EditOperation extends EditRecord
{
    protected static string $resource = OperationResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('editDescription')
                ->label('Editar descripción')
                ->modalHeading('Editor de descripción')
                ->modalSubmitActionLabel('Guardar descripción')
                ->modalWidth('7xl')
                ->fillForm(function (): array {
                    $description = $this->record->description ?? [];
                    $sections = $description['sections'] ?? [];

                    if (blank($sections) && filled($description['content'] ?? null)) {
                        $sections = [
                            [
                                'title' => 'Descripción',
                                'content' => $description['content'],
                                'images' => [],
                            ],
                        ];
                    }

                    return ['sections' => $sections];
                })
                ->form([
                    Repeater::make('sections')
                        ->label('Secciones')
                        ->schema([
                            TextInput::make('title')
                                ->label('Título')
                                ->required()
                                ->maxLength(255),

                            RichEditor::make('content')
                                ->label('Contenido')
                                ->columnSpanFull(),

                            Repeater::make('images')
                                ->label('Imágenes externas')
                                ->schema([
                                    TextInput::make('url')
                                        ->label('URL')
                                        ->url()
                                        ->required()
                                        ->maxLength(2048),

                                    TextInput::make('caption')
                                        ->label('Pie de imagen')
                                        ->maxLength(255),
                                ])
                                ->columns(2)
                                ->itemLabel(fn (array $state): ?string => $state['caption'] ?? $state['url'] ?? null)
                                ->default([])
                                ->addActionLabel('Añadir imagen')
                                ->collapsible()
                                ->columnSpanFull(),
                        ])
                        ->itemLabel(fn (array $state): ?string => $state['title'] ?? null)
                        ->reorderableWithButtons()
                        ->collapsible()
                        ->default([])
                        ->addActionLabel('Añadir sección')
                        ->columnSpanFull(),
                ])
                ->action(function (array $data): void {
                    $sections = collect($data['sections'] ?? [])
                        ->map(function (array $section): array {
                            $images = collect($section['images'] ?? [])
                                ->filter(fn (array $image): bool => filled($image['url'] ?? null))
                                ->map(fn (array $image): array => [
                                    'url' => $image['url'] ?? '',
                                    'caption' => $image['caption'] ?? '',
                                ])
                                ->values()
                                ->all();

                            return [
                                'title' => $section['title'] ?? '',
                                'content' => $section['content'] ?? '',
                                'images' => $images,
                            ];
                        })
                        ->values()
                        ->all();

                    $this->record->forceFill([
                        'description' => ['sections' => $sections],
                    ])->save();
                }),

            


            Action::make('editOrbat')
                ->label('Editar ORBAT')
                ->modalHeading('Editor de ORBAT')
                ->modalSubmitActionLabel('Guardar ORBAT')
                ->modalWidth('7xl')
                ->fillForm(fn (): array => $this->record->orbat ?? ['groups' => []])
                ->form([
                    Repeater::make('groups')
                        ->label('Grupos')
                        ->columns(3)
                        ->schema([
                            TextInput::make('name')
                                ->label('Nombre')
                                ->required()
                                ->maxLength(255),

                            Select::make('army_id')
                                ->label('Ejército')
                                ->options(fn (): array => Army::query()
                                    ->orderBy('name')
                                    ->pluck('name', 'id')
                                    ->all())
                                ->searchable()
                                ->required(),

                            Toggle::make('visible')
                                ->label('Visible')
                                ->default(true),

                            Repeater::make('slots')
                                ->label('Slots')
                                ->columns(2)
                                ->schema([
                                    Hidden::make('slot_key')
                                        ->default(fn (): string => (string) Str::ulid()),

                                    TextInput::make('name')
                                        ->label('Nombre')
                                        ->required()
                                        ->maxLength(255),

                                    Select::make('slot_type_id')
                                        ->label('Tipo de slot')
                                        ->options(fn (): array => SlotType::query()
                                            ->orderBy('name')
                                            ->pluck('name', 'id')
                                            ->all())
                                        ->searchable()
                                        ->required(),
                                ])
                                ->itemLabel(fn (array $state): ?string => $state['name'] ?? null)
                                ->reorderableWithButtons()
                                ->collapsible()
                                ->default([])
                                ->addActionLabel('Añadir slot')
                                ->columnSpanFull(),
                        ])
                        ->itemLabel(fn (array $state): ?string => $state['name'] ?? null)
                        ->reorderableWithButtons()
                        ->collapsible()
                        ->default([])
                        ->addActionLabel('Añadir grupo')
                        ->columnSpanFull(),
                ])
                ->action(function (array $data): void {
                    $groups = collect($data['groups'] ?? [])
                        ->map(function (array $group): array {
                            $slots = collect($group['slots'] ?? [])
                                ->map(fn (array $slot): array => [
                                    'slot_key' => $slot['slot_key'] ?: (string) Str::ulid(),
                                    'name' => $slot['name'] ?? '',
                                    'slot_type_id' => isset($slot['slot_type_id']) ? (int) $slot['slot_type_id'] : null,
                                ])
                                ->values()
                                ->all();

                            return [
                                'name' => $group['name'] ?? '',
                                'army_id' => isset($group['army_id']) ? (int) $group['army_id'] : null,
                                'visible' => (bool) ($group['visible'] ?? false),
                                'slots' => $slots,
                            ];
                        })
                        ->values()
                        ->all();

                    $this->record->forceFill([
                        'orbat' => ['groups' => $groups],
                    ])->save();
                }),

            Action::make('editRadio')
                ->label('Editar radios')
                ->modalHeading('Editor de radios')
                ->modalSubmitActionLabel('Guardar radios')
                ->modalWidth('7xl')
                ->fillForm(function (): array {
                    $radio = $this->record->radio ?? [];
                    $networks = $radio['networks'] ?? [];

                    if (blank($networks) && filled($radio['content'] ?? null)) {
                        $networks = [
                            [
                                'name' => 'Radio',
                                'radio_model_id' => null,
                                'radio_model_name' => null,
                                'configuration' => [
                                    'channel' => null,
                                    'block' => null,
                                    'frequency' => null,
                                ],
                                'notes' => $radio['content'],
                                'visible' => true,
                            ],
                        ];
                    }

                    return ['networks' => $networks];
                })
                ->form([
                    Actions::make([
                        Action::make('loadOrbatRadioNetworks')
                            ->label('Cargar ORBAT')
                            ->action(function (Get $get, Set $set): void {
                                $orbatGroups = $this->record->orbat['groups'] ?? [];

                                $networks = collect($get('networks') ?? [])
                                    ->merge(
                                        collect($orbatGroups)
                                            ->pluck('name')
                                            ->filter()
                                            ->map(fn (string $name): array => static::blankRadioNetwork($name))
                                    )
                                    ->values()
                                    ->all();

                                $set('networks', $networks);
                            }),

                        Action::make('addAirRadioNetwork')
                            ->label('Aire')
                            ->action(fn (Get $get, Set $set) => $set(
                                'networks',
                                collect($get('networks') ?? [])
                                    ->push(static::blankRadioNetwork('Aire'))
                                    ->values()
                                    ->all()
                            )),

                        Action::make('addVehiclesRadioNetwork')
                            ->label('Vehículos')
                            ->action(fn (Get $get, Set $set) => $set(
                                'networks',
                                collect($get('networks') ?? [])
                                    ->push(static::blankRadioNetwork('Vehículos'))
                                    ->values()
                                    ->all()
                            )),

                        Action::make('addGlobalRadioNetwork')
                            ->label('Global')
                            ->action(fn (Get $get, Set $set) => $set(
                                'networks',
                                collect($get('networks') ?? [])
                                    ->push(static::blankRadioNetwork('Global'))
                                    ->values()
                                    ->all()
                            )),
                    ]),

                    Repeater::make('networks')
                        ->label('Redes de radio')
                        ->schema([
                            TextInput::make('name')
                                ->label('Nombre')
                                ->required()
                                ->maxLength(255),

                            Select::make('radio_model_id')
                                ->label('Modelo de radio')
                                ->options(fn (): array => RadioModel::query()
                                    ->orderBy('name')
                                    ->pluck('name', 'id')
                                    ->all())
                                ->searchable()
                                ->preload()
                                ->live()
                                ->afterStateUpdated(function ($state, Set $set): void {
                                    $radioModel = RadioModel::query()->find($state);

                                    $set('radio_model_name', $radioModel?->name);
                                    $set('configuration.channel', null);
                                    $set('configuration.block', null);
                                    $set('configuration.frequency', null);
                                })
                                ->required(),

                            Hidden::make('radio_model_name'),

                            TextInput::make('configuration.channel')
                                ->label('Canal')
                                ->numeric()
                                ->visible(fn (Get $get): bool => (bool) RadioModel::query()
                                    ->whereKey($get('radio_model_id'))
                                    ->value('channel')),

                            TextInput::make('configuration.block')
                                ->label('Bloque')
                                ->numeric()
                                ->visible(fn (Get $get): bool => (bool) RadioModel::query()
                                    ->whereKey($get('radio_model_id'))
                                    ->value('block')),

                            TextInput::make('configuration.frequency')
                                ->label('Frecuencia')
                                ->numeric()
                                ->step('0.001')
                                ->suffix('MHz')
                                ->visible(fn (Get $get): bool => (bool) RadioModel::query()
                                    ->whereKey($get('radio_model_id'))
                                    ->value('frequency')),

                            Textarea::make('notes')
                                ->label('Notas')
                                ->rows(2)
                                ->columnSpanFull(),

                            Toggle::make('visible')
                                ->label('Visible')
                                ->default(true),
                        ])
                        ->columns(3)
                        ->itemLabel(fn (array $state): ?string => $state['name'] ?? null)
                        ->reorderableWithButtons()
                        ->collapsible()
                        ->default([])
                        ->addActionLabel('Añadir red')
                        ->columnSpanFull(),
                ])
                ->action(function (array $data): void {
                    $networks = collect($data['networks'] ?? [])
                        ->map(function (array $network): array {
                            $radioModel = isset($network['radio_model_id'])
                                ? RadioModel::query()->find($network['radio_model_id'])
                                : null;

                            return [
                                'name' => $network['name'] ?? '',
                                'radio_model_id' => isset($network['radio_model_id']) ? (int) $network['radio_model_id'] : null,
                                'radio_model_name' => $radioModel?->name ?? $network['radio_model_name'] ?? null,
                                'configuration' => [
                                    'channel' => filled($network['configuration']['channel'] ?? null)
                                        ? (int) $network['configuration']['channel']
                                        : null,
                                    'block' => filled($network['configuration']['block'] ?? null)
                                        ? (int) $network['configuration']['block']
                                        : null,
                                    'frequency' => filled($network['configuration']['frequency'] ?? null)
                                        ? (float) $network['configuration']['frequency']
                                        : null,
                                ],
                                'notes' => $network['notes'] ?? null,
                                'visible' => (bool) ($network['visible'] ?? true),
                            ];
                        })
                        ->values()
                        ->all();

                    $this->record->forceFill([
                        'radio' => ['networks' => $networks],
                    ])->save();
                }),    

            Action::make('editAddons')
                ->label('Editar addons')
                ->modalHeading('Editor de addons')
                ->modalSubmitActionLabel('Guardar addons')
                ->modalWidth('3xl')
                ->fillForm(fn (): array => [
                    'addon_ids' => $this->record->addons['addon_ids'] ?? [],
                    'addon_preset_id' => null,
                    'addons_text' => '',
                ])
                ->form([
                    Select::make('addon_preset_id')
                        ->label('Preset de addons')
                        ->options(fn (): array => AddonPreset::query()
                            ->orderBy('name')
                            ->pluck('name', 'id')
                            ->all())
                        ->searchable()
                        ->preload()
                        ->dehydrated(false),

                    Textarea::make('addons_text')
                        ->label('Listado de addons')
                        ->helperText('Pega aquí el listado en texto plano, un addon por línea.')
                        ->rows(3)
                        ->dehydrated(false)
                        ->columnSpanFull(),

                    Actions::make([
                        Action::make('applyAddonPreset')
                            ->label('Aplicar preset')
                            ->action(function (Get $get, Set $set): void {
                                $presetId = $get('addon_preset_id');

                                if (blank($presetId)) {
                                    return;
                                }

                                $preset = AddonPreset::query()->find($presetId);

                                $presetAddonIds = $preset
                                    ? $preset->addons()
                                        ->pluck('addons.id')
                                        ->map(fn (int $id): string => (string) $id)
                                        ->all()
                                    : [];

                                $set('addon_ids', collect($get('addon_ids') ?? [])
                                    ->merge($presetAddonIds)
                                    ->unique()
                                    ->values()
                                    ->all());
                            }),

                        Action::make('selectMandatoryAddons')
                            ->label('Seleccionar obligatorios')
                            ->action(function (Get $get, Set $set): void {
                                $mandatoryAddonIds = Addon::query()
                                    ->where('mandatory', true)
                                    ->pluck('id')
                                    ->map(fn (int $id): string => (string) $id)
                                    ->all();

                                $set('addon_ids', collect($get('addon_ids') ?? [])
                                    ->merge($mandatoryAddonIds)
                                    ->unique()
                                    ->values()
                                    ->all());
                            }),

                        Action::make('importAddonsHtml')
                            ->label('Importar listado')
                            ->action(function (Get $get, Set $set): void {
                                $addonNames = static::extractAddonNamesFromText($get('addons_text'));

                                if (blank($addonNames)) {
                                    Notification::make()
                                        ->title('No se han encontrado addons en el listado.')
                                        ->warning()
                                        ->send();

                                    return;
                                }

                                $addonIds = Addon::query()
                                    ->whereIn('name', $addonNames)
                                    ->pluck('id')
                                    ->all();

                                // De momento no creamos automáticamente addons que no existan.
                                // Si más adelante queremos recuperarlo, esta era la lógica:
                                // $addonIds = collect($addonNames)
                                //     ->map(fn (string $name): int => Addon::query()
                                //         ->firstOrCreate(['name' => $name], [
                                //             'description' => null,
                                //             'mandatory' => false,
                                //         ])
                                //         ->id)
                                //     ->all();

                                $set('addon_ids', collect($get('addon_ids') ?? [])
                                    ->merge($addonIds)
                                    ->map(fn ($addonId): string => (string) $addonId)
                                    ->unique()
                                    ->values()
                                    ->all());

                                Notification::make()
                                    ->title(count($addonIds) . ' addons importados.')
                                    ->success()
                                    ->send();
                            }),

                        Action::make('downloadAddonsHtml')
                            ->label('Descargar HTML')
                            ->action(function (Get $get) {
                                $addonIds = collect($get('addon_ids') ?? [])
                                    ->map(fn ($addonId): int => (int) $addonId)
                                    ->filter()
                                    ->values()
                                    ->all();

                                $filename = Str::slug($this->record->name ?: 'operacion') . '-addons.html';

                                return response()->streamDownload(
                                    fn () => print static::buildAddonsHtml($addonIds),
                                    $filename,
                                    ['Content-Type' => 'text/html; charset=UTF-8']
                                );
                            }),

                        
                    ]),

                    CheckboxList::make('addon_ids')
                        ->label('Addons')
                        ->options(fn (): array => Addon::query()
                            ->orderBy('mandatory', 'desc')
                            ->orderBy('name')
                            ->pluck('name', 'id')
                            ->all())
                        ->descriptions(fn (): array => Addon::query()
                            ->orderBy('mandatory', 'desc')
                            ->orderBy('name')
                            ->get()
                            ->mapWithKeys(fn (Addon $addon): array => [
                                $addon->id => trim(($addon->mandatory ? 'Obligatorio. ' : 'Opcional. ') . ($addon->description ?? '')),
                            ])
                            ->all())
                        ->bulkToggleable()
                        ->searchable()
                        ->columns(2)
                        ->columnSpanFull(),
                ])
                ->action(function (array $data): void {
                    $addonIds = collect($data['addon_ids'] ?? [])
                        ->map(fn ($addonId): int => (int) $addonId)
                        ->filter()
                        ->values()
                        ->all();

                    $this->record->forceFill([
                        'addons' => ['addon_ids' => $addonIds],
                    ])->save();
                }),

            

            DeleteAction::make(),
            ForceDeleteAction::make(),
            RestoreAction::make(),
        ];
    }

    protected static function buildAddonsHtml(array $addonIds): string
    {
        $addons = Addon::query()
            ->whereIn('id', $addonIds)
            ->orderBy('mandatory', 'desc')
            ->orderBy('name')
            ->get();

        $rows = $addons
            ->map(function (Addon $addon): string {
                $name = htmlspecialchars($addon->name, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

                return <<<HTML
  <tr data-type="ModContainer">
    <td data-type="DisplayName">{$name}</td>
  </tr>
HTML;
            })
            ->implode("\n");

        return <<<HTML
<html><head></head><body><table>
  <tbody>{$rows}
</tbody></table></body></html>
HTML;
    }

    protected static function extractAddonNamesFromText(?string $text): array
    {
        if (blank($text)) {
            return [];
        }

        return collect(preg_split('/\R/', $text))
            ->map(fn (string $name): string => trim($name))
            ->filter()
            ->unique(fn (string $name): string => mb_strtolower($name))
            ->values()
            ->all();
    }

    protected static function blankRadioNetwork(string $name): array
    {
        return [
            'name' => $name,
            'radio_model_id' => null,
            'radio_model_name' => null,
            'configuration' => [
                'channel' => null,
                'block' => null,
                'frequency' => null,
            ],
            'notes' => null,
            'visible' => true,
        ];
    }
}
