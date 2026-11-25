<?php

namespace App\Filament\Resources\ProfessionalRoles\Pages;

use App\Filament\Resources\ProfessionalRoles\ProfessionalRoleResource;
use App\Filament\Support\CatalogDeletion;
use Filament\Resources\Pages\EditRecord;

class EditProfessionalRole extends EditRecord
{
    protected static string $resource = ProfessionalRoleResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CatalogDeletion::deleteAction(),
        ];
    }
}
