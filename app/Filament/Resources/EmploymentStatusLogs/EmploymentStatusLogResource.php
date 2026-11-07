<?php

namespace App\Filament\Resources\EmploymentStatusLogs;

use App\Enums\ApplicationStatus;
use App\Enums\EmploymentStatus;
use App\Enums\ReportSource;
use App\Filament\Resources\EmploymentStatusLogs\Pages\ManageEmploymentStatusLogs;
use App\Models\Application;
use App\Models\EmploymentStatusLog;
use App\Enums\NavigationGroup;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Storage;
use UnitEnum;

class EmploymentStatusLogResource extends Resource
{
    protected static ?string $model = EmploymentStatusLog::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBriefcase;

    protected static string|UnitEnum|null $navigationGroup = NavigationGroup::Deployment;

    protected static ?int $navigationSort = 2;

    protected static ?string $navigationLabel = 'Employment Status';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->columns(2)
            ->components([
                Select::make('application_id')
                    ->label('Worker')
                    ->options(fn () => Application::with(['applicant', 'jobOrder'])
                        ->where('status', ApplicationStatus::Arrived)
                        ->latest('id')
                        ->get()
                        ->mapWithKeys(fn (Application $a) => [
                            $a->id => "{$a->applicant->full_name} — {$a->jobOrder->position} ("
                                . ($a->employmentLogs()->first()?->status->getLabel() ?? 'no status yet') . ')',
                        ]))
                    ->searchable()
                    ->required()
                    ->columnSpanFull(),

                ...static::entryFields(),
            ]);
    }

    public static function entryFields(): array
    {
        return [
            Select::make('status')
                ->label('Employment status')
                ->options(EmploymentStatus::class)
                ->required(),
            DatePicker::make('effective_on')
                ->label('Effective date')
                ->default(today())
                ->maxDate(today())
                ->required(),
            Select::make('source')
                ->label('Reported by')
                ->options(ReportSource::class)
                ->required(),
            TextInput::make('notified')
                ->label('Who was notified')
                ->placeholder('e.g. Management; family by phone')
                ->maxLength(255),
            Textarea::make('remarks')
                ->label('Remarks')
                ->helperText('Internal only — never shown to the worker.')
                ->columnSpanFull(),
            FileUpload::make('evidence_path')
                ->label('Supporting evidence (optional)')
                ->disk('local')
                ->directory('employment-evidence')
                ->visibility('private')
                ->acceptedFileTypes(['image/jpeg', 'image/png', 'application/pdf'])
                ->maxSize(10240)
                ->storeFileNamesIn('evidence_name')
                ->columnSpanFull(),
        ];
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->with(['application.applicant', 'correctedBy', 'encoder']))
            ->columns([
                TextColumn::make('application.applicant.last_name')
                    ->label('Last name')
                    ->searchable(),
                TextColumn::make('application.applicant.first_name')
                    ->label('First name')
                    ->searchable(),
                TextColumn::make('status')
                    ->badge()
                    ->description(fn (EmploymentStatusLog $record) => match (true) {
                        $record->correctedBy !== null => 'Corrected later — see newer entry',
                        $record->corrects_id !== null => 'Correction of an earlier entry',
                        default => null,
                    }),
                TextColumn::make('effective_on')
                    ->label('Effective')
                    ->date('M j, Y')
                    ->sortable(),
                TextColumn::make('source')
                    ->label('Reported by')
                    ->badge()
                    ->color('gray'),
                TextColumn::make('notified')
                    ->placeholder('—')
                    ->toggleable(),
                TextColumn::make('encoder.name')
                    ->label('Encoded by')
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('created_at')
                    ->label('Logged')
                    ->dateTime('M j, Y g:i A')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('id', 'desc')
            ->filters([
                SelectFilter::make('status')
                    ->options(EmploymentStatus::class),
            ])
            ->recordActions([
                Action::make('evidence')
                    ->label('Evidence')
                    ->icon(Heroicon::OutlinedEye)
                    ->color('gray')
                    ->visible(fn (EmploymentStatusLog $record) => filled($record->evidence_path))
                    ->url(fn (EmploymentStatusLog $record) => Storage::disk('local')
                        ->temporaryUrl($record->evidence_path, now()->addMinutes(5)))
                    ->openUrlInNewTab(),

                Action::make('correct')
                    ->authorize('update')
                    ->label('Correct')
                    ->icon(Heroicon::OutlinedPencilSquare)
                    ->color('warning')
                    ->visible(fn (EmploymentStatusLog $record) => $record->application->employmentLogs()->first()?->id === $record->id)
                    ->modalHeading('Correct this entry')
                    ->modalDescription('The wrong entry stays in the log. Explain in the remarks what was wrong.')
                    ->schema(static::entryFields())
                    ->fillForm(fn (EmploymentStatusLog $record) => [
                        'effective_on' => $record->effective_on,
                        'source' => $record->source,
                    ])
                    ->action(fn (EmploymentStatusLog $record, array $data, Action $action) => static::attempt(
                        $action, fn () => EmploymentStatusLog::create([
                            ...$data,
                            'application_id' => $record->application_id,
                            'corrects_id' => $record->id,
                        ])
                    )),
            ]);
    }

    // Same helper as in FlightResource: a broken rule becomes a red notification.
    public static function attempt(Action $action, callable $callback): mixed
    {
        try {
            return $callback();
        } catch (\DomainException $e) {
            Notification::make()
                ->title('Not allowed')
                ->body($e->getMessage())
                ->danger()
                ->send();

            $action->halt();
        }

        return null;
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageEmploymentStatusLogs::route('/'),
        ];
    }
}