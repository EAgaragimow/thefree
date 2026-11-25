<?php

namespace App\Filament\Resources\ProfessionalRoles\Pages;

use App\Filament\Resources\ProfessionalRoles\ProfessionalRoleResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListProfessionalRoles extends ListRecords
{
    protected static string $resource = ProfessionalRoleResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
