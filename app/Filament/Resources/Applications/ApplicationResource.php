<?php

namespace App\Filament\Resources\Applications;

use App\Filament\Resources\Applications\Pages\ManageApplications;
use App\Models\Application;
use BackedEnum;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use App\Enums\ApplicationStatus;
use App\Models\Applicant;
use App\Models\JobOrder;
use Filament\Forms\Components\Select;
use Filament\Tables\Filters\SelectFilter;
use Filament\Actions\ViewAction;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Support\Enums\Width;
use Filament\Infolists\Components\RepeatableEntry;
use Closure;
use App\Enums\ClosureReason;
use App\Filament\Concerns\CatchesDomainErrors;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Toggle;
use Illuminate\Support\Facades\Storage;
use Filament\Schemas\Components\Utilities\Get;
use Illuminate\Validation\Rules\Unique;

class ApplicationResource extends Resource
{
    use CatchesDomainErrors;

    private const VIA_ACTIONS = [
        ApplicationStatus::OnHold,
        ApplicationStatus::Closed,
        ApplicationStatus::Withdrawn,
    ];

    protected static ?string $model = Application::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('applicant_id')
                    ->label('Applicant')
                    ->relationship('applicant', 'last_name')
                    ->getOptionLabelFromRecordUsing(fn (Applicant $record) => $record->full_name)
                    ->searchable(['first_name', 'last_name', 'mobile'])
                    ->preload()
                    ->required(),
                Select::make('job_order_id')
                    ->label('Job order')
                    ->relationship('jobOrder', 'position')
                    ->getOptionLabelFromRecordUsing(fn (JobOrder $record) => "{$record->position} - {$record->employer->name}")
                    ->searchable()
                    ->preload()
                    ->required()
                    ->unique(
                        ignoreRecord: true,
                        modifyRuleUsing: fn (Unique $rule, Get $get) => $rule->where('applicant_id', $get('applicant_id')),
                    )
                    ->validationMessages([
                        'unique' => 'This applicant already has an application for this job order.',
                    ]),
                Select::make('status')
                    ->options(function (?Application $record) {
                        $current = $record?->status ?? ApplicationStatus::Registered;

                        return collect(ApplicationStatus::cases())
                            ->filter(fn (ApplicationStatus $case) => $case === $current || ($current->canMoveTo($case) && ! in_array($case, self::VIA_ACTIONS, true)))
                            ->mapWithKeys(fn (ApplicationStatus $case) => [$case->value => $case->getLabel()])
                            ->all();
                    })
                    ->default(ApplicationStatus::Registered->value)
                    ->required()
                    ->rules([
                        // Shows the model's business rules (medical, employer…) as a form error instead of a crash page.
                            fn (?Application $record): Closure => function (string $attribute, $value, Closure $fail) use ($record) {
                            if (! $record || $record->status->value === $value) {
                                return;
                            }

                            try {
                                $record->ensureRequirementsMet(ApplicationStatus::from($value));                            
                                } catch (\DomainException $e) {
                                $fail($e->getMessage());
                            }
                        },
                    ]),
                TextInput::make('fee')
                    ->label('Fee')
                    ->numeric()
                    ->prefix('₱')
                    ->minValue(0)
                    ->default(0)
                    ->required()
                    ->helperText('Leave at 0 if this job order charges no fee'),
                Textarea::make('remarks')
                    ->columnSpanFull(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
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
                TextColumn::make('jobOrder.employer.name')
                    ->label('Employer'),
                TextColumn::make('status')
                    ->badge()
                    ->sortable(),
                TextColumn::make('status_changed_at')
                    ->since()->label('In status')
                    ->toggleable(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options (
                        collect(ApplicationStatus::cases())
                        ->mapWithKeys(fn (ApplicationStatus $case) => [$case->value => $case->getLabel()])
                        ->all()
                    ),
            ])
            ->recordActions([
                ViewAction::make()
                    ->slideOver()
                    ->modalWidth(Width::FiveExtraLarge),
                EditAction::make(),

                Action::make('putOnHold')
                    ->label('Put on hold')
                    ->icon(Heroicon::OutlinedPauseCircle)
                    ->color('warning')
                    ->visible(fn (Application $record) => $record->status !== ApplicationStatus::OnHold
                        && $record->status->canMoveTo(ApplicationStatus::OnHold))
                    ->schema([
                        Textarea::make('reason')
                            ->label('Why is this case paused?')
                            ->required(),
                    ])
                    ->action(fn (Application $record, array $data, Action $action) => static::attempt(
                        $action, fn () => $record->putOnHold($data['reason'])
                    )),

                Action::make('closeCase')
                    ->label('Close case')
                    ->icon(Heroicon::OutlinedLockClosed)
                    ->color('danger')
                    ->visible(fn (Application $record) => $record->status->canMoveTo(ApplicationStatus::Closed)
                        && ! in_array($record->status, [ApplicationStatus::Closed, ApplicationStatus::Withdrawn], true))
                    ->modalHeading('Close this case')
                    ->modalDescription('This is permanent. A closed or withdrawn case cannot be reopened.')
                    ->modalSubmitActionLabel('Close case')
                    ->schema([
                        Select::make('reason')
                            ->options(ClosureReason::class)
                            ->required(),
                        DatePicker::make('closed_on')
                            ->label('Closure date')
                            ->default(today())
                            ->maxDate(today())
                            ->required(),
                        Textarea::make('details')
                            ->label('Details')
                            ->required(),
                        Toggle::make('refund_due')
                            ->label('Refund due')
                            // Pre-ticked when the applicant has paid anything
                            ->default(fn (Application $record) => $record->amountPaid() > 0)
                            ->helperText(fn (Application $record) => 'Paid so far: ₱' . number_format($record->amountPaid(), 2)),
                        FileUpload::make('evidence_path')
                            ->label('Supporting evidence (optional)')
                            ->disk('local')
                            ->directory('closures')
                            ->visibility('private')
                            ->acceptedFileTypes(['image/jpeg', 'image/png', 'application/pdf'])
                            ->maxSize(10240)
                            ->storeFileNamesIn('evidence_name'),
                    ])
                    ->action(fn (Application $record, array $data, Action $action) => static::attempt(
                        $action, fn () => $record->close(
                            $data['reason'] instanceof ClosureReason ? $data['reason'] : ClosureReason::from($data['reason']),
                            $data['closed_on'],
                            $data['details'],
                            (bool) ($data['refund_due'] ?? false),
                            $data['evidence_path'] ?? null,
                            $data['evidence_name'] ?? null,
                        )
                    )),

            ]);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                Section::make('Case')
                    ->columns(4)
                    ->columnSpan(1)
                    ->schema([
                        TextEntry::make('status')->badge(),
                        TextEntry::make('status_changed_at')->label('Status since')->since(),
                        TextEntry::make('created_at')->label('Applied')->dateTime('M j, Y'),
                        TextEntry::make('remarks')->placeholder('—'),
                    ]),
                Section::make('Closure')
                    ->columns(4)
                    ->columnSpan(1)
                    ->visible(fn (Application $record) => $record->closure()->exists())
                    ->schema([
                        TextEntry::make('closure.reason')
                            ->label('Reason')
                            ->badge()
                            ->color('danger'),
                        TextEntry::make('closure.closed_on')
                            ->label('Closed on')
                            ->date('M j, Y'),
                        TextEntry::make('closure.refund_due')
                            ->label('Refund due')
                            ->badge()
                            ->formatStateUsing(fn ($state) => $state ? 'Yes — Finance to check' : 'No')
                            ->color(fn ($state) => $state ? 'danger' : 'gray'),
                        TextEntry::make('closure.encoder.name')
                            ->label('Closed by')
                            ->placeholder('System'),
                        TextEntry::make('closure.details')
                            ->label('Details')
                            ->columnSpanFull(),
                        TextEntry::make('closure.evidence_name')
                            ->label('Evidence')
                            ->url(fn (Application $record) => $record->closure?->evidence_path
                                ? Storage::disk('local')->temporaryUrl($record->closure->evidence_path, now()->addMinutes(5))
                                : null)
                            ->openUrlInNewTab()
                            ->color('primary')
                            ->placeholder('None'),
                    ]),
                Section::make('Applicant')
                    ->columns(5)
                    ->columnSpan(1)
                    ->schema([
                        TextEntry::make('applicant.last_name')->label('Last name'),
                        TextEntry::make('applicant.first_name')->label('First name'),
                        TextEntry::make('applicant.mobile')->label('Mobile')->placeholder('—'),
                        TextEntry::make('applicant.passport_expiry_date')->label('Passport expiry')->date()->placeholder('—'),
                        TextEntry::make('applicant.email')->label('Email address')->placeholder('—')->columnSpanFull(),
                    ]),

