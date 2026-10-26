<?php

namespace App\Filament\Resources\EmployerCommunications;

use App\Enums\CommunicationChannel;
use App\Enums\EmployerCommunicationType;
use App\Filament\Resources\EmployerCommunications\Pages\ManageEmployerCommunications;
use App\Models\Application;
use App\Models\EmployerCommunication;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
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
                TextColumn::make('type')
                    ->badge(),
                TextColumn::make('channel')
                    ->toggleable(),
                TextColumn::make('communicated_on')
                    ->label('Date')
                    ->date('M j, Y')
                    ->sortable(),
                TextColumn::make('contact_name')
                    ->label('Contact')
                    ->description(fn (EmployerCommunication $record) => $record->contact_number)
                    ->placeholder('—')
                    ->toggleable(),
                TextColumn::make('notes')
                    ->limit(40)
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('encoder.name')
                    ->label('Encoded by')
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('id', 'desc')
            ->filters([
                SelectFilter::make('type')
                    ->options(EmployerCommunicationType::class),
            ])
            ->recordActions([
                Action::make('evidence')
                    ->icon(Heroicon::OutlinedEye)
                    ->color('gray')
                    ->visible(fn (EmployerCommunication $record) => filled($record->evidence_path))
                    ->url(fn (EmployerCommunication $record) => Storage::disk('local')
                        ->temporaryUrl($record->evidence_path, now()->addMinutes(5)))
                    ->openUrlInNewTab(),
                // No Edit or Delete on purpose — the employer log is permanent.
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageEmployerCommunications::route('/'),
        ];
    }
}