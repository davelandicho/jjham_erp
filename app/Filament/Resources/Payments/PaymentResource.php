<?php

namespace App\Filament\Resources\Payments;

use App\Enums\PaymentMethod;
use App\Filament\Resources\Payments\Pages\ManagePayments;
use App\Models\Application;
use App\Models\Payment;
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
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Storage;
use UnitEnum;

class PaymentResource extends Resource
{
    protected static ?string $model = Payment::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBanknotes;

    protected static string|UnitEnum|null $navigationGroup = NavigationGroup::Finance;

    protected static ?int $navigationSort = 1;

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('application_id')
                    ->label('Application')
                    ->options(fn () => Application::with(['applicant', 'jobOrder'])
                        ->where('fee', '>', 0)
                        ->latest('id')
                        ->get()
                        ->mapWithKeys(fn (Application $a) => [
                            $a->id => "{$a->applicant->full_name} — {$a->jobOrder->position} (balance ₱"
                                . number_format($a->balance(), 2) . ')',
                        ]))
                    ->searchable()
                    ->required()
                    ->helperText('Only applications with a fee are listed.'),

                TextInput::make('amount')
                    ->numeric()
                    ->prefix('₱')
                    ->minValue(0.01)
                    ->required(),

                DatePicker::make('paid_on')
                    ->label('Date paid')
                    ->default(today())
                    ->maxDate(today())
                    ->required(),

                Select::make('method')
                    ->options(PaymentMethod::class)
                    ->default(PaymentMethod::Cash)
                    ->required(),

                TextInput::make('reference_number')
                    ->label('OR / reference number')
                    ->required()
                    ->maxLength(255),

                FileUpload::make('proof_path')
                    ->label('Proof (receipt or transfer screenshot)')
                    ->disk('local')
                    ->directory('payments')
                    ->visibility('private')
                    ->acceptedFileTypes(['image/jpeg', 'image/png', 'application/pdf'])
                    ->maxSize(10240)
                    ->storeFileNamesIn('proof_name')
                    ->columnSpanFull(),

                Textarea::make('remarks')
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
                TextColumn::make('amount')
                    ->money('PHP')
                    ->color(fn (Payment $record) => $record->isVoided() ? 'gray' : null)
                    ->sortable(),
                TextColumn::make('paid_on')
                    ->label('Paid')
                    ->date('M j, Y')
                    ->sortable(),
                TextColumn::make('method')
                    ->badge(),
                TextColumn::make('reference_number')
                    ->label('OR / Ref.')
                    ->searchable(),
                TextColumn::make('voided_at')
                    ->label('Status')
                    ->badge()
                    ->state(fn (Payment $record) => $record->isVoided() ? 'Voided' : 'Valid')
                    ->color(fn (Payment $record) => $record->isVoided() ? 'danger' : 'success')
                    ->description(fn (Payment $record) => $record->void_reason),
                TextColumn::make('receiver.name')
                    ->label('Received by')
                    ->placeholder('—')
                    ->toggleable(),
            ])
            ->defaultSort('id', 'desc')
            ->filters([
                SelectFilter::make('method')
                    ->options(PaymentMethod::class),
                TernaryFilter::make('voided_at')
                    ->label('Voided')
                    ->nullable(),
            ])
            ->recordActions([
                Action::make('proofFile')
                    ->label('Proof')
                    ->icon(Heroicon::OutlinedEye)
                    ->color('gray')
                    ->visible(fn (Payment $record) => filled($record->proof_path))
                    ->url(fn (Payment $record) => Storage::disk('local')
                        ->temporaryUrl($record->proof_path, now()->addMinutes(5)))
                    ->openUrlInNewTab(),

                Action::make('void')
                    ->label('Void')
                    ->icon(Heroicon::OutlinedNoSymbol)
                    ->color('danger')
                    ->visible(fn (Payment $record) => ! $record->isVoided())
                    ->modalDescription('The payment stays on record but no longer counts toward the fee. This cannot be undone.')
                    ->schema([
                        Textarea::make('reason')
                            ->label('Why is this payment being voided?')
                            ->required(),
                    ])
                    ->action(fn (Payment $record, array $data) => $record->void($data['reason'])),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManagePayments::route('/'),
        ];
    }
}