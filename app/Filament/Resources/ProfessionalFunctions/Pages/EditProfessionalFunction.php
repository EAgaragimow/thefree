<?php

namespace App\Filament\Resources\ProfessionalFunctions\Pages;

use App\Filament\Resources\ProfessionalFunctions\ProfessionalFunctionResource;
use App\Filament\Support\CatalogDeletion;
use Filament\Resources\Pages\EditRecord;

class EditProfessionalFunction extends EditRecord
{
    protected static string $resource = ProfessionalFunctionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CatalogDeletion::deleteAction(),
        ];
    }
}
