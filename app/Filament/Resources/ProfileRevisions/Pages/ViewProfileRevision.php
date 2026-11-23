<?php

namespace App\Filament\Resources\ProfileRevisions\Pages;

use App\Filament\Resources\ProfileRevisions\ProfileRevisionResource;
use Filament\Resources\Pages\ViewRecord;

class ViewProfileRevision extends ViewRecord
{
    protected static string $resource = ProfileRevisionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ProfileRevisionResource::approveAction(),
            ProfileRevisionResource::requestChangesAction(),
        ];
    }
}
