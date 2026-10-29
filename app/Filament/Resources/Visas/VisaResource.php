<?php

namespace App\Filament\Resources\Visas;

use App\Enums\VisaStatus;
use App\Filament\Resources\Visas\Pages\ManageVisas;
use App\Models\Application;
use App\Models\Visa;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Storage;

class VisaResource extends Resource
{
    protected static ?string $model = Visa::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedGlobeAlt;

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('application_id')
                    ->label('Application')
                    ->options(fn () => Application::with(['applicant', 'jobOrder'])
                        ->latest('id')
                        ->get()
                        ->mapWithKeys(fn (Application $a) => [
                            $a->id => "{$a->applicant->full_name} — {$a->jobOrder->position} ({$a->status->getLabel()})",
                        ]))
                    ->searchable()
                    ->required()
                    ->disabledOn('edit'),

                DatePicker::make('applied_on')
                    ->label('Date the employer filed')
                    ->default(today())
                    ->maxDate(today())
                    ->required(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('application.applicant.last_name')
                    ->label('Last name')
                    ->searchable(),
                TextColumn::make('application.applicant.first_name')
                    ->label('First name')
                    ->searchable(),
                TextColumn::make('status')
                    ->badge()
                    ->description(fn (Visa $record) => $record->denied_reason),
                TextColumn::make('visa_number')
                    ->label('Visa number')
                    ->placeholder('—')
                    ->searchable(),
                TextColumn::make('applied_on')
                    ->label('Filed')
                    ->date('M j, Y')
                    ->sortable(),
                TextColumn::make('released_on')
                    ->label('Released')
                    ->date('M j, Y')
                    ->placeholder('—'),
                TextColumn::make('expires_on')
                    ->label('Expires')
                    ->date('M j, Y')
                    ->placeholder('—')
                    ->color(fn (Visa $record) => $record->isExpired() ? 'danger' : null)
                    ->sortable(),
            ])
            ->defaultSort('id', 'desc')
            ->filters([
                SelectFilter::make('status')
                    ->options(VisaStatus::class),
            ])
            ->recordActions([
                Action::make('visaFile')
                    ->label('Visa copy')
                    ->icon(Heroicon::OutlinedEye)
                    ->color('gray')
                    ->visible(fn (Visa $record) => filled($record->visa_path))
                    ->url(fn (Visa $record) => Storage::disk('local')
                        ->temporaryUrl($record->visa_path, now()->addMinutes(5)))
                    ->openUrlInNewTab(),

                Action::make('release')
                    ->label('Release')
                    ->icon(Heroicon::OutlinedCheckBadge)
                    ->color('success')
                    ->visible(fn (Visa $record) => $record->status === VisaStatus::Applied)
                    ->schema([
                        TextInput::make('visa_number')
                            ->label('Visa / entry permit number')
                            ->required()
                            ->maxLength(255),
                        DatePicker::make('released_on')
                            ->label('Date released')
                            ->default(today())
                            ->maxDate(today())
                            ->required(),
                        DatePicker::make('expires_on')
                            ->label('Expiry date')
                            ->after('released_on')
                            ->required(),
                        FileUpload::make('visa_path')
                            ->label('Visa copy')
                            ->disk('local')
                            ->directory('visas')
                            ->visibility('private')
                            ->acceptedFileTypes(['image/jpeg', 'image/png', 'application/pdf'])
                            ->maxSize(10240)
                            ->storeFileNamesIn('visa_name')
                            ->required(),
                    ])
                    ->action(fn (Visa $record, array $data) => $record->release(
                        $data['visa_number'],
                        $data['released_on'],
                        $data['expires_on'],
                        $data['visa_path'],
                        $data['visa_name'] ?? basename($data['visa_path']),
                    )),

                Action::make('deny')
                    ->label('Deny')
                    ->icon(Heroicon::OutlinedXCircle)
                    ->color('danger')
                    ->visible(fn (Visa $record) => $record->status === VisaStatus::Applied)
                    ->modalDescription('If the employer re-files, add a new visa record afterwards.')
                    ->schema([
                        Textarea::make('reason')
                            ->label('Why was the visa denied?')
                            ->required(),
                    ])
                    ->action(fn (Visa $record, array $data) => $record->deny($data['reason'])),

                EditAction::make()
                    ->visible(fn (Visa $record) => $record->status === VisaStatus::Applied),
                // No Delete on purpose — visa records are permanent.
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageVisas::route('/'),
        ];
    }
}