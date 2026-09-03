<?php
namespace App\Filament\Resources\ContactSubmissions\Tables;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
class ContactSubmissionsTable { public static function configure(Table $table): Table { return $table->columns([IconColumn::make('is_recruitment')->label('Alistamiento')->boolean(),TextColumn::make('email')->searchable(),TextColumn::make('message')->label('Mensaje')->limit(70)->wrap(),TextColumn::make('created_at')->label('Recibido')->dateTime('d/m/Y H:i')->sortable(),IconColumn::make('read_at')->label('Leída')->boolean()->state(fn ($record): bool => filled($record->read_at))])->filters([TernaryFilter::make('is_recruitment')->label('Alistamiento')])->defaultSort('created_at','desc')->recordActions([EditAction::make()]); } }
