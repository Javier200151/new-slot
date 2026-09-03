<?php
namespace App\Filament\Resources\ContactSubmissions\Pages;
use App\Filament\Resources\ContactSubmissions\ContactSubmissionResource;
use Filament\Resources\Pages\EditRecord;
class EditContactSubmission extends EditRecord { protected static string $resource = ContactSubmissionResource::class; protected function mutateFormDataBeforeSave(array $data): array { $data['read_at'] ??= now(); return $data; } }
