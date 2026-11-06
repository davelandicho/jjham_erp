<?php

namespace App\Filament\Resources\DocumentTypes;

use App\Filament\Resources\DocumentTypes\Pages\ManageDocumentTypes;
use App\Models\DocumentType;
use App\Enums\NavigationGroup;
use BackedEnum;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Notifications\Notification;
use UnitEnum;

class DocumentTypeResource extends Resource
{
    protected static ?string $model = DocumentType::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedTag;

    protected static string|UnitEnum|null $navigationGroup = NavigationGroup::Settings;

    protected static ?int $navigationSort = 1;

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->required()
                    ->maxLength(255)
                    ->unique(ignoreRecord: true),
                Toggle::make('has_expiry')
                    ->required()
                    ->label('Has Expiry Date')
                    ->helperText('Turn on for passport, medical certificate, visa and exit clearance.'),
                Textarea::make('description')
                    ->columnSpanFull(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->searchable()
                    ->sortable(),
                IconColumn::make('has_expiry')
                    ->boolean()
                    ->label('Expires'),
                TextColumn::make('description')
                    ->limit(50)
                    ->placeholder('-')
                    ->toggleable(),
            ])

            ->defaultSort('name')
            ->recordActions([
                EditAction::make(),
                DeleteAction::make()
                   ->before(function (DeleteAction $action, DocumentType $record) {
                       if ($record->jobOrders()->exists()) {
                           Notification::make()
                               ->danger()
                               ->title('Cannot delete document type')
                               ->body('This document type is on a job order checklist. Remove it from those checklists first.')
                               ->send();

                           $action->cancel();
                       }
                   }),  
            ])
            ->toolbarActions([
                    DeleteBulkAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageDocumentTypes::route('/'),
        ];
    }
}
