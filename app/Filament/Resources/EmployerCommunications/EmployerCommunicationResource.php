<?php

namespace App\Filament\Resources\EmployerCommunications;

use App\Enums\CommunicationChannel;
use App\Enums\EmployerCommunicationType;
use App\Filament\Resources\EmployerCommunications\Pages\ManageEmployerCommunications;
use App\Models\Application;
use App\Models\EmployerCommunication;
use BackedEnum;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Storage;

class EmployerCommunicationResource extends Resource
{
    protected static ?string $model = EmployerCommunication::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedChatBubbleLeftRight;

    protected static ?string $navigationLabel = 'Employer log';

    protected static ?string $modelLabel = 'employer log entry';

    protected static ?string $pluralModelLabel = 'employer log';

    public static function form(Schema $schema): Schema
    {
        // The form may hold the enum itself or its string value, so accept both.
        $needsEvidence = function (Get $get): bool {
            $type = $get('type');
            $type = $type instanceof EmployerCommunicationType
                ? $type
                : EmployerCommunicationType::tryFrom((string) $type);

            return $type?->requiresEvidence() ?? false;
        };

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
                    ->required(),

                Select::make('type')
                    ->label('What happened')
                    ->options(EmployerCommunicationType::class)
                    ->required()
                    ->live(),

                Select::make('channel')
                    ->options(CommunicationChannel::class)
                    ->default(CommunicationChannel::WhatsApp->value)
                    ->required(),

                DatePicker::make('communicated_on')
                    ->label('Date sent / received')
                    ->default(today())
                    ->maxDate(today())
                    ->required(),

                TextInput::make('contact_name')
                    ->label('Employer contact name')
                    ->maxLength(255),

                TextInput::make('contact_number')
                    ->label('Employer contact number')
                    ->tel()
                    ->maxLength(255),

                FileUpload::make('evidence_path')
                    ->label('Evidence (screenshot or file)')
                    ->disk('local')
                    ->directory('employer-evidence')
                    ->visibility('private')
                    ->acceptedFileTypes(['image/jpeg', 'image/png', 'application/pdf'])
                    ->maxSize(10240)
                    ->storeFileNamesIn('evidence_name')
                    ->required($needsEvidence)
                    ->columnSpanFull(),

                Textarea::make('notes')
                    ->helperText('Entries cannot be edited later. To correct a mistake, add a new entry and explain it here.')
                    ->columnSpanFull(),
            ]);
    }

public static function table(Table $table): Table
{
    return $table
        // One row per case, even though this resource saves log entries
        ->query(fn () => Application::query()->with(['applicant', 'jobOrder', 'employerCommunications']))
        ->columns([
            TextColumn::make('applicant.last_name')
                ->label('Last name')
                ->searchable()
                ->sortable(),
            TextColumn::make('applicant.first_name')
                ->label('First name')
                ->searchable(),
            TextColumn::make('jobOrder.position')
                ->label('Position')
                ->searchable(),
            TextColumn::make('status')
                ->badge(),
            TextColumn::make('employer_communications_count')
                ->label('Entries')
                ->counts('employerCommunications')
                ->sortable(),
            TextColumn::make('latest_entry')
                ->label('Latest entry')
                ->badge()
                ->state(fn (Application $record) => $record->employerCommunications->first()?->type)
                ->placeholder('None yet'),
            TextColumn::make('latest_entry_on')
                ->label('Date')
                ->date('M j, Y')
                ->state(fn (Application $record) => $record->employerCommunications->first()?->communicated_on)
                ->placeholder('—'),
        ])
        ->defaultSort('id', 'desc')
        ->filters([
            SelectFilter::make('type')
                ->label('Has entry')
                ->options(EmployerCommunicationType::class)
                ->query(fn (Builder $query, array $data) => $query->when(
                    filled($data['value']),
                    fn (Builder $query) => $query->whereHas(
                        'employerCommunications',
                        fn (Builder $entries) => $entries->where('type', $data['value']),
                    ),
                )),
        ])
        ->recordActions([
            ViewAction::make()
                ->slideOver()
                ->modalWidth(Width::FiveExtraLarge)
                ->modalHeading(fn (Application $record) => "{$record->applicant->full_name} — {$record->jobOrder->position}"),
            // No Edit or Delete on purpose — the employer log is permanent.
        ]);
}

public static function infolist(Schema $schema): Schema
{
    return $schema
        ->columns(1)
        ->components([
            RepeatableEntry::make('employerCommunications')
                ->hiddenLabel()
                ->placeholder('No employer log entries yet.')
                ->columns(4)
                ->schema([
                    TextEntry::make('type')
                        ->label('What happened')
                        ->badge(),
                    TextEntry::make('communicated_on')
                        ->label('Date')
                        ->date('M j, Y'),
                    TextEntry::make('channel'),
                    TextEntry::make('encoder.name')
                        ->label('Encoded by')
                        ->placeholder('System'),
                    TextEntry::make('contact_name')
                        ->label('Contact')
                        ->placeholder('—'),
                    TextEntry::make('contact_number')
                        ->label('Contact number')
                        ->placeholder('—'),
                    TextEntry::make('evidence_name')
                        ->label('Evidence')
                        ->url(fn (EmployerCommunication $record) => $record->evidence_path
                            ? Storage::disk('local')->temporaryUrl($record->evidence_path, now()->addMinutes(5))
                            : null)
                        ->openUrlInNewTab()
                        ->color('primary')
                        ->placeholder('None'),
                    TextEntry::make('notes')
                        ->placeholder('—'),
                ]),
        ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageEmployerCommunications::route('/'),
        ];
    }
}