<?php

namespace App\Filament\Resources\ProfessionalFunctions\Pages;

use App\Filament\Resources\ProfessionalFunctions\ProfessionalFunctionResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListProfessionalFunctions extends ListRecords
{
    protected static string $resource = ProfessionalFunctionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
