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
