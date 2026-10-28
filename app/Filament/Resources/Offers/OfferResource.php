<?php

namespace App\Filament\Resources\Offers;

use App\Enums\OfferStatus;
use App\Filament\Resources\Offers\Pages\ManageOffers;
use App\Models\Application;
use App\Models\Offer;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Storage;

class OfferResource extends Resource
{
    protected static ?string $model = Offer::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedDocumentCheck;

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
                            $a->id => "{$a->applicant->full_name} — {$a->jobOrder->position}",
                        ]))
                    ->searchable()
                    ->required()
                    ->disabledOn('edit') 
                    ->live()
                    ->afterStateUpdated(function ($state, Set $set) {
                        $jobOrder = Application::find($state)?->jobOrder;

                        if ($jobOrder) {
                            $set('position', $jobOrder->position);
                            $set('salary', $jobOrder->salary);
                        }
                    }),

                DatePicker::make('released_on')
                    ->label('Date released by employer')
                    ->default(today())
                    ->maxDate(today())
                    ->required(),

                TextInput::make('position')
                    ->required()
                    ->maxLength(255),

                TextInput::make('salary')
                    ->label('Monthly salary')
                    ->numeric()
                    ->minValue(0)
                    ->required(),

                Select::make('currency')
                    ->options(['AED' => 'AED', 'USD' => 'USD', 'PHP' => 'PHP'])
                    ->default('AED')
                    ->required(),

                DatePicker::make('signing_deadline')
                    ->afterOrEqual('released_on'),

                FileUpload::make('offer_path')
                    ->label('Offer letter (unsigned)')
                    ->disk('local')
                    ->directory('offers')
                    ->visibility('private')
                    ->acceptedFileTypes(['image/jpeg', 'image/png', 'application/pdf'])
                    ->maxSize(10240)
                    ->storeFileNamesIn('offer_name')
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
                TextColumn::make('position')
                    ->searchable(),
                TextColumn::make('salary')
                    ->money(fn (Offer $record) => $record->currency)
                    ->sortable(),
                TextColumn::make('released_on')
                    ->label('Released')
                    ->date('M j, Y')
                    ->sortable(),
                TextColumn::make('signing_deadline')
                    ->label('Sign by')
                    ->date('M j, Y')
                    ->placeholder('—')
                    ->color(fn (Offer $record) => $record->isOverdue() ? 'danger' : null)
                    ->description(fn (Offer $record) => $record->isOverdue() ? 'Overdue' : null),
                TextColumn::make('status')
                    ->badge()
                    ->description(fn (Offer $record) => $record->declined_reason),
                TextColumn::make('signed_on')
                    ->label('Signed')
                    ->date('M j, Y')
                    ->placeholder('—')
                    ->toggleable(),
                TextColumn::make('signer.name')
                    ->label('Signature recorded by')
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('id', 'desc')
            ->filters([
                SelectFilter::make('status')
                    ->options(OfferStatus::class),
            ])
            ->recordActions([
                Action::make('offer')
                    ->label('Offer')
                    ->icon(Heroicon::OutlinedEye)
                    ->color('gray')
                    ->url(fn (Offer $record) => Storage::disk('local')
                        ->temporaryUrl($record->offer_path, now()->addMinutes(5)))
                    ->openUrlInNewTab(),

                Action::make('signedCopy')
                    ->label('Signed copy')
                    ->icon(Heroicon::OutlinedDocumentText)
                    ->color('gray')
                    ->visible(fn (Offer $record) => filled($record->signed_path))
                    ->url(fn (Offer $record) => Storage::disk('local')
                        ->temporaryUrl($record->signed_path, now()->addMinutes(5)))
                    ->openUrlInNewTab(),

                Action::make('recordSignature')
                    ->label('Record signature')
                    ->icon(Heroicon::OutlinedPencilSquare)
                    ->color('success')
                    ->visible(fn (Offer $record) => $record->status === OfferStatus::Pending)
                    ->modalDescription('Upload the copy the applicant signed on paper (wet signature).')
                    ->schema([
                        FileUpload::make('signed_path')
                            ->label('Signed offer letter')
                            ->disk('local')
                            ->directory('offers/signed')
                            ->visibility('private')
                            ->acceptedFileTypes(['image/jpeg', 'image/png', 'application/pdf'])
                            ->maxSize(10240)
                            ->storeFileNamesIn('signed_name')
                            ->required(),
                        DatePicker::make('signed_on')
                            ->label('Date signed')
                            ->default(today())
                            ->maxDate(today())
                            ->required(),
                    ])
                    ->action(fn (Offer $record, array $data) => $record->recordSignature(
                        $data['signed_path'],
                        $data['signed_name'] ?? basename($data['signed_path']),
                        $data['signed_on'],
                    )),

                Action::make('decline')
                    ->label('Mark declined')
                    ->icon(Heroicon::OutlinedXCircle)
                    ->color('danger')
                    ->visible(fn (Offer $record) => $record->status === OfferStatus::Pending)
                    ->schema([
                        Textarea::make('reason')
                            ->label('Why was the offer declined or not signed?')
                            ->required(),
                    ])
                    ->action(fn (Offer $record, array $data) => $record->decline($data['reason'])),

                EditAction::make()
                    ->visible(fn (Offer $record) => $record->status === OfferStatus::Pending),
                // No Delete on purpose — offers are permanent.
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageOffers::route('/'),
        ];
    }
}