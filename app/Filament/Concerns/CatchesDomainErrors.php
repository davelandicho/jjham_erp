<?php

namespace App\Filament\Concerns;

use Filament\Actions\Action;
use Filament\Notifications\Notification;

trait CatchesDomainErrors
{

    public static function attempt(Action $action, callable $callback): mixed
    {
        try {
            return $callback();
        } catch (\DomainException $e) {
            Notification::make()
                ->title('Not allowed')
                ->body($e->getMessage())
                ->danger()
                ->send();

            $action->halt();
        }

        return null;
    }
}