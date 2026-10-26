<?php

namespace App\Filament\Resources\EmployerCommunications\Pages;

use App\Filament\Resources\EmployerCommunications\EmployerCommunicationResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageEmployerCommunications extends ManageRecords
{
    protected static string $resource = EmployerCommunicationResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
