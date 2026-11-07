<?php

namespace App\Filament\Resources\Contracts;

use App\Enums\ContractType;
use App\Enums\MwoStatus;
use App\Filament\Resources\Contracts\Pages\ManageContracts;
use App\Models\Application;
use App\Models\Contract;
use App\Enums\NavigationGroup;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Storage;
use UnitEnum;

class ContractResource extends Resource
{
    protected static ?string $model = Contract::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedDocumentDuplicate;

    protected static string|UnitEnum|null $navigationGroup = NavigationGroup::Processing;

    protected static ?int $navigationSort = 2;

    public static function form(Schema $schema): Schema
    {
        // The form may hold the enum itself or its string value, so accept both.
        $isMohre = fn (Get $get): bool => in_array(
            $get('type'),
            [ContractType::Mohre, ContractType::Mohre->value],
            true,
        );

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
                    ->disabledOn('edit'),

                Select::make('type')
                    ->options(ContractType::class)
                    ->required()
                    ->live()
                    ->disabledOn('edit'),

                TextInput::make('contract_number')
                    ->label('Contract number')
                    ->required($isMohre)
                    ->maxLength(255),

                DatePicker::make('issued_on')
                    ->label('Date issued')
                    ->default(today())
                    ->maxDate(today())
                    ->required(),

                FileUpload::make('contract_path')
                    ->label('Contract (unsigned)')
                    ->disk('local')
                    ->directory('contracts')
                    ->visibility('private')
                    ->acceptedFileTypes(['image/jpeg', 'image/png', 'application/pdf'])
                    ->maxSize(10240)
                    ->storeFileNamesIn('contract_name')
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
                TextColumn::make('type')
                    ->badge(),
                TextColumn::make('contract_number')
                    ->label('Number')
                    ->placeholder('—')
                    ->searchable(),
                TextColumn::make('issued_on')
                    ->label('Issued')
                    ->date('M j, Y')
                    ->sortable(),
                TextColumn::make('signed_on')
                    ->label('Signed')
                    ->date('M j, Y')
                    ->placeholder('Not signed'),
                TextColumn::make('mwo_status')
                    ->label('MWO')
                    ->badge()
                    ->placeholder('—')
                    ->description(fn (Contract $record) => $record->mwo_return_reason),
                IconColumn::make('is_complete')
                    ->label('Complete')
                    ->state(fn (Contract $record) => $record->isComplete())
                    ->boolean(),
            ])
            ->defaultSort('id', 'desc')
            ->filters([
                SelectFilter::make('type')
                    ->options(ContractType::class),
                SelectFilter::make('mwo_status')
                    ->label('MWO')
                    ->options(MwoStatus::class),
            ])
            ->recordActions([
                ActionGroup::make([
                    Action::make('contractFile')
                        ->label('Contract')
                        ->icon(Heroicon::OutlinedEye)
                        ->url(fn (Contract $record) => Storage::disk('local')
                            ->temporaryUrl($record->contract_path, now()->addMinutes(5)))
                        ->openUrlInNewTab(),
                    Action::make('signedFile')
                        ->label('Signed copy')
                        ->icon(Heroicon::OutlinedDocumentText)
                        ->visible(fn (Contract $record) => filled($record->signed_path))
                        ->url(fn (Contract $record) => Storage::disk('local')
                            ->temporaryUrl($record->signed_path, now()->addMinutes(5)))
                        ->openUrlInNewTab(),
                    Action::make('mwoProofFile')
                        ->label('MWO proof')
                        ->icon(Heroicon::OutlinedShieldCheck)
                        ->visible(fn (Contract $record) => filled($record->mwo_proof_path))
                        ->url(fn (Contract $record) => Storage::disk('local')
                            ->temporaryUrl($record->mwo_proof_path, now()->addMinutes(5)))
                        ->openUrlInNewTab(),
                ])
                    ->label('Files')
                    ->icon(Heroicon::OutlinedPaperClip)
                    ->color('gray'),

                Action::make('recordSignature')
                    ->authorize('update')
                    ->label('Record signature')
                    ->icon(Heroicon::OutlinedPencilSquare)
                    ->color('success')
                    ->visible(fn (Contract $record) => ! $record->isSigned())
                    ->modalDescription('Upload the copy the applicant signed on paper (wet signature).')
                    ->schema([
                        FileUpload::make('signed_path')
                            ->label('Signed contract')
                            ->disk('local')
                            ->directory('contracts/signed')
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
                    ->action(fn (Contract $record, array $data) => $record->recordSignature(
                        $data['signed_path'],
                        $data['signed_name'] ?? basename($data['signed_path']),
                        $data['signed_on'],
                    )),

                Action::make('submitToMwo')
                    ->authorize('update')
                    ->label('Submit to MWO')
                    ->icon(Heroicon::OutlinedPaperAirplane)
                    ->color('warning')
                    ->visible(fn (Contract $record) => $record->type->needsMwoVerification()
                        && $record->isSigned()
                        && $record->mwo_status === null)
                    ->schema([
                        DatePicker::make('submitted_on')
                            ->label('Date submitted to MWO')
                            ->default(today())
                            ->maxDate(today())
                            ->required(),
                    ])
                    ->action(fn (Contract $record, array $data) => $record->submitToMwo($data['submitted_on'])),

                Action::make('mwoVerified')
                    ->authorize('update')
                    ->label('MWO verified')
                    ->icon(Heroicon::OutlinedCheckBadge)
                    ->color('success')
                    ->visible(fn (Contract $record) => $record->mwo_status === MwoStatus::Submitted)
                    ->schema([
                        DatePicker::make('verified_on')
                            ->label('Date verified')
                            ->default(today())
                            ->maxDate(today())
                            ->required(),
                        FileUpload::make('proof_path')
                            ->label('MWO verification proof')
                            ->disk('local')
                            ->directory('contracts/mwo')
                            ->visibility('private')
                            ->acceptedFileTypes(['image/jpeg', 'image/png', 'application/pdf'])
                            ->maxSize(10240)
                            ->storeFileNamesIn('proof_name')
                            ->required(),
                    ])
                    ->action(fn (Contract $record, array $data) => $record->markMwoVerified(
                        $data['verified_on'],
                        $data['proof_path'],
                        $data['proof_name'] ?? basename($data['proof_path']),
                    )),

                Action::make('mwoReturned')
                    ->authorize('update')
                    ->label('MWO returned')
                    ->icon(Heroicon::OutlinedArrowUturnLeft)
                    ->color('danger')
                    ->visible(fn (Contract $record) => $record->mwo_status === MwoStatus::Submitted)
                    ->modalDescription('Afterwards, upload the corrected contract as a new record.')
                    ->schema([
                        Textarea::make('reason')
                            ->label('Why did the MWO return it?')
                            ->required(),
                    ])
                    ->action(fn (Contract $record, array $data) => $record->markMwoReturned($data['reason'])),

                EditAction::make()
                    ->visible(fn (Contract $record) => ! $record->isSigned()),
                // No Delete on purpose — contracts are permanent.
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageContracts::route('/'),
        ];
    }
}