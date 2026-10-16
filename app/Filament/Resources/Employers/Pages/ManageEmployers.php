<?php

namespace App\Filament\Resources\Employers\Pages;

use App\Filament\Resources\Employers\EmployerResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageEmployers extends ManageRecords
{
    protected static string $resource = EmployerResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
