<?php

namespace App\Filament\Resources\RecruitmentApplications;

use App\Filament\Resources\RecruitmentApplications\Pages\BoardRecruitmentApplications;
use App\Filament\Resources\RecruitmentApplications\Pages\EditRecruitmentApplication;
use App\Filament\Resources\RecruitmentApplications\Pages\ListRecruitmentApplications;
use App\Filament\Resources\RecruitmentApplications\RelationManagers\CommentsRelationManager;
use App\Filament\Resources\RecruitmentApplications\Schemas\RecruitmentApplicationForm;
use App\Filament\Resources\RecruitmentApplications\Tables\RecruitmentApplicationsTable;
use App\Models\ContactSubmission;
use App\Models\User;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

class RecruitmentApplicationResource extends Resource
{
    protected static ?string $model = ContactSubmission::class;

    protected static string|UnitEnum|null $navigationGroup = 'Reclutamiento';

    protected static ?int $navigationSort = 0;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClipboardDocumentCheck;

    protected static ?string $navigationLabel = 'Alistamiento';

    protected static ?string $modelLabel = 'Solicitud de alistamiento';

    protected static ?string $pluralModelLabel = 'Solicitudes de alistamiento';

    public static function form(Schema $schema): Schema
    {
        return RecruitmentApplicationForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return RecruitmentApplicationsTable::configure($table);
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->where('is_recruitment', true)
            ->with([
                'recruitmentReviewedBy',
                'recruitmentInterviewer',
                'recruitmentMatchedUser.status',
                'recruitmentTierMarkedBy',
                'recruitmentTierRatings.user',
            ])
            ->withCount('recruitmentComments');
    }

    public static function getRelations(): array
    {
        return [
            CommentsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => BoardRecruitmentApplications::route('/'),
            'list' => ListRecruitmentApplications::route('/lista'),
            'edit' => EditRecruitmentApplication::route('/{record}/edit'),
        ];
    }

    public static function getNavigationBadge(): ?string
    {
        if (! static::canViewAny()) {
            return null;
        }

        return (string) static::getEloquentQuery()
            ->where('recruitment_review_status', ContactSubmission::REVIEW_UNREVIEWED)
            ->count();
    }


    public static function interviewerOptions(): array
    {
        return User::permission('recruitment-applications.manage')
            ->orderBy('nick')
            ->pluck('nick', 'id')
            ->all();
    }

    public static function canViewAny(): bool
    {
        return (bool) auth()->user()?->can('recruitment-applications.manage');
    }

    public static function canView(Model $record): bool
    {
        return static::canViewAny() && (bool) $record->is_recruitment;
    }

    public static function canEdit(Model $record): bool
    {
        return static::canView($record);
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canDelete(Model $record): bool
    {
        return false;
    }

    public static function canDeleteAny(): bool
    {
        return false;
    }
}
