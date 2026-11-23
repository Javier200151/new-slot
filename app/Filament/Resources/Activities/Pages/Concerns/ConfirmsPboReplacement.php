<?php

namespace App\Filament\Resources\Activities\Pages\Concerns;

use App\Services\ActivityPboExportService;
use App\Services\PersistentCampaignPbo;
use App\Support\ActivityPbo;
use Filament\Actions\Action;
use Illuminate\Validation\ValidationException;

trait ConfirmsPboReplacement
{
    /** Confirmation applies to one uploaded basename, not all future changes. */
    public ?string $approvedPboReplacementName = null;

    /** Called by FileUpload immediately after a temporary PBO has arrived. */
    public function checkPboReplacement(string $filename): void
    {
        $this->approvedPboReplacementName = null;
        if (! ActivityPbo::validFilename($filename)) { return; }

        if (app(ActivityPboExportService::class)->replacementRequired($filename, $this->record?->id)) {
            $this->mountAction('approvePboReplacement', ['filename' => $filename]);
        } else {
            $this->dispatch('sqa-activity-pbo-uploaded');
        }
    }

    public function rejectPboReplacement(): void
    {
        $this->approvedPboReplacementName = null;
        $this->data['pbo_path'] = $this->record?->pbo_path;
    }

    protected function approvePboReplacementAction(): Action
    {
        return Action::make('approvePboReplacement')
            ->hidden() // Available for mounting via FileUpload; not another header button.
            ->modalHeading('Ya existe un PBO con este nombre')
            ->modalDescription(function (array $arguments): string {
                $filename = (string) ($arguments['filename'] ?? '');
                $campaignId = (int) ($this->data['campaign_id'] ?? $this->record?->campaign_id ?? 0);
                if (PersistentCampaignPbo::campaignKey($campaignId)) {
                    $others = \App\Models\Activity::withTrashed()->where('campaign_id', $campaignId)
                        ->whereNotNull('pbo_path')->pluck('name')->take(10)->implode(', ');
                    if ($others) { return 'Al sustituir ' . $filename . ' también actualizarás las actividades de la campaña: ' . $others . '.'; }
                }
                return 'El archivo ' . $filename . ' ya existe. ¿Quieres reemplazarlo?';
            })
            ->modalSubmitActionLabel('Reemplazar archivo')
            ->modalCancelAction(false)
            ->modalCloseButton(false)
            ->closeModalByClickingAway(false)
            ->closeModalByEscaping(false)
            ->extraModalFooterActions([
                Action::make('cancelPboUpload')
                    ->label('Cancelar subida')
                    ->color('gray')
                    ->action(fn () => $this->rejectPboReplacement())
                    ->cancelParentActions(),
            ])
            ->action(function (array $arguments): void {
                $filename = (string) ($arguments['filename'] ?? '');
                if (! ActivityPbo::validFilename($filename)) {
                    $this->rejectPboReplacement();
                    return;
                }
                $this->approvedPboReplacementName = $filename;
                // The ordinary Save button, or the existing save prompt, persists the upload.
                $this->dispatch('sqa-activity-pbo-uploaded');
            });
    }

    /** Verify once more at persistence time: never silently replace after a stale UI. */
    protected function assertPboReplacementApproved(?string $newPath, ?string $oldPath = null): void
    {
        if (! is_string($newPath) || $newPath === $oldPath) { return; }
        $filename = basename($newPath);
        $campaignId = (int) ($this->data['campaign_id'] ?? $this->record?->campaign_id ?? 0);
        $shared = PersistentCampaignPbo::source($campaignId, $this->record?->id);
        if ($shared && $shared->pbo_path === $newPath) { return; } // Link, not replacement.
        if (app(ActivityPboExportService::class)->replacementRequired($filename, $this->record?->id)
            && strcasecmp($this->approvedPboReplacementName ?? '', $filename) !== 0) {
            throw ValidationException::withMessages([
                'data.pbo_path' => 'Ya existe un PBO con este nombre. Confirma su sustitución o cancela la subida.',
            ]);
        }
    }
}
