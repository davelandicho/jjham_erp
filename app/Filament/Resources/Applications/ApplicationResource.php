<?php

namespace App\Filament\Resources\Applications;

use App\Filament\Resources\Applications\Pages\ManageApplications;
use App\Models\Application;
use BackedEnum;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
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


class ApplicationResource extends Resource
{
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
                    ->required(),
                Select::make('status')
                    ->options(function (?Application $record) {
                        $current = $record?->status ?? ApplicationStatus::Registered;

                        return collect(ApplicationStatus::cases())
                            ->filter(fn (ApplicationStatus $case) => $current->canMoveTo($case))
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
                DeleteAction::make(),
            ])
            ->recordAction(ViewAction::class)
            ->toolbarActions([
                    DeleteBulkAction::make(),
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
