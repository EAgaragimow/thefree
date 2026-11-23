<?php

namespace App\Filament\Resources\FreelancerApplications\Pages;

use App\Filament\Resources\FreelancerApplications\FreelancerApplicationResource;
use Filament\Resources\Pages\ViewRecord;

class ViewFreelancerApplication extends ViewRecord
{
    protected static string $resource = FreelancerApplicationResource::class;

    protected function getHeaderActions(): array
    {
        return [
            FreelancerApplicationResource::approveAction(),
            FreelancerApplicationResource::rejectAction(),
            FreelancerApplicationResource::resendAction(),
        ];
    }
}
