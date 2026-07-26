<?php

namespace App\Filament\Resources\EventComments\Pages;

use App\Filament\Resources\EventComments\EventCommentResource;
use Filament\Resources\Pages\CreateRecord;

class CreateEventComment extends CreateRecord
{
    protected static string $resource = EventCommentResource::class;
}
