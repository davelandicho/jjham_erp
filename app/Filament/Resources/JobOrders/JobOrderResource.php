<?php

namespace App\Filament\Resources\JobOrders;

use App\Filament\Resources\JobOrders\Pages\ManageJobOrders;
use App\Models\JobOrder;
use BackedEnum;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\CheckboxList;

class JobOrderResource extends Resource
{
    protected static ?string $model = JobOrder::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('employer_id')
                    ->label('Employer')
                    ->relationship('employer', 'name')
                    ->searchable()
                    ->preload()
                    ->required(),
                TextInput::make('position')
                    ->required(),
                TextInput::make('openings')
                    ->required()
                    ->numeric()
                    ->default(1),
                TextInput::make('salary')
                    ->label('Salary')
                    ->numeric()
                    ->prefix('AED')
                    ->default(0),
                Textarea::make('notes')
                    ->columnSpanFull(),
                CheckboxList::make('documentTypes')
                    ->label('Required documents (checklist)')
                    ->relationship('documentTypes', 'name')
                    ->columns(3)
                    ->bulkToggleable()
                    ->columnSpanFull(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('position')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('employer.name')
                    ->label('Employer')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('document_types_count')
                    ->label('Checklist')
                    ->counts('documentTypes')
                    ->suffix(' docs')
                    ->sortable(),
                TextColumn::make('openings')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('salary')
                    ->money('AED'),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                //
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                    DeleteBulkAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageJobOrders::route('/'),
        ];
    }
}
