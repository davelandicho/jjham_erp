<?php

namespace App\Filament\Resources\EmploymentStatusLogs\Pages;

use App\Filament\Resources\EmploymentStatusLogs\EmploymentStatusLogResource;
use App\Models\EmploymentStatusLog;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageEmploymentStatusLogs extends ManageRecords
{
    protected static string $resource = EmploymentStatusLogResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->label('Log status')
                ->using(fn (array $data, CreateAction $action) => EmploymentStatusLogResource::attempt(
                    $action, fn () => EmploymentStatusLog::create($data)
                )),
        ];
    }
}