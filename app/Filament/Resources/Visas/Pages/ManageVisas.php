<?php

namespace App\Filament\Resources\Visas\Pages;

use App\Filament\Resources\Visas\VisaResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;
use App\Models\Application;
use App\Models\Visa;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Support\Icons\Heroicon;

class ManageVisas extends ManageRecords
{
    protected static string $resource = VisaResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('recordReleased')
                ->label('Record released visa')
                ->icon(Heroicon::OutlinedDocumentArrowDown)
                ->color('success')
                ->modalDescription('Use this when the employer emails the visa PDF.')
                ->schema([
                    VisaResource::applicationSelect(),
                    DatePicker::make('applied_on')
                        ->label('Date the employer filed')
                        ->helperText('Optional. Leave empty if the employer did not say.')
                        ->maxDate(today()),
                    ...VisaResource::releaseFields(),
                ])
                ->action(fn (array $data, Action $action) => VisaResource::attempt(
                    $action, fn () => Visa::recordReleased(
                        Application::findOrFail($data['application_id']),
                        $data,
                    )
                )),

            CreateAction::make()
                ->label('Record filing only')
                ->color('gray'),
        ];
    }
}
