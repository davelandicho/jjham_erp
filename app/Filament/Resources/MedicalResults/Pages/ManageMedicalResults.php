<?php

namespace App\Filament\Resources\MedicalResults\Pages;

use App\Filament\Resources\MedicalResults\MedicalResultResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageMedicalResults extends ManageRecords
{
    protected static string $resource = MedicalResultResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->label('Record medical result')
                ->modalHeading('Record medical result')
                ->createAnother(false),
        ];
    }
}