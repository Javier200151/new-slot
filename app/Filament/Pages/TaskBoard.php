<?php

namespace App\Filament\Pages;

use App\Models\TaskCard;
use App\Models\User;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use UnitEnum;

class TaskBoard extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static string|UnitEnum|null $navigationGroup = 'Procedimientos';

    protected static ?int $navigationSort = 3;

    protected static ?string $navigationLabel = 'Área de tareas';

    protected static ?string $title = 'Área de tareas';

    protected string $view = 'filament.pages.task-board';

    protected Width|string|null $maxContentWidth = Width::Full;

    public string $search = '';

    public static function canAccess(): bool
    {
        $user = auth()->user();

        return (bool) ($user?->hasRole('admin') || $user?->can('task-area.access'));
    }

    public function mount(): void
    {
        abort_unless(static::canAccess(), 403);
    }

    /** @return array<int, array{status:string,label:string,items:Collection}> */
    public function columns(): array
    {
        $query = TaskCard::query()
            ->with(['creator', 'assignees', 'checklistItems'])
            ->withCount('comments')
            ->when(filled($this->search), function ($query): void {
                $term = trim($this->search);
                $query->where(function ($query) use ($term): void {
                    $query->where('title', 'like', "%{$term}%")
                        ->orWhere('description', 'like', "%{$term}%")
                        ->orWhereHas('assignees', fn ($query) => $query->where('nick', 'like', "%{$term}%"));
                });
            });

        return collect(TaskCard::statuses())
            ->map(function (string $label, string $status) use ($query): array {
                return [
                    'status' => $status,
                    'label' => $label,
                    'items' => (clone $query)
                        ->where('status', $status)
                        ->orderBy('position')
                        ->orderByDesc('updated_at')
                        ->get(),
                ];
            })
            ->values()
            ->all();
    }

    public function moveTask(int $taskId, string $status): void
    {
        $this->authorizeBoard();
        abort_unless(array_key_exists($status, TaskCard::statuses()), 422);

        $task = TaskCard::query()->findOrFail($taskId);
        $position = ((int) TaskCard::query()->where('status', $status)->max('position')) + 10;

        $task->update([
            'status' => $status,
            'position' => $position,
            'updated_by' => auth()->id(),
        ]);
    }

    public function assignMe(int $taskId): void
    {
        $this->authorizeBoard();
        $task = TaskCard::query()->findOrFail($taskId);

        $task->assignees()->syncWithoutDetaching([
            auth()->id() => ['assigned_by' => auth()->id()],
        ]);

        $task->forceFill(['updated_by' => auth()->id()])->save();
    }

    public function deleteTask(int $taskId): void
    {
        $this->authorizeBoard();
        TaskCard::query()->findOrFail($taskId)->delete();

        Notification::make()
            ->success()
            ->title('Tarjeta eliminada')
            ->send();
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('createTask')
                ->label('Crear tarjeta')
                ->icon('heroicon-o-plus')
                ->color('warning')
                ->modalHeading('Nueva tarea')
                ->modalWidth('3xl')
                ->schema($this->taskFormSchema(false))
                ->action(function (array $data): void {
                    $this->authorizeBoard();

                    DB::transaction(function () use ($data): void {
                        $task = TaskCard::query()->create([
                            'title' => trim((string) $data['title']),
                            'description' => $this->nullableTrim($data['description'] ?? null),
                            'status' => TaskCard::STATUS_PENDING,
                            'position' => ((int) TaskCard::query()->where('status', TaskCard::STATUS_PENDING)->max('position')) + 10,
                            'created_by' => auth()->id(),
                            'updated_by' => auth()->id(),
                        ]);

                        $this->syncAssignees($task, $data['assignee_ids'] ?? []);
                        $this->replaceChecklist($task, $data['checklist'] ?? []);
                    });

                    Notification::make()->success()->title('Tarea creada en Pendiente')->send();
                }),

            Action::make('editTask')
                ->label('Editar tarjeta')
                ->extraAttributes(['style' => 'display:none'])
                ->modalHeading(fn (array $arguments): string => TaskCard::query()->find($arguments['taskId'] ?? 0)?->title ?? 'Tarjeta')
                ->modalSubmitActionLabel('Guardar tarjeta')
                ->modalWidth('5xl')
                ->fillForm(function (array $arguments): array {
                    $task = TaskCard::query()
                        ->with(['assignees', 'checklistItems', 'comments.user'])
                        ->findOrFail((int) ($arguments['taskId'] ?? 0));

                    return [
                        'task_id' => $task->id,
                        'title' => $task->title,
                        'description' => $task->description,
                        'status' => $task->status,
                        'assignee_ids' => $task->assignees->pluck('id')->all(),
                        'checklist' => $task->checklistItems->map(fn ($item): array => [
                            'label' => $item->label,
                            'completed' => (bool) $item->completed,
                        ])->all(),
                        'comments_history' => $this->commentsHistory($task),
                        'new_comment' => '',
                    ];
                })
                ->schema($this->taskFormSchema(true))
                ->action(function (array $data): void {
                    $this->authorizeBoard();
                    $task = TaskCard::query()->findOrFail((int) $data['task_id']);
                    $status = array_key_exists((string) ($data['status'] ?? ''), TaskCard::statuses())
                        ? (string) $data['status']
                        : $task->status;

                    DB::transaction(function () use ($task, $data, $status): void {
                        $task->update([
                            'title' => trim((string) $data['title']),
                            'description' => $this->nullableTrim($data['description'] ?? null),
                            'status' => $status,
                            'updated_by' => auth()->id(),
                        ]);

                        $this->syncAssignees($task, $data['assignee_ids'] ?? []);
                        $this->replaceChecklist($task, $data['checklist'] ?? []);

                        $comment = $this->nullableTrim($data['new_comment'] ?? null);
                        if ($comment !== null) {
                            $task->comments()->create([
                                'user_id' => auth()->id(),
                                'body' => $comment,
                            ]);
                        }
                    });

                    Notification::make()->success()->title('Tarjeta actualizada')->send();
                }),
        ];
    }

    /** @return array<int, mixed> */
    private function taskFormSchema(bool $editing): array
    {
        $schema = [];

        if ($editing) {
            $schema[] = Hidden::make('task_id')->required();
        }

        $schema[] = TextInput::make('title')
            ->label('Título')
            ->required()
            ->maxLength(160)
            ->columnSpanFull();

        $schema[] = Textarea::make('description')
            ->label('Descripción')
            ->rows(5)
            ->maxLength(10000)
            ->columnSpanFull();

        if ($editing) {
            $schema[] = Select::make('status')
                ->label('Estado')
                ->options(TaskCard::statuses())
                ->required();
        }

        $schema[] = Select::make('assignee_ids')
            ->label('Miembros asignados')
            ->options(fn (): array => User::query()
                ->whereNull('deleted_at')
                ->orderBy('nick')
                ->pluck('nick', 'id')
                ->all())
            ->multiple()
            ->searchable()
            ->preload()
            ->helperText('Puede haber varios jugadores asignados a la misma tarjeta.');

        $schema[] = Repeater::make('checklist')
            ->label('Checklist')
            ->schema([
                TextInput::make('label')
                    ->label('Elemento')
                    ->required()
                    ->maxLength(255),
                Toggle::make('completed')
                    ->label('Completado')
                    ->default(false),
            ])
            ->columns(4)
            ->reorderableWithButtons()
            ->collapsible()
            ->default([])
            ->addActionLabel('Añadir elemento')
            ->columnSpanFull();

        if ($editing) {
            $schema[] = Textarea::make('comments_history')
                ->label('Comentarios y actividad')
                ->rows(9)
                ->disabled()
                ->dehydrated(false)
                ->columnSpanFull();

            $schema[] = Textarea::make('new_comment')
                ->label('Añadir comentario')
                ->rows(3)
                ->maxLength(5000)
                ->placeholder('Escribe un comentario para la tarjeta...')
                ->columnSpanFull();
        }

        return $schema;
    }

    private function syncAssignees(TaskCard $task, array $userIds): void
    {
        $ids = collect($userIds)->map(fn ($id): int => (int) $id)->filter()->unique()->values();
        $validIds = User::query()->whereNull('deleted_at')->whereIn('id', $ids)->pluck('id');

        $task->assignees()->sync(
            $validIds->mapWithKeys(fn ($id): array => [(int) $id => ['assigned_by' => auth()->id()]])->all()
        );
    }

    private function replaceChecklist(TaskCard $task, array $items): void
    {
        $task->checklistItems()->delete();

        collect($items)
            ->filter(fn ($item): bool => filled($item['label'] ?? null))
            ->values()
            ->each(function (array $item, int $position) use ($task): void {
                $task->checklistItems()->create([
                    'label' => trim((string) $item['label']),
                    'completed' => (bool) ($item['completed'] ?? false),
                    'position' => $position + 1,
                    'updated_by' => auth()->id(),
                ]);
            });
    }

    private function commentsHistory(TaskCard $task): string
    {
        if ($task->comments->isEmpty()) {
            return 'Todavía no hay comentarios.';
        }

        return $task->comments
            ->map(fn ($comment): string => sprintf(
                '[%s] %s: %s',
                $comment->created_at?->format('d/m/Y H:i') ?? '',
                $comment->user?->nick ?? 'Usuario',
                trim((string) $comment->body),
            ))
            ->implode("\n\n");
    }

    private function nullableTrim(mixed $value): ?string
    {
        $value = trim((string) $value);

        return $value !== '' ? $value : null;
    }

    private function authorizeBoard(): void
    {
        abort_unless(static::canAccess(), 403);
    }
}
