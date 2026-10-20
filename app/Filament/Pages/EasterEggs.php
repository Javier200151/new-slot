<?php

namespace App\Filament\Pages;

use App\Support\SquadEasterEggs;
use App\Http\Controllers\Admin\EasterEggZeusMediaController;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Pages\PageConfiguration;
use Filament\Panel;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Concerns\InteractsWithSchemas;
use Filament\Schemas\Contracts\HasSchemas;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema as SchemaFacade;

class EasterEggs extends Page implements HasSchemas
{
    use InteractsWithSchemas;

    protected static ?string $slug = 'easter_eggs';
    protected static bool $shouldRegisterNavigation = false;
    protected static ?string $title = 'Easter Eggs · Squad ALPHA';
    protected string $view = 'filament.pages.easter-eggs';

    /** @var array<string, mixed>|null */
    public ?array $data = [];

    public bool $spoilersVisible = false;

    public static function canAccess(): bool
    {
        return (bool) (Auth::user()?->hasRole('admin'));
    }

    /** Ruta POST interna al panel: hereda prefijo /admin y middleware Filament. */
    public static function routes(Panel $panel, ?PageConfiguration $configuration = null): void
    {
        parent::routes($panel, $configuration);

        Route::post(static::getRoutePath($panel).'/zeus-media', EasterEggZeusMediaController::class)
            ->middleware(static::getRouteMiddleware($panel))
            ->withoutMiddleware(static::getWithoutRouteMiddleware($panel))
            ->name(static::getRelativeRouteName($panel).'.zeus-media');
    }

    public function mount(): void
    {
        abort_unless(static::canAccess(), 403);
        abort_unless(SchemaFacade::hasTable('sqa_easter_eggs')
            && SchemaFacade::hasTable('sqa_easter_egg_404_phrases')
            && SchemaFacade::hasTable('sqa_easter_egg_overrides'), 503, 'Ejecuta las migraciones pendientes.');

        $rows = DB::table('sqa_easter_eggs')->get()->keyBy('key');
        $initial = [];
        foreach (SquadEasterEggs::KEYS as $key) {
            $initial[$key.'_enabled'] = (bool) ($rows->get($key)?->enabled ?? false);
        }

        $initial['grenade_cooldown_seconds'] = (int) ($rows->get('grenade')?->cooldown_seconds ?? 120);
        $initial['fallujah_mode'] = DB::table('sqa_easter_egg_overrides')
            ->where('key', 'fallujah_2012')->value('mode') ?: 'auto';
        $initial['phrases'] = DB::table('sqa_easter_egg_404_phrases')
            ->orderBy('position')->orderBy('id')->get(['phrase', 'enabled'])
            ->map(fn ($row) => ['phrase' => $row->phrase, 'enabled' => (bool) $row->enabled])
            ->all();

        $this->form->fill($initial);
    }

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Granada · tecla G')
                ->description('La granada solo aparece en la portada, Eventos, Foro, Actividades, Usuarios, Organigrama, Metopas y Tesorería.')
                ->schema([
                    Toggle::make('grenade_enabled')->label('Activar granada'),
                    TextInput::make('grenade_cooldown_seconds')
                        ->label('Espera mínima entre granadas (segundos)')
                        ->numeric()->minValue(1)->maxValue(86400)->required(),
                ]),
            Section::make('Error 404')
                ->description('Muestra una frase al azar cuando alguien abre una página inexistente.')
                ->schema([
                    Toggle::make('error404_enabled')->label('Activar frases del 404'),
                    Repeater::make('phrases')
                        ->label('Frases disponibles')
                        ->schema([
                            TextInput::make('phrase')->label('Frase')->required()->maxLength(500),
                            Toggle::make('enabled')->label('Utilizar esta frase')->default(true),
                        ])
                        ->columns(1)
                        ->defaultItems(0)
                        ->addActionLabel('Añadir frase')
                        ->reorderable()
                        ->deletable(),
                ]),
            Section::make('Visión nocturna')
                ->description('Se activa al pulsar las letras N de los títulos de Inicio y Eventos. Púlsalas otra vez para volver a la normalidad.')
                ->schema([
                    Toggle::make('nvg_enabled')->label('Activar visión nocturna'),
                ]),
            Section::make('Ojo de Zeus · tecla Y')
                ->description('Al pulsar Y aparece el ojo de Zeus y, si lo has añadido, su sonido.')
                ->schema([
                    Toggle::make('zeus_enabled')->label('Activar ojo de Zeus'),
                ]),
            Section::make('Código Konami')
                ->description('↑ ↑ ↓ ↓ ← → ← → B A. Abre un menú secreto de ventajas ficticias.')
                ->schema([
                    Toggle::make('konami_enabled')->label('Activar código Konami'),
                ]),
            Section::make('Fallujah · 27 de abril de 2012')
                ->description('Homenaje a la primera partida oficial de Squad ALPHA.')
                ->schema([
                    Toggle::make('fallujah_2012_enabled')->label('Activar homenaje de Fallujah'),
                    Select::make('fallujah_mode')->label('Modo de activación')
                        ->options([
                            'auto' => 'Automático — solo el 27 de abril',
                            'on' => 'Mostrar ahora — pruebas',
                            'off' => 'No mostrar',
                        ])
                        ->required(),
                ]),
        ])->statePath('data');
    }

    public function toggleSpoilers(): void
    {
        abort_unless(static::canAccess(), 403);
        $this->spoilersVisible = ! $this->spoilersVisible;
    }

    public function save(): void
    {
        abort_unless(static::canAccess(), 403);
        $data = $this->form->getState();
        $mode = (string) ($data['fallujah_mode'] ?? 'auto');
        if (! in_array($mode, ['auto', 'on', 'off'], true)) {
            throw \Illuminate\Validation\ValidationException::withMessages(['data.fallujah_mode' => 'Modo no válido.']);
        }

        DB::transaction(function () use ($data, $mode): void {
            foreach (SquadEasterEggs::KEYS as $key) {
                $values = [
                    'enabled' => (bool) ($data[$key.'_enabled'] ?? false),
                    'updated_at' => now(),
                ];
                if ($key === 'grenade') {
                    $values['cooldown_seconds'] = max(1, min(86400, (int) ($data['grenade_cooldown_seconds'] ?? 120)));
                }

                DB::table('sqa_easter_eggs')->updateOrInsert(
                    ['key' => $key],
                    $values
                );
            }

            DB::table('sqa_easter_egg_overrides')->updateOrInsert(
                ['key' => 'fallujah_2012'],
                ['mode' => $mode, 'updated_at' => now()]
            );

            DB::table('sqa_easter_egg_404_phrases')->delete();
            foreach (array_values($data['phrases'] ?? []) as $position => $entry) {
                DB::table('sqa_easter_egg_404_phrases')->insert([
                    'phrase' => trim((string) $entry['phrase']),
                    'enabled' => (bool) ($entry['enabled'] ?? false),
                    'position' => $position,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        });

        SquadEasterEggs::resetCache();
        Notification::make()->title('Configuración guardada')->success()->send();
    }
}
