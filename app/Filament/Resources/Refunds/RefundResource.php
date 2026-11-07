<?php

namespace App\Filament\Resources\Refunds;

use App\Enums\PaymentMethod;
use App\Enums\RefundStatus;
use App\Filament\Concerns\CatchesDomainErrors;
use App\Filament\Resources\Refunds\Pages\ManageRefunds;
use App\Models\Application;
use App\Models\Refund;
use App\Enums\NavigationGroup;
use BackedEnum;
use Filament\Actions\Action;
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
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Storage;
use UnitEnum;

class RefundResource extends Resource
{
    use CatchesDomainErrors;

    protected static ?string $model = Refund::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedReceiptRefund;

    protected static string|UnitEnum|null $navigationGroup = NavigationGroup::Finance;

    protected static ?int $navigationSort = 2;

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('application_id')
                    ->label('Closed case')
                    ->options(fn () => Application::with(['applicant', 'jobOrder'])
                        ->whereHas('closure', fn (Builder $closure) => $closure->where('refund_due', true))
                        ->whereDoesntHave('refunds', fn (Builder $refunds) => $refunds
                            ->whereIn('status', [RefundStatus::Requested, RefundStatus::Approved]))
                        ->latest('id')
                        ->get()
                        ->filter(fn (Application $a) => $a->refundableAmount() > 0)
                        ->mapWithKeys(fn (Application $a) => [
                            $a->id => "{$a->applicant->full_name} — {$a->jobOrder->position} (refundable ₱"
                                . number_format($a->refundableAmount(), 2) . ')',
                        ]))
                    ->searchable()
                    ->required()
                    ->helperText('Only closed cases marked "refund due", with money left to refund and no open refund, are listed.')
                    ->columnSpanFull(),

                TextInput::make('amount')
                    ->numeric()
                    ->prefix('₱')
                    ->minValue(0.01)
                    ->required(),

                Textarea::make('reason')
                    ->label('Reason for the refund')
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
                TextColumn::make('application.jobOrder.position')
                    ->label('Position')
                    ->toggleable(),
                TextColumn::make('amount')
                    ->money('PHP')
                    ->sortable(),
                TextColumn::make('status')
                    ->badge()
                    ->description(fn (Refund $record) => $record->decision_remarks),
                TextColumn::make('reason')
                    ->limit(40)
                    ->tooltip(fn (Refund $record) => $record->reason)
                    ->toggleable(),
                TextColumn::make('requester.name')
                    ->label('Requested by')
                    ->placeholder('—')
                    ->toggleable(),
                TextColumn::make('decider.name')
                    ->label('Decided by')
                    ->description(fn (Refund $record) => $record->decided_at?->format('M j, Y'))
                    ->placeholder('—')
                    ->toggleable(),
                TextColumn::make('refunded_on')
                    ->label('Refunded')
                    ->date('M j, Y')
                    ->placeholder('—')
                    ->sortable(),
                TextColumn::make('method')
                    ->badge()
                    ->placeholder('—')
                    ->toggleable(),
                TextColumn::make('reference_number')
                    ->label('Ref.')
                    ->placeholder('—')
                    ->searchable(),
                TextColumn::make('payer.name')
                    ->label('Paid by')
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('id', 'desc')
            ->filters([
                SelectFilter::make('status')
                    ->options(RefundStatus::class),
            ])
            ->recordActions([
                Action::make('proofFile')
                    ->label('Proof')
                    ->icon(Heroicon::OutlinedEye)
                    ->color('gray')
                    ->visible(fn (Refund $record) => filled($record->proof_path))
                    ->url(fn (Refund $record) => Storage::disk('local')
                        ->temporaryUrl($record->proof_path, now()->addMinutes(5)))
                    ->openUrlInNewTab(),

                Action::make('approve')
                    ->authorize(fn (): bool => auth()->user()->can('Approve:Refund'))
                    ->icon(Heroicon::OutlinedCheckCircle)
                    ->color('success')
                    ->visible(fn (Refund $record) => $record->status === RefundStatus::Requested)
                    ->modalHeading('Approve this refund')
                    ->modalDescription(fn (Refund $record) => 'Approve a refund of ₱' . number_format((float) $record->amount, 2) . '. No money moves until the payout is recorded.')
                    ->modalSubmitActionLabel('Approve')
                    ->schema([
                        Textarea::make('remarks')
                            ->label('Remarks (optional)'),
                    ])
                    ->action(fn (Refund $record, array $data, Action $action) => static::attempt(
                        $action, fn () => $record->approve($data['remarks'] ?? null)
                    )),

                Action::make('decline')
                    ->authorize(fn (): bool => auth()->user()->can('Approve:Refund'))
                    ->icon(Heroicon::OutlinedXCircle)
                    ->color('danger')
                    ->visible(fn (Refund $record) => $record->status === RefundStatus::Requested)
                    ->modalHeading('Decline this refund')
                    ->modalDescription('This is permanent. If the request had a mistake, you can file a new one afterwards.')
                    ->modalSubmitActionLabel('Decline')
                    ->schema([
                        Textarea::make('remarks')
                            ->label('Why is this refund being declined?')
                            ->required(),
                    ])
                    ->action(fn (Refund $record, array $data, Action $action) => static::attempt(
                        $action, fn () => $record->decline($data['remarks'])
                    )),

                Action::make('markRefunded')
                    ->authorize('update')
                    ->label('Record payout')
                    ->icon(Heroicon::OutlinedBanknotes)
                    ->color('primary')
                    ->visible(fn (Refund $record) => $record->status === RefundStatus::Approved)
                    ->modalHeading('Record the refund payout')
                    ->modalDescription(fn (Refund $record) => 'Paying out ₱' . number_format((float) $record->amount, 2) . '. This is permanent.')
                    ->modalSubmitActionLabel('Record payout')
                    ->schema([
                        DatePicker::make('refunded_on')
                            ->label('Date refunded')
                            ->default(today())
                            ->maxDate(today())
                            ->required(),
                        Select::make('method')
                            ->options(PaymentMethod::class)
                            ->default(PaymentMethod::Cash)
                            ->required(),
                        TextInput::make('reference_number')
                            ->label('Voucher / reference number')
                            ->required()
                            ->maxLength(255),
                        FileUpload::make('proof_path')
                            ->label('Proof (voucher or transfer screenshot)')
                            ->disk('local')
                            ->directory('refunds')
                            ->visibility('private')
                            ->acceptedFileTypes(['image/jpeg', 'image/png', 'application/pdf'])
                            ->maxSize(10240)
                            ->storeFileNamesIn('proof_name'),
                    ])
                    ->action(fn (Refund $record, array $data, Action $action) => static::attempt(
                        $action, fn () => $record->markRefunded(
                            $data['refunded_on'],
                            $data['method'] instanceof PaymentMethod ? $data['method'] : PaymentMethod::from($data['method']),
                            $data['reference_number'],
                            $data['proof_path'] ?? null,
                            $data['proof_name'] ?? null,
                        )
                    )),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageRefunds::route('/'),
        ];
    }
}