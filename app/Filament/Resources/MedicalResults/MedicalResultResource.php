<?php

namespace App\Filament\Resources\MedicalResults;

use App\Enums\MedicalResultStatus;
use App\Enums\MedicalSource;
use App\Filament\Resources\MedicalResults\Pages\ManageMedicalResults;
use App\Models\Application;
use App\Models\MedicalResult;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Storage;
use App\Models\Clinic;

class MedicalResultResource extends Resource
{
    protected static ?string $model = MedicalResult::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBeaker;

    public static function form(Schema $schema): Schema
    {
        // The form may hold the enum itself or its string value, so accept both.
        $isFit = fn (Get $get): bool => in_array(
            $get('result'),
            [MedicalResultStatus::Fit, MedicalResultStatus::Fit->value],
            true,
        );

        return $schema
            ->components([
                Select::make('application_id')
                    ->label('Application')
                    ->options(fn () => Application::with(['applicant', 'jobOrder'])
                        ->latest('id')
                        ->get()
                        ->mapWithKeys(fn (Application $a) => [
                            $a->id => "{$a->applicant->full_name} — {$a->jobOrder->position}",
                        ]))
                    ->searchable()
                    ->required()
                    ->disabledOn('edit'), // a result never moves to another application

                Select::make('clinic_id')
                    ->label('Clinic')
                    ->options(fn (?MedicalResult $record) => Clinic::active()
                        ->orWhere('id', $record?->clinic_id)
                        ->orderBy('name')
                        ->pluck('name', 'id'))
                    ->placeholder('Other Clinic')
                    ->helperText('Leave empty for an applicant who used another clinic.')
                    ->live(),

                    TextInput::make('clinic')
                        ->label('Other clinic name')
                        ->visible(fn (Get $get): bool => blank($get('clinic_id')))
                        ->required(fn (Get $get): bool => blank($get('clinic_id')))
                        ->maxLength(255),

                DatePicker::make('exam_date')
                    ->required()
                    ->maxDate(today()),

                Select::make('result')
                    ->options(MedicalResultStatus::class)
                    ->default(MedicalResultStatus::Pending->value)
                    ->required()
                    ->live(),

                DatePicker::make('valid_until')
                    ->label('Valid until')
                    ->required($isFit)
                    ->after('exam_date'),

                Select::make('source')
                    ->options(MedicalSource::class)
                    ->helperText('Verified automatically only for a preferred clinic. Other clinics or applicant-brought results need Verify.')
                    ->required(),

                FileUpload::make('certificate_path')
                    ->label('Medical certificate')
                    ->disk('local')
                    ->directory('medical')
                    ->visibility('private')
                    ->acceptedFileTypes(['image/jpeg', 'image/png', 'application/pdf'])
                    ->maxSize(10240)
                    ->storeFileNamesIn('certificate_name')
                    ->columnSpanFull(),

                Textarea::make('remarks')
                    ->helperText('Encrypted. Staff only — never shown to the applicant.')
                    ->columnSpanFull(),
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
                TextColumn::make('application.jobOrder.position')
                    ->label('Position')
                    ->toggleable(),
                TextColumn::make('clinic')
                    ->searchable()
                    ->description(fn (MedicalResult $record) => $record->clinic_id ? null : 'Other Clinic')
                    ->toggleable(),
                TextColumn::make('exam_date')
                    ->date('M j, Y')
                    ->sortable(),
                TextColumn::make('result')
                    ->badge()
                    ->toggleable(),
                TextColumn::make('source')
                    ->toggleable(),
                IconColumn::make('is_verified')
                    ->label('Verified')
                    ->state(fn (MedicalResult $record) => $record->isVerified())
                    ->boolean(),
                TextColumn::make('valid_until')
                    ->label('Valid until')
                    ->date('M j, Y')
                    ->placeholder('—')
                    ->color(fn (MedicalResult $record) => $record->expiresSoon() ? 'danger' : null)
                    ->description(fn (MedicalResult $record) => $record->expiresSoon()
                        ? 'Expires within ' . MedicalResult::EXPIRY_WARNING_DAYS . ' days'
                        : null)
                    ->sortable(),
                TextColumn::make('verifier.name')
                    ->label('Verified by')
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('id', 'desc')
            ->filters([
                SelectFilter::make('result')
                    ->options(MedicalResultStatus::class),
            ])
            ->recordActions([
                Action::make('certificate')
                    ->icon(Heroicon::OutlinedEye)
                    ->color('gray')
                    ->visible(fn (MedicalResult $record) => filled($record->certificate_path))
                    ->url(fn (MedicalResult $record) => Storage::disk('local')
                        ->temporaryUrl($record->certificate_path, now()->addMinutes(5)))
                    ->openUrlInNewTab(),

                Action::make('verify')
                    ->icon(Heroicon::OutlinedCheckBadge)
                    ->color('success')
                    ->requiresConfirmation()
                    ->modalDescription('Confirm you checked this result directly with the clinic.')
                    ->visible(fn (MedicalResult $record) => ! $record->isVerified()
                        && $record->result !== MedicalResultStatus::Pending)
                    ->action(fn (MedicalResult $record) => $record->verify()),

                EditAction::make(),
                // No Delete on purpose — medical results are permanent.
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageMedicalResults::route('/'),
        ];
    }
}