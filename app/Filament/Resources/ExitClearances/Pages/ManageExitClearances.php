<?php

namespace App\Filament\Resources\ExitClearances\Pages;

use App\Filament\Resources\ExitClearances\ExitClearanceResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageExitClearances extends ManageRecords
{
    protected static string $resource = ExitClearanceResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->label('Record RFP'),
        ];
    }
}
