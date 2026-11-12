<?php

namespace App\Filament\Pages;

use App\Models\RecruitmentFormSetting;
use App\Support\RecruitmentFormFields;
use BackedEnum;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Concerns\InteractsWithSchemas;
use Filament\Schemas\Contracts\HasSchemas;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use UnitEnum;

class EditRecruitmentForm extends Page implements HasSchemas
{
    use InteractsWithSchemas;

    protected static string|UnitEnum|null $navigationGroup = 'Reclutamiento';
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClipboardDocumentList;
    protected static ?string $navigationLabel = 'Formulario de alistamiento';
    protected static ?int $navigationSort = 1;
    protected static ?string $title = 'Formulario de alistamiento';
    protected string $view = 'filament.pages.edit-recruitment-form';

    public ?array $data = [];

    public static function canAccess(): bool
    {
        return (bool) auth()->user()?->can('recruitment-form.manage');
    }

    public function mount(): void
    {
        abort_unless(static::canAccess(), 403);
        $this->form->fill(['fields' => RecruitmentFormSetting::current()->fields]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Preguntas del formulario')
                ->description('Añade, quita, oculta y ordena las preguntas que aparecen al marcar la solicitud de alistamiento. Los datos básicos de contacto y los consentimientos se mantienen siempre.')
                ->schema([
                    Repeater::make('fields')
                        ->label('Preguntas')
                        ->schema([
                            Hidden::make('key'),
                            TextInput::make('section')
                                ->label('Apartado')
                                ->helperText('Las preguntas consecutivas con el mismo apartado aparecen juntas.')
                                ->maxLength(100),
                            Select::make('type')
                                ->label('Tipo de respuesta')
                                ->options([
                                    'text' => 'Texto corto',
                                    'textarea' => 'Texto largo',
                                    'checkbox' => 'Casilla de verificación',
                                    'date' => 'Fecha',
                                    'email' => 'Correo electrónico',
                                    'tel' => 'Teléfono',
                                ])
                                ->required()
                                ->disabled(fn (Get $get): bool => array_key_exists((string) $get('key'), RecruitmentFormFields::LEGACY_TYPES))
                                ->dehydrated()
                                ->live(),
                            Textarea::make('label')
                                ->label('Pregunta o texto de la casilla')
                                ->rows(2)
                                ->maxLength(500)
                                ->required()
                                ->columnSpanFull(),
                            TextInput::make('placeholder')
                                ->label('Texto de ejemplo')
                                ->maxLength(400)
                                ->visible(fn (Get $get): bool => $get('type') !== 'checkbox')
                                ->columnSpanFull(),
                            Toggle::make('required')
                                ->label('Respuesta obligatoria')
                                ->helperText('En una casilla, obliga a marcarla para poder enviar la solicitud.'),
                            Toggle::make('enabled')
                                ->label('Mostrar en la web')
                                ->default(true),
                            Select::make('url')
                                ->label('Enlace al lado de la pregunta (opcional)')
                                ->options(['' => 'Sin enlace', '/normativa' => 'Normativa', '/politica-de-privacidad' => 'Política de privacidad'])
                                ->default('')
                                ->visible(fn (Get $get): bool => $get('type') === 'checkbox'),
                            TextInput::make('linkText')
                                ->label('Texto del enlace')
                                ->maxLength(100)
                                ->visible(fn (Get $get): bool => $get('type') === 'checkbox' && filled($get('url'))),
                        ])
                        ->columns(2)
                        ->defaultItems(0)
                        ->addActionLabel('Añadir pregunta')
                        ->reorderable()
                        ->deletable()
                        ->collapsible()
                        ->itemLabel(fn (array $state): ?string => trim((string) ($state['label'] ?? '')) ?: 'Nueva pregunta')
                        ->maxItems(80)
                        ->columnSpanFull(),
                ])->columnSpanFull(),
        ])->statePath('data');
    }

    public function save(): void
    {
        abort_unless(static::canAccess(), 403);

        try {
            $fields = RecruitmentFormFields::normalize((array) ($this->form->getState()['fields'] ?? []));
        } catch (InvalidArgumentException $exception) {
            Notification::make()->title('No se pudo guardar')->body($exception->getMessage())->danger()->send();
            return;
        }

        DB::transaction(function () use ($fields): void {
            RecruitmentFormSetting::current()->update(['fields' => $fields]);
        });

        $this->form->fill(['fields' => $fields]);
        Notification::make()->title('Formulario guardado')->body('Los cambios ya aparecen en la web. Las solicitudes anteriores conservan sus respuestas.')->success()->send();
    }
}
