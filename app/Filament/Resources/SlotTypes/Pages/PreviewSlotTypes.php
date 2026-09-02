<?php

namespace App\Filament\Resources\SlotTypes\Pages;

use App\Filament\Resources\SlotTypes\SlotTypeResource;
use App\Models\SlotType;
use App\Support\SlotQuickSelection;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\Page;
use Illuminate\Support\Facades\DB;

class PreviewSlotTypes extends Page
{
    protected static string $resource = SlotTypeResource::class;

    protected string $view =
        'filament.resources.slot-types.pages.preview-slot-types';

    protected static ?string $title =
        'Preview del selector de slots';

    public array $columns = [];

    public function mount(): void
    {
        abort_unless(
            auth()->user()?->can('slot-types.update') ?? false,
            403,
        );

        $this->loadColumns();
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('backToSlotTypes')
                ->label('Volver a tipos de slot')
                ->icon('heroicon-o-arrow-left')
                ->color('gray')
                ->url(SlotTypeResource::getUrl('index')),
        ];
    }

    public function saveLayout(array $columns): void
    {
        abort_unless(
            auth()->user()?->can('slot-types.update') ?? false,
            403,
        );

        if (count($columns) !== 4) {
            abort(422, 'El selector debe contener cuatro columnas.');
        }

        $normalizedColumns = collect($columns)
            ->map(
                fn (mixed $column): array => collect(
                    is_array($column) ? $column : []
                )
                    ->map(fn (mixed $id): int => (int) $id)
                    ->filter(fn (int $id): bool => $id > 0)
                    ->values()
                    ->all()
            )
            ->values();

        $receivedIds = $normalizedColumns
            ->flatten()
            ->values();

        if ($receivedIds->count() !== $receivedIds->unique()->count()) {
            abort(422, 'Hay tipos de slot repetidos en el layout.');
        }

        $databaseIds = SlotType::query()
            ->pluck('id')
            ->map(fn (mixed $id): int => (int) $id)
            ->sort()
            ->values();

        if (
            $receivedIds->sort()->values()->all()
            !== $databaseIds->all()
        ) {
            abort(
                422,
                'El listado de tipos de slot ha cambiado. Recarga la página antes de guardar.'
            );
        }

        DB::transaction(
            function () use ($normalizedColumns): void {
                foreach ($normalizedColumns as $columnIndex => $slotTypeIds) {
                    foreach ($slotTypeIds as $orderIndex => $slotTypeId) {
                        SlotType::query()
                            ->whereKey($slotTypeId)
                            ->update([
                                'picker_column' => $columnIndex + 1,
                                'picker_order' => ($orderIndex + 1) * 10,
                            ]);
                    }
                }
            }
        );

        SlotQuickSelection::clearCache();
        $this->loadColumns();

        Notification::make()
            ->title('Orden del selector guardado')
            ->success()
            ->send();
    }

    private function loadColumns(): void
    {
        // Array PHP normal: necesitamos poder añadir elementos con []
        // dentro de cada columna. Una Collection no permite modificar así
        // indirectamente sus elementos sobrecargados.
        $columns = [
            1 => [],
            2 => [],
            3 => [],
            4 => [],
        ];

        SlotType::query()
            ->with([
                'quickNames' => fn ($query) => $query
                    ->orderBy('sort_order')
                    ->orderBy('name'),
            ])
            ->orderBy('picker_column')
            ->orderBy('picker_order')
            ->orderBy('name')
            ->get()
            ->each(
                function (SlotType $slotType) use (&$columns): void {
                    $column = max(
                        1,
                        min(4, (int) ($slotType->picker_column ?: 1))
                    );

                    $quickNames = $slotType->quickNames
                        ->pluck('name')
                        ->values()
                        ->all();

                    if ($quickNames === []) {
                        $quickNames = [$slotType->name];
                    }

                    $columns[$column][] = [
                        'id' => (int) $slotType->id,
                        'name' => (string) $slotType->name,
                        'quick_names' => $quickNames,
                    ];
                }
            );

        $this->columns = array_values($columns);
    }
}
