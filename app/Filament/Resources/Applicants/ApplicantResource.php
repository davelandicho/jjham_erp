<?php

namespace App\Filament\Resources\Applicants;

use App\Filament\Resources\Applicants\Pages\ManageApplicants;
use App\Models\Applicant;
use BackedEnum;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Support\Enums\Width;
use Filament\Forms\Components\Select;
use Filament\Actions\ViewAction;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;

class ApplicantResource extends Resource
{
    protected static ?string $model = Applicant::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->columns(2)
            ->components([
                TextInput::make('last_name')
                    ->required(),   
                TextInput::make('first_name')
                    ->required(),
                TextInput::make('middle_name'),
                DatePicker::make('birthdate'),
                Select::make('sex')
                    ->options(['Male' => 'Male', 'Female' => 'Female']),
                Select::make('civil_status')->options([
                    'Single' => 'Single', 'Married' => 'Married',
                    'Widowed' => 'Widowed', 'Separated' => 'Separated',
                ]),
                TextInput::make('nationality')
                    ->required()
                    ->default('Filipino'),
                Textarea::make('address')
                    ->columnSpanFull(),
                TextInput::make('mobile'),
                TextInput::make('whatsapp'),
                TextInput::make('email')
                    ->label('Email address')
                    ->email(),
                TextInput::make('emergency_contact_name'),
                TextInput::make('emergency_contact_number'),
                Textarea::make('passport_number')
                    ->columnSpanFull(),
                DatePicker::make('passport_issue_date'),
                DatePicker::make('passport_expiry_date'),
                TextInput::make('passport_place_of_issue'),
                TextInput::make('height_cm')
                    ->label('Height')
                    ->numeric()
                    ->suffix('cm'),
                TextInput::make('weight_kg')
                    ->label('Weight')
                    ->numeric()
                    ->suffix('kg'),
                Textarea::make('experience')
                    ->columnSpanFull(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('last_name')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('first_name')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('middle_name')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('birthdate')
                    ->date()
                    ->sortable(),
                TextColumn::make('sex')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('civil_status')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('nationality')
                    ->searchable(),
                TextColumn::make('mobile')
                    ->searchable()
                    ->toggleable(),
                TextColumn::make('whatsapp')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('email')
                    ->label('Email address')
                    ->searchable(),
                TextColumn::make('emergency_contact_name')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('emergency_contact_number')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('passport_issue_date')
                    ->date()
                    ->sortable()
                    ->toggleable(),
                TextColumn::make('passport_expiry_date')
                    ->date()
                    ->sortable()
                    ->toggleable(),
                TextColumn::make('passport_place_of_issue')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('height_cm')
                    ->numeric()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('weight_kg')
                    ->numeric()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
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
                ViewAction::make()
                    ->slideOver()
                    ->modalWidth(Width::FourExtraLarge),
                EditAction::make()->modalWidth(Width::FourExtraLarge),
                DeleteAction::make(),
            ])
            ->recordAction(ViewAction::class)
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function infolist(Schema $schema): Schema
    {
    return $schema
        ->components([
            Section::make('Personal Information')
                ->columns(3)
                ->schema([
                    TextEntry::make('last_name'),
                    TextEntry::make('first_name'),
                    TextEntry::make('middle_name')->placeholder('—'),
                    TextEntry::make('birthdate')->date(),
                    TextEntry::make('sex'),
                    TextEntry::make('civil_status'),
                    TextEntry::make('nationality'),
                    TextEntry::make('height_cm')->label('Height')->suffix(' cm')->placeholder('—'),
                    TextEntry::make('weight_kg')->label('Weight')->suffix(' kg')->placeholder('—'),
                ]),

            Section::make('Contact')
                ->columns(3)
                ->schema([
                    TextEntry::make('mobile')->placeholder('—'),
                    TextEntry::make('whatsapp')->placeholder('—'),
                    TextEntry::make('email')->label('Email address')->placeholder('—'),
                    TextEntry::make('address')->columnSpanFull()->placeholder('—'),
                    TextEntry::make('emergency_contact_name')->label('Emergency contact')->placeholder('—'),
                    TextEntry::make('emergency_contact_number')->label('Emergency number')->placeholder('—'),
                ]),

            Section::make('Passport')
                ->columns(3)
                ->schema([
                    TextEntry::make('passport_number')
                        ->formatStateUsing(fn (?string $state) => $state
                            ? str_repeat('•', max(strlen($state) - 3, 0)) . substr($state, -3)
                            : null)
                        ->placeholder('—'),
                    TextEntry::make('passport_issue_date')->date()->placeholder('—'),
                    TextEntry::make('passport_expiry_date')->date()->placeholder('—'),
                    TextEntry::make('passport_place_of_issue')->placeholder('—'),
                ]),

            Section::make('Experience')
                ->schema([
                    TextEntry::make('experience')->placeholder('—')->columnSpanFull(),
                ]),
        ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageApplicants::route('/'),
        ];
    }
}
