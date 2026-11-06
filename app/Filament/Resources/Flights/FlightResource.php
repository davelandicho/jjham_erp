<?php

namespace App\Filament\Resources\Flights;

use App\Enums\ApplicationStatus;
use App\Enums\FlightStatus;
use App\Filament\Resources\Flights\Pages\ManageFlights;
use App\Models\Application;
use App\Models\Flight;
use App\Enums\NavigationGroup;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DateTimePicker;
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

class FlightResource extends Resource
{
    protected static ?string $model = Flight::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedTicket;

    protected static string|UnitEnum|null $navigationGroup = NavigationGroup::Deployment;

    protected static ?int $navigationSort = 1;

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->columns(2)
            ->components([
                Select::make('application_id')
                    ->label('Application')
                    ->options(fn (?Flight $record) => Application::with(['applicant', 'jobOrder'])
                        ->where(fn ($q) => $q
                            ->whereIn('status', [
                                ApplicationStatus::ExitClearanceIssued,
                                ApplicationStatus::ReadyForDeployment,
                            ])
                            ->whereDoesntHave('flights', fn ($f) => $f->whereIn('status', [
                                FlightStatus::Booked,
                                FlightStatus::Departed,
                            ])))
                        ->when($record, fn ($q) => $q->orWhere('id', $record->application_id))
                        ->latest('id')
                        ->get()
                        ->mapWithKeys(fn (Application $a) => [
                            $a->id => "{$a->applicant->full_name} — {$a->jobOrder->position} ({$a->status->getLabel()})",
                        ]))
                    ->searchable()
                    ->required()
                    ->disabledOn('edit')
                    ->columnSpanFull(),

                TextInput::make('ticket_number')
                    ->label('Ticket number')
                    ->required()
                    ->maxLength(255),
                TextInput::make('airline')
                    ->required()
                    ->maxLength(255),
                TextInput::make('flight_number')
                    ->label('Flight number')
                    ->placeholder('e.g. EK 335')
                    ->required()
                    ->maxLength(255),
                TextInput::make('origin')
                    ->label('From (airport code)')
                    ->placeholder('MNL')
                    ->default('MNL')
                    ->required()
                    ->maxLength(10),
                TextInput::make('destination')
                    ->label('To (airport code)')
                    ->placeholder('DXB')
                    ->required()
                    ->maxLength(10),
                DateTimePicker::make('departs_at')
                    ->label('Scheduled departure (local time)')
                    ->seconds(false)
                    ->required(),
                DateTimePicker::make('arrives_at')
                    ->label('Scheduled arrival (local time)')
                    ->seconds(false)
                    ->after('departs_at')
                    ->required(),

                FileUpload::make('ticket_path')
                    ->label('E-ticket')
                    ->disk('local')
                    ->directory('tickets')
                    ->visibility('private')
                    ->acceptedFileTypes(['image/jpeg', 'image/png', 'application/pdf'])
                    ->maxSize(10240)
                    ->storeFileNamesIn('ticket_name')
                    ->required()
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
                TextColumn::make('status')
                    ->badge()
                    ->description(fn (Flight $record) => $record->cancelled_reason),
                TextColumn::make('flight_number')
                    ->label('Flight')
                    ->description(fn (Flight $record) => $record->airline)
                    ->searchable(),
                TextColumn::make('route')
                    ->state(fn (Flight $record) => "{$record->origin} → {$record->destination}"),
                TextColumn::make('departs_at')
                    ->label('Departs')
                    ->dateTime('M j, Y g:i A')
                    ->description(fn (Flight $record) => $record->departed_at
                        ? 'Left ' . $record->departed_at->format('M j, g:i A')
                        : null)
                    ->sortable(),
                TextColumn::make('arrives_at')
                    ->label('Arrives')
                    ->dateTime('M j, Y g:i A')
                    ->description(fn (Flight $record) => $record->arrived_at
                        ? 'Landed ' . $record->arrived_at->format('M j, g:i A')
                        : null),
                TextColumn::make('ticket_number')
                    ->label('Ticket no.')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('id', 'desc')
            ->filters([
                SelectFilter::make('status')
                    ->options(FlightStatus::class),
            ])
            ->recordActions([
                Action::make('ticketFile')
                    ->label('E-ticket')
                    ->icon(Heroicon::OutlinedEye)
                    ->color('gray')
                    ->url(fn (Flight $record) => Storage::disk('local')
                        ->temporaryUrl($record->ticket_path, now()->addMinutes(5)))
                    ->openUrlInNewTab(),

                Action::make('departed')
                    ->label('Mark departed')
                    ->icon(Heroicon::OutlinedPaperAirplane)
                    ->color('info')
                    ->visible(fn (Flight $record) => $record->status === FlightStatus::Booked)
                    ->schema([
                        DateTimePicker::make('departed_at')
                            ->label('Actual departure time (Manila)')
                            ->seconds(false)
                            ->default(fn (Flight $record) => $record->departs_at->isFuture() ? now() : $record->departs_at)
                            ->maxDate(now())
                            ->required(),
                    ])
                    ->action(fn (Flight $record, array $data, Action $action) => static::attempt(
                        $action, fn () => $record->markDeparted($data['departed_at'])
                    )),

                Action::make('arrived')
                    ->label('Confirm touchdown')
                    ->icon(Heroicon::OutlinedMapPin)
                    ->color('success')
                    ->visible(fn (Flight $record) => $record->status === FlightStatus::Departed)
                    ->schema([
                        DateTimePicker::make('arrived_at')
                            ->label('Actual touchdown time (local time at destination)')
                            ->seconds(false)
                            ->default(fn (Flight $record) => $record->arrives_at->isFuture() ? now() : $record->arrives_at)
                            ->maxDate(now())
                            ->required(),
                    ])
                    ->action(fn (Flight $record, array $data, Action $action) => static::attempt(
                        $action, fn () => $record->confirmArrival($data['arrived_at'])
                    )),

                Action::make('cancel')
                    ->label('Cancel / rebook')
                    ->icon(Heroicon::OutlinedXCircle)
                    ->color('danger')
                    ->visible(fn (Flight $record) => $record->status === FlightStatus::Booked)
                    ->modalDescription('After cancelling, record the new flight with "Record flight".')
                    ->schema([
                        Textarea::make('reason')
                            ->label('Why was the flight cancelled or rebooked?')
                            ->required(),
                    ])
                    ->action(fn (Flight $record, array $data, Action $action) => static::attempt(
                        $action, fn () => $record->cancel($data['reason'])
                    )),

                EditAction::make()
                    ->visible(fn (Flight $record) => $record->status === FlightStatus::Booked),
            ]);
    }
    protected static function attempt(Action $action, callable $callback): void
    {
        try {
            $callback();
        } catch (\DomainException $e) {
            Notification::make()
                ->title('Not allowed')
                ->body($e->getMessage())
                ->danger()
                ->send();

            $action->halt();
        }
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageFlights::route('/'),
        ];
    }
}