<?php

namespace App\Filament\Resources\Applicants;

use App\Filament\Resources\Applicants\Pages\ManageApplicants;
use App\Models\Applicant;
use App\Enums\NavigationGroup;
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
use App\Support\PassportScanReader;
use Filament\Forms\Components\FileUpload;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Utilities\Set;
use Illuminate\Support\Arr;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use UnitEnum;

class ApplicantResource extends Resource
{
    protected static ?string $model = Applicant::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUsers;

    protected static string|UnitEnum|null $navigationGroup = NavigationGroup::Recruitment;

    protected static ?int $navigationSort = 1;

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->columns(2)
            ->components([
                FileUpload::make('passport_scan')
                    ->label('Scan passport (auto-fill)')
                    ->helperText('Upload the bio page as JPG or PNG. The fields below fill in automatically; check them against the passport before saving.')
                    ->image()
                    ->acceptedFileTypes(['image/jpeg', 'image/png'])
                    ->maxSize(10240)
                    ->storeFiles(false)
                    ->dehydrated(false)
                    ->live()
                    ->afterStateUpdated(fn ($state, Set $set) => static::fillFromScan($state, $set))
                    ->columnSpanFull(),
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

    protected static function fillFromScan($state, Set $set): void
    {
        $file = collect(Arr::wrap($state))->first();

        if (! $file instanceof TemporaryUploadedFile) {
            return; // file was removed, nothing to read
        }

        $mrz = PassportScanReader::read($file->getRealPath());

        if (! $mrz) {
            Notification::make()
                ->title('No passport MRZ found')
                ->body('Try a straight, clear scan that shows the two lines at the bottom of the bio page.')
                ->warning()
                ->send();

            return;
        }

        foreach (['last_name', 'first_name', 'birthdate', 'sex', 'passport_number', 'passport_expiry_date'] as $field) {
            if (filled($mrz[$field])) {
                $set($field, $mrz[$field]);
            }
        }

        if ($mrz['nationality_code'] === 'PHL') {
            $set('nationality', 'Filipino');
        }

        $failed = array_keys(array_filter($mrz['checks'], fn (bool $ok) => ! $ok));

        if ($failed) {
            Notification::make()
                ->title('Check these against the passport: ' . str_replace('_', ' ', implode(', ', $failed)))
                ->body('The scan was read, but these check digits did not match, so the reading may be wrong.')
                ->warning()
                ->persistent()
                ->send();

            return;
        }

        Notification::make()
            ->title('Passport read')
            ->body('Review the filled fields, then type the middle name, issue date and place of issue.')
            ->success()
            ->send();
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
                    ->modalWidth(Width::FiveExtraLarge),
                EditAction::make()
                    ->modalWidth(Width::FourExtraLarge),
                DeleteAction::make(),
            ])
            ->recordAction(ViewAction::class)
            ->toolbarActions([
                DeleteBulkAction::make()
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
                ->columns(2)
                ->schema([
                    TextEntry::make('mobile')->placeholder('—'),
                    TextEntry::make('whatsapp')->label('WhatsApp')->placeholder('—'),
                    TextEntry::make('email')->label('Email address')->placeholder('—'),
                    TextEntry::make('address')->placeholder('—'),
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
