<?php
namespace App\Filament\Resources\RecruitmentReinforcementAreas\Schemas;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;
class RecruitmentReinforcementAreaForm { public static function configure(Schema $schema): Schema { return $schema->components([
    TextInput::make('name')->label('Nombre')->required()->maxLength(255)->unique(ignoreRecord: true),
    Toggle::make('active')->label('Activa')->default(true),
    Textarea::make('description')->label('Descripción')->rows(3)->columnSpanFull(),
]); } }
