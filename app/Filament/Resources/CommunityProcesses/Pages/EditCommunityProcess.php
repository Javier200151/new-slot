<?php

namespace App\Filament\Resources\CommunityProcesses\Pages;

use App\Filament\Resources\CommunityProcesses\CommunityProcessResource;
use App\Models\CommunityPoll;
use App\Models\CommunityProcess;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Support\Facades\DB;

class EditCommunityProcess extends EditRecord
{
    protected static string $resource = CommunityProcessResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('createPollFromApplications')
                ->label('Crear votación con candidaturas')
                ->icon('heroicon-o-chart-bar-square')
                ->color('success')
                ->visible(fn (): bool => ! $this->record->poll
                    && $this->record->activeApplications()->exists())
                ->form([
                    TextInput::make('title')
                        ->label('Título de la votación')
                        ->default(fn (): string => 'Votación · ' . $this->record->title)
                        ->required()
                        ->maxLength(180),
                    DateTimePicker::make('starts_at')
                        ->label('Inicio')
                        ->seconds(false)
                        ->default(now()),
                    DateTimePicker::make('ends_at')
                        ->label('Cierre')
                        ->seconds(false)
                        ->after('starts_at'),
                    Select::make('selection_mode')
                        ->label('Selección')
                        ->options([
                            CommunityPoll::MODE_SINGLE => 'Una sola candidatura',
                            CommunityPoll::MODE_MULTIPLE => 'Varias candidaturas',
                        ])
                        ->default(
                            fn (): string => ($this->record->max_winners ?? 1) > 1
                                ? CommunityPoll::MODE_MULTIPLE
                                : CommunityPoll::MODE_SINGLE
                        )
                        ->required(),
                    TextInput::make('max_choices')
                        ->label('Máximo de candidaturas')
                        ->numeric()
                        ->minValue(1)
                        ->default(fn (): int => max(1, (int) ($this->record->max_winners ?: 1))),
                    Toggle::make('allow_vote_change')
                        ->label('Permitir cambiar el voto')
                        ->default(true),
                    Toggle::make('is_anonymous')
                        ->label('Voto anónimo')
                        ->default(true),
                    Select::make('results_visibility')
                        ->label('Resultados')
                        ->options([
                            CommunityPoll::RESULTS_ALWAYS => 'Siempre visibles',
                            CommunityPoll::RESULTS_AFTER_VOTE => 'Después de votar',
                            CommunityPoll::RESULTS_AFTER_CLOSE => 'Solo al cerrar',
                            CommunityPoll::RESULTS_HIDDEN => 'Ocultos',
                        ])
                        ->default(CommunityPoll::RESULTS_AFTER_CLOSE)
                        ->required(),
                    TextInput::make('quorum_percent')
                        ->label('Quórum mínimo (%)')
                        ->numeric()
                        ->minValue(1)
                        ->maxValue(100),
                ])
                ->modalHeading('Crear votación desde las postulaciones')
                ->modalDescription('Las candidaturas activas se convertirán automáticamente en opciones de voto y la votación quedará enlazada al proceso.')
                ->modalSubmitActionLabel('Crear votación')
                ->action(function (array $data): void {
                    $process = $this->record->fresh(['activeApplications.user']);

                    DB::transaction(function () use ($process, $data): void {
                        $mode = $data['selection_mode'] ?? CommunityPoll::MODE_SINGLE;
                        $maxChoices = $mode === CommunityPoll::MODE_MULTIPLE
                            ? max(1, (int) ($data['max_choices'] ?? $process->max_winners ?? 1))
                            : 1;

                        $poll = CommunityPoll::create([
                            'community_process_id' => $process->id,
                            'title' => $data['title'],
                            'description' => 'Votación vinculada a la convocatoria «' . $process->title . '».',
                            'is_published' => true,
                            'selection_mode' => $mode,
                            'min_choices' => 1,
                            'max_choices' => $maxChoices,
                            'allow_vote_change' => (bool) ($data['allow_vote_change'] ?? true),
                            'is_anonymous' => (bool) ($data['is_anonymous'] ?? true),
                            'results_visibility' => $data['results_visibility'] ?? CommunityPoll::RESULTS_AFTER_CLOSE,
                            'show_voter_names' => false,
                            'show_participation' => true,
                            'allow_abstain' => true,
                            'randomize_options' => false,
                            'quorum_percent' => $data['quorum_percent'] ?? null,
                            'starts_at' => $data['starts_at'] ?? now(),
                            'ends_at' => $data['ends_at'] ?? null,
                            'created_by' => auth()->id(),
                        ]);

                        foreach ($process->activeApplications as $index => $application) {
                            $poll->options()->create([
                                'candidate_user_id' => $application->user_id,
                                'label' => $application->user?->nick ?? 'Candidatura #' . $application->id,
                                'sort_order' => ($index + 1) * 10,
                            ]);
                        }

                        $process->update(['status' => CommunityProcess::STATUS_VOTING]);
                    });

                    Notification::make()
                        ->title('Votación creada')
                        ->body('Las candidaturas se han convertido en opciones de voto.')
                        ->success()
                        ->send();

                    $this->refreshFormData(['status']);
                }),
            DeleteAction::make(),
        ];
    }
}
