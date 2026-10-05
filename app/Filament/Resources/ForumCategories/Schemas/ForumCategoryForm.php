<?php

namespace App\Filament\Resources\ForumCategories\Schemas;

use App\Filament\Forms\BbcodeTextarea;
use App\Models\CommunityProcess;
use App\Models\ForumCategory;
use App\Models\Status;
use Filament\Forms\Components\ColorPicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Validation\Rule;

class ForumCategoryForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Categoría')
                    ->description('Las categorías normales son totalmente configurables. Diario es una categoría interna: permite personalizar su nombre, color, orden y los estados que pueden acceder.')
                    ->schema([
                        TextInput::make('title')
                            ->label('Nombre visible')
                            ->required()
                            ->maxLength(120),

                        TextInput::make('slug')
                            ->label('Identificador / URL')
                            ->disabled(fn (?ForumCategory $record): bool => (bool) $record?->isDiary())
                            ->placeholder('debates-generales')
                            ->helperText('Ejemplo: debates-generales → /area/foro/debates-generales. Déjalo vacío al crear para generarlo desde el título.')
                            ->maxLength(100)
                            ->unique(ignoreRecord: true)
                            ->rules(fn (?ForumCategory $record): array => $record?->isDiary()
                                ? []
                                : [
                                    'nullable',
                                    'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/',
                                    Rule::notIn(ForumCategory::RESERVED_SLUGS),
                                ]),

                        TextInput::make('singular')
                            ->label('Nombre en singular')
                            ->helperText(fn (?ForumCategory $record): string => $record?->isDiary()
                                ? 'Personalización segura del texto singular. La lógica interna de Diario no cambia.'
                                : 'Se usa en textos como “Nuevo debate” o “Nueva presentación”.')
                            ->placeholder('Hilo')
                            ->maxLength(80),

                        TextInput::make('icon')
                            ->label('Icono')
                            ->disabled(fn (?ForumCategory $record): bool => (bool) $record?->isDiary())
                            ->helperText('Puedes usar un emoji, por ejemplo 👋, 💬 o 🥃.')
                            ->default('💬')
                            ->maxLength(32),

                        ColorPicker::make('color')
                            ->label('Color')
                            ->default('#38bdf8'),

                        BbcodeTextarea::make('description')
                            ->label('Descripción')
                            ->disabled(fn (?ForumCategory $record): bool => (bool) $record?->isDiary())
                            ->rows(4)
                            ->maxLength(2000)
                            ->columnSpanFull(),

                        TextInput::make('hint')
                            ->label('Texto de ayuda al publicar')
                            ->disabled(fn (?ForumCategory $record): bool => (bool) $record?->isDiary())
                            ->maxLength(255)
                            ->columnSpanFull(),
                    ])
                    ->columns(2),

                Section::make('Comportamiento')
                    ->description('Configura qué puede hacerse dentro de esta categoría. Los permisos concretos siguen administrándose desde Roles.')
                    ->schema([
                        Toggle::make('allow_polls')
                            ->label('Permitir votaciones en esta categoría')
                            ->disabled(fn (?ForumCategory $record): bool => (bool) $record?->isDiary())
                            ->helperText('Cuando está activo, los usuarios con permiso de votaciones podrán crear o gestionar una votación vinculada a un hilo.')
                            ->default(false),

                        Select::make('process_type')
                            ->label('Flujo especial')
                            ->disabled(fn (?ForumCategory $record): bool => (bool) $record?->isDiary())
                            ->options([
                                CommunityProcess::TYPE_CALL => 'Convocatoria / postulaciones',
                                CommunityProcess::TYPE_PROPOSALS => 'Propuesta',
                                CommunityProcess::TYPE_CONSULTATION => 'Consulta',
                            ])
                            ->placeholder('Foro normal')
                            ->helperText('Déjalo vacío para una categoría normal. Úsalo solo si quieres que los hilos incluyan el flujo especial indicado.')
                            ->nullable(),
                    ])
                    ->columns(2),

                Section::make('Visibilidad y orden')
                    ->schema([
                        Select::make('statuses')
                            ->label('Estados de usuario que pueden verla')
                            ->relationship('statuses', 'name')
                            ->multiple()
                            ->searchable()
                            ->preload()
                            ->required()
                            ->default(
                                fn (): array => Status::query()
                                    ->whereIn('name', ['ACTIVO', 'RESERVA', 'RECLUTA'])
                                    ->pluck('id')
                                    ->all()
                            )
                            ->helperText('Admin y los roles con permisos de moderación de esta categoría podrán entrar aunque su estado no esté seleccionado.')
                            ->columnSpanFull(),

                        TextInput::make('sort_order')
                            ->label('Orden')
                            ->numeric()
                            ->minValue(0)
                            ->maxValue(65535)
                            ->default(100)
                            ->required(),

                        Toggle::make('is_enabled')
                            ->label('Categoría activa')
                            ->disabled(fn (?ForumCategory $record): bool => (bool) $record?->isDiary())
                            ->helperText('Si se desactiva, desaparece del foro público sin borrar sus hilos.')
                            ->default(true),
                    ])
                    ->columns(2),
            ]);
    }
}
