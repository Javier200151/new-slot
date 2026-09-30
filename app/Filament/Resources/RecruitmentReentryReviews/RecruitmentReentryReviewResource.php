<?php
namespace App\Filament\Resources\RecruitmentReentryReviews;
use App\Filament\Resources\RecruitmentReentryReviews\Pages\ListRecruitmentReentryReviews;
use App\Filament\Resources\RecruitmentReentryReviews\Tables\RecruitmentReentryReviewsTable;
use App\Models\RecruitmentReentryReview;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;
class RecruitmentReentryReviewResource extends Resource
{
 protected static ?string $model=RecruitmentReentryReview::class;
 protected static string|UnitEnum|null $navigationGroup='Tutores';
 protected static string|BackedEnum|null $navigationIcon=Heroicon::OutlinedExclamationTriangle;
 protected static ?string $navigationLabel='Reincorporaciones pendientes';
 protected static ?string $pluralModelLabel='Reincorporaciones pendientes';
 protected static ?int $navigationSort=3;
 public static function table(Table $table):Table{return RecruitmentReentryReviewsTable::configure($table);}
 public static function getEloquentQuery():Builder{return parent::getEloquentQuery()->whereNull('resolved_at')->whereNotNull('pending_user_id')->with(['user','previousPeriod','tutorialTutor']);}
 public static function getPages():array{return['index'=>ListRecruitmentReentryReviews::route('/')];}
 public static function canCreate():bool{return false;}
 public static function getNavigationBadge():?string{return(string)static::getEloquentQuery()->count();}
}
