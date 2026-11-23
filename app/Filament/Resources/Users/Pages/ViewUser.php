<?php

namespace App\Filament\Resources\Users\Pages;

use App\Enums\UserStatus;
use App\Filament\Resources\Users\UserResource;
use Filament\Resources\Pages\ViewRecord;
use Filament\Support\Icons\Heroicon;

class ViewUser extends ViewRecord
{
    protected static string $resource = UserResource::class;

    protected function getHeaderActions(): array
    {
        return [
            UserResource::statusAction('restrict', 'Restrict', UserStatus::Restricted, 'warning', Heroicon::OutlinedNoSymbol),
            UserResource::statusAction('suspend', 'Suspend', UserStatus::Suspended, 'danger', Heroicon::OutlinedLockClosed),
            UserResource::statusAction('reinstate', 'Reinstate', UserStatus::Active, 'success', Heroicon::OutlinedCheckCircle),
            UserResource::deleteAction(),
            UserResource::restoreAction(),
        ];
    }
}
