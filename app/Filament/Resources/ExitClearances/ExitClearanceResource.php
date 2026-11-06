<?php

namespace App\Filament\Resources\ExitClearances;

use App\Enums\ExitClearanceStatus;
use App\Filament\Resources\ExitClearances\Pages\ManageExitClearances;
use App\Models\Application;
use App\Models\ExitClearance;
use App\Enums\NavigationGroup;
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
use UnitEnum;

class ExitClearanceResource extends Resource
{
    protected static ?string $model = ExitClearance::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedPaperAirplane;

    protected static string|UnitEnum|null $navigationGroup = NavigationGroup::Processing;

    protected static ?int $navigationSort = 4;

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

                DatePicker::make('submitted_on')
                    ->label('Date RFP submitted to DMW')
                    ->default(today())
                    ->maxDate(today())
                    ->required(),

                TextInput::make('rfp_reference')
                    ->label('RFP reference (if any)')
                    ->maxLength(255),
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
                    ->description(fn (ExitClearance $record) => $record->returned_reason),
                TextColumn::make('rfp_reference')
                    ->label('RFP ref.')
                    ->placeholder('—')
                    ->searchable(),
                TextColumn::make('submitted_on')
                    ->label('Submitted')
                    ->date('M j, Y')
                    ->sortable(),
                TextColumn::make('clearance_number')
                    ->label('Clearance no.')
                    ->placeholder('—')
                    ->searchable(),
                TextColumn::make('expires_on')
                    ->label('Expires')
                    ->date('M j, Y')
                    ->placeholder('—')
                    ->color(fn (ExitClearance $record) => $record->isExpired() ? 'danger' : null)
                    ->sortable(),
            ])
            ->defaultSort('id', 'desc')
            ->filters([
                SelectFilter::make('status')
                    ->options(ExitClearanceStatus::class),
            ])
            ->recordActions([
                Action::make('clearanceFile')
                    ->label('Clearance copy')
                    ->icon(Heroicon::OutlinedEye)
                    ->color('gray')
                    ->visible(fn (ExitClearance $record) => filled($record->clearance_path))
                    ->url(fn (ExitClearance $record) => Storage::disk('local')
                        ->temporaryUrl($record->clearance_path, now()->addMinutes(5)))
                    ->openUrlInNewTab(),

                Action::make('issue')
                    ->label('Issue')
                    ->icon(Heroicon::OutlinedCheckBadge)
                    ->color('success')
                    ->visible(fn (ExitClearance $record) => $record->status === ExitClearanceStatus::Submitted)
                    ->schema([
                        TextInput::make('clearance_number')
                            ->label('Clearance (OEC) number')
                            ->required()
                            ->maxLength(255),
                        DatePicker::make('issued_on')
                            ->label('Date issued')
                            ->default(today())
                            ->maxDate(today())
                            ->required(),
                        DatePicker::make('expires_on')
                            ->label('Expiry date')
                            ->after('issued_on')
                            ->required(),
                        FileUpload::make('clearance_path')
                            ->label('Clearance copy')
                            ->disk('local')
                            ->directory('clearances')
                            ->visibility('private')
                            ->acceptedFileTypes(['image/jpeg', 'image/png', 'application/pdf'])
                            ->maxSize(10240)
                            ->storeFileNamesIn('clearance_name')
                            ->required(),
                    ])
                    ->action(fn (ExitClearance $record, array $data) => $record->issue(
                        $data['clearance_number'],
                        $data['issued_on'],
                        $data['expires_on'],
                        $data['clearance_path'],
                        $data['clearance_name'] ?? basename($data['clearance_path']),
                    )),

                Action::make('returned')
                    ->label('Returned')
                    ->icon(Heroicon::OutlinedArrowUturnLeft)
                    ->color('danger')
                    ->visible(fn (ExitClearance $record) => $record->status === ExitClearanceStatus::Submitted)
                    ->modalDescription('After fixing the deficiency, record a new RFP.')
                    ->schema([
                        Textarea::make('reason')
                            ->label('Why did the DMW return it?')
                            ->required(),
                    ])
                    ->action(fn (ExitClearance $record, array $data) => $record->markReturned($data['reason'])),

                EditAction::make()
                    ->visible(fn (ExitClearance $record) => $record->status === ExitClearanceStatus::Submitted),
                // No Delete on purpose — exit clearance records are permanent.
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageExitClearances::route('/'),
        ];
    }
}
