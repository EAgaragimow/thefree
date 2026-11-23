<?php

namespace App\Filament\Support;

use Closure;
use DomainException;
use Filament\Notifications\Notification;
use Illuminate\Validation\ValidationException;

class RunsAdminAction
{
    /**
     * Run a domain action and report its outcome as a Filament notification.
     *
     * Domain rules (such as "only pending applications can be approved") surface as a
     * readable error instead of an exception page.
     */
    public static function run(Closure $callback, string $successTitle): void
    {
        try {
            $callback();
        } catch (DomainException $exception) {
            Notification::make()->title($exception->getMessage())->danger()->send();

            return;
        } catch (ValidationException $exception) {
            Notification::make()->title(collect($exception->errors())->flatten()->first())->danger()->send();

            return;
        }

        Notification::make()->title($successTitle)->success()->send();
    }
}