                Section::make('Job order')
                    ->columns(4)
                    ->columnSpanFull()
                    ->schema([
                        TextEntry::make('jobOrder.position')->label('Position'),
                        TextEntry::make('jobOrder.employer.name')->label('Employer'),
                        TextEntry::make('jobOrder.salary')->label('Salary')->money('AED')->placeholder('—'),
                        TextEntry::make('jobOrder.openings')->label('Openings'),
                    ]),
                Section::make('Payments')
                    ->columns(3)
                    ->columnSpanFull()
                    ->schema([
                        TextEntry::make('fee')
                            ->label('Fee')
                            ->money('PHP'),
                        TextEntry::make('amount_paid')
                            ->label('Paid')
                            ->state(fn (Application $record) => $record->amountPaid())
                            ->money('PHP'),
                        TextEntry::make('balance')
                            ->label('Balance')
                            ->state(fn (Application $record) => $record->balance())
                            ->money('PHP')
                            ->color(fn (Application $record) => $record->isFullyPaid() ? 'success' : 'danger'),
                    ]),
                Section::make('Visa')
                    ->columns(4)
                    ->columnSpanFull()
                    ->schema([
                        TextEntry::make('latest_visa_status')
                            ->label('Status')
                            ->state(fn (Application $record) => $record->visas()->first()?->status)
                            ->badge()
                            ->placeholder('Not applied'),
                        TextEntry::make('latest_visa_number')
                            ->label('Released')
                            ->state(fn (Application $record) => $record->visas()->first()?->visa_number)
                            ->placeholder('-'),
                        TextEntry::make('latest_visa_released')
                            ->label('Released')
                            ->state(fn (Application $record) => $record->visas()->first()?->released_on)
                            ->date('M j, Y')
                            ->placeholder('-'),
                        TextEntry::make('latest_visa_expires')
                            ->label('Expires')
                            ->state(fn (Application $record) => $record->visas()->first()?->expires_on)
                            ->date('M j, Y')
                            ->placeholder('-')
                            ->color(fn (Application $record) => $record->visas()->first()?->isExpired() ? 'danger' : null)
                            ->placeholder('-'),
                    ]),
                    Section::make('Refunds')
                        ->columnSpan(1)
                        ->visible(fn (Application $record) => $record->refunds()->exists())
                        ->schema([
                            RepeatableEntry::make('refunds')
                                ->hiddenLabel()
                                ->columns(5)
                                ->schema([
                                    TextEntry::make('status')
                                        ->badge(),
                                    TextEntry::make('amount')
                                        ->money('PHP'),
                                    TextEntry::make('requester.name')
                                        ->label('Requested by')
                                        ->placeholder('—'),
                                    TextEntry::make('decider.name')
                                        ->label('Decided by')
                                        ->placeholder('—'),
                                    TextEntry::make('refunded_on')
                                        ->label('Refunded')
                                        ->date('M j, Y')
                                        ->placeholder('—'),
                                    TextEntry::make('reason')
                                        ->columnSpanFull(),
                                    TextEntry::make('decision_remarks')
                                        ->label('Decision remarks')
                                        ->placeholder('—')
                                        ->columnSpanFull(),
                                ]),
                        ]),
                Section::make('Exit clearance')
                    ->columns(4)
                    ->columnSpanFull()
                    ->schema([
                        TextEntry::make('latest_clearance_status')
                            ->label('Status')
                            ->state(fn (Application $record) => $record->exitClearances()->first()?->status)
                            ->badge()
                            ->placeholder('No RFP yet'),
                        TextEntry::make('latest_clearance_number')
                            ->label('Clearance number')
                            ->state(fn (Application $record) => $record->exitClearances()->first()?->clearance_number)
                            ->placeholder('—'),
                        TextEntry::make('latest_clearance_issued')
                            ->label('Issued')
                            ->state(fn (Application $record) => $record->exitClearances()->first()?->issued_on)
                            ->date('M j, Y')
                            ->placeholder('—'),
                        TextEntry::make('latest_clearance_expires')
                            ->label('Expires')
                            ->state(fn (Application $record) => $record->exitClearances()->first()?->expires_on)
                            ->date('M j, Y')
                            ->color(fn (Application $record) => $record->exitClearances()->first()?->isExpired() ? 'danger' : null)
                            ->placeholder('—'),
                    ]),
                Section::make('Document checklist')
                    ->columnSpanFull()
                    ->description(fn (Application $record) => $record->checklistSummary())
                    ->schema([
                        RepeatableEntry::make('checklist')
                            ->hiddenLabel()
                            ->state(fn (Application $record) => $record->documentChecklist())
                            ->placeholder('This job order has no required documents yet.')
                            ->columns(5)
                            ->schema([
                                TextEntry::make('name')
                                    ->label('Document'),
                                TextEntry::make('status')
                                    ->badge()
                                    ->placeholder('Not uploaded'),
                                TextEntry::make('version')
                                    ->formatStateUsing(fn (int $state) => "v{$state}")
                                    ->placeholder('—'),
                                TextEntry::make('expiry_date')
                                    ->label('Expires')
                                    ->date('M j, Y')
                                    ->placeholder('—'),
                                TextEntry::make('note')
                                    ->label('Rejection reason')
                                    ->placeholder('—'),
                            ]),
                    ]),
                Section::make('Flight')
                    ->columns(4)
                    ->columnSpanFull()
                    ->schema([
                        TextEntry::make('latest_flight_status')
                            ->label('Status')
                            ->state(fn (Application $record) => $record->flights()->first()?->status)
                            ->badge()
                            ->placeholder('No flight yet'),
                        TextEntry::make('latest_flight_number')
                            ->label('Flight')
                            ->state(fn (Application $record) => ($f = $record->flights()->first())
                                ? "{$f->flight_number} ({$f->airline}) · {$f->origin} → {$f->destination}"
                                : null)
                            ->placeholder('—'),
                        TextEntry::make('latest_flight_departure')
                            ->label(fn (Application $record) => $record->flights()->first()?->departed_at
                                ? 'Departed' : 'Scheduled departure')
                            ->state(fn (Application $record) => ($f = $record->flights()->first())
                                ? ($f->departed_at ?? $f->departs_at)
                                : null)
                            ->dateTime('M j, Y g:i A')
                            ->placeholder('—'),
                        TextEntry::make('latest_flight_arrival')
                            ->label(fn (Application $record) => $record->flights()->first()?->arrived_at
                                ? 'Touchdown' : 'Scheduled arrival')
                            ->state(fn (Application $record) => ($f = $record->flights()->first())
                                ? ($f->arrived_at ?? $f->arrives_at)
                                : null)
                            ->dateTime('M j, Y g:i A')
                            ->placeholder('—'),
                    ]),
                Section::make('Employment')
                    ->columnSpanFull()
                    ->description(fn (Application $record) => ($current = $record->employmentLogs()->first())
                        ? "Current: {$current->status->getLabel()} since {$current->effective_on->format('M j, Y')}"
                        : 'No employment status logged yet.')
                    ->visible(fn (Application $record) => $record->status === ApplicationStatus::Arrived
                        || $record->employmentLogs()->exists())
                    ->schema([
                        RepeatableEntry::make('employmentLogs')
                            ->hiddenLabel()
                            ->placeholder('No entries yet.')
                            ->columns(5)
                            ->schema([
                                TextEntry::make('status')
                                    ->badge(),
                                TextEntry::make('effective_on')
                                    ->label('Effective')
                                    ->date('M j, Y'),
                                TextEntry::make('source')
                                    ->label('Reported by'),
                                TextEntry::make('note')
                                    ->label('Note')
                                    ->state(fn ($record) => match (true) {
                                        $record->correctedBy()->exists() => 'Corrected later',
                                        $record->corrects_id !== null => 'Correction',
                                        default => null,
                                    })
                                    ->color('warning')
                                    ->placeholder('—'),
                                TextEntry::make('encoder.name')
                                    ->label('Encoded by')
                                    ->placeholder('System'),
                                TextEntry::make('remarks')
                                    ->label('Remarks (internal)')
                                    ->placeholder('—')
                                    ->columnSpanFull(),
                            ]),
                    ]),                
                Section::make('Status history')
                    ->columnSpanFull()
                    ->schema([
                        RepeatableEntry::make('histories')
                            ->hiddenLabel()
                            ->placeholder('No status changes recorded yet.')
                            ->columns(4)
                            ->schema([
                                TextEntry::make('from_status')
                                    ->label('From')
                                    ->badge()
                                    ->placeholder('Created'),
                                TextEntry::make('to_status')
                                    ->label('To')
                                    ->badge(),
                                TextEntry::make('user.name')
                                    ->label('Changed by')
                                    ->placeholder('System'),
                                TextEntry::make('created_at')
                                    ->label('When')
                                    ->dateTime('M j, Y g:i A'),
                                TextEntry::make('remarks')
                                    ->hiddenLabel()
                                    ->columnSpanFull()
                                    ->color('gray')
                                    ->hidden(fn ($record) => blank($record->remarks)),
                            ]),
                    ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageApplications::route('/'),
        ];
    }
}
