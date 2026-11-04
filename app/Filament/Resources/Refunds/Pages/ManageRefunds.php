<?php

namespace App\Filament\Resources\Refunds\Pages;

use App\Filament\Resources\Refunds\RefundResource;
use App\Models\Refund;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageRefunds extends ManageRecords
{
    protected static string $resource = RefundResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->label('Request refund')
                ->using(fn (array $data, CreateAction $action) => RefundResource::attempt(
                    $action, fn () => Refund::create($data)
                )),
        ];
    }
}