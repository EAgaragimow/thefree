<?php

namespace App\Filament\Resources\FreelancerProfiles\Pages;

use App\Filament\Resources\FreelancerProfiles\FreelancerProfileResource;
use Filament\Resources\Pages\ViewRecord;

class ViewFreelancerProfile extends ViewRecord
{
    protected static string $resource = FreelancerProfileResource::class;

    protected function getHeaderActions(): array
    {
        return [
            FreelancerProfileResource::featureAction(),
            FreelancerProfileResource::unfeatureAction(),
        ];
    }
}
