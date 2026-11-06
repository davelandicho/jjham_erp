<?php

namespace App\Filament\Resources\Clinics;

use App\Filament\Resources\Clinics\Pages\ManageClinics;
use App\Models\Clinic;
use App\Enums\NavigationGroup;
use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use UnitEnum;

class ClinicResource extends Resource
{
    protected static ?string $model = Clinic::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBuildingOffice2;

    protected static string|UnitEnum|null $navigationGroup = NavigationGroup::Settings;

    protected static ?int $navigationSort = 2;

    protected static ?string $navigationLabel = 'Preferred clinics';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->required()
                    ->maxLength(255)
                    ->unique(ignoreRecord: true),
                TextInput::make('contact_number')
                    ->tel()
                    ->maxLength(255),
                Textarea::make('address')
                    ->columnSpanFull(),
                Toggle::make('is_active')
                    ->label('Active')
                    ->default(true)
                    ->helperText('Turn off instead of deleting. Inactive clinics disappear from the medicaults.'),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('contact_number')
                    ->placeholder('—'),
                TextColumn::make('address')
                    ->limit(40)
                    ->placeholder('—')
                    ->toggleable(),
                IconColumn::make('is_active')
                    ->label('Active')
                    ->boolean(),
            ])
            ->defaultSort('name')
            ->filters([
                TernaryFilter::make('is_active')
                    ->label('Active'),
            ])
            ->recordActions([
                EditAction::make(),
                // No Delete on purpose — deactivate instead, so old medical results keep their clinic.
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageClinics::route('/'),
        ];
    }
}