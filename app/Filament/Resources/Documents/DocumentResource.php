<?php

namespace App\Filament\Resources\Documents;

use App\Enums\DocumentStatus;
use App\Filament\Resources\Documents\Pages\ManageDocuments;
use App\Models\Application;
use App\Models\Document;
use App\Models\DocumentType;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Storage;

class DocumentResource extends Resource
{
    protected static ?string $model = Document::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedDocumentText;

    public static function form(Schema $schema): Schema
    {
        $needsExpiry = fn (Get $get): bool => (bool) DocumentType::find($get('document_type_id'))?->has_expiry;

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
                    ->live()
                    ->afterStateUpdated(fn (Set $set) => $set('document_type_id', null)),

                Select::make('document_type_id')
                    ->label('Document type')
                    ->options(fn (Get $get) => Application::find($get('application_id'))
                        ?->jobOrder
                        ?->documentTypes()
                        ->orderBy('document_types.name')
                        ->pluck('document_types.name', 'document_types.id') ?? [])
                    ->helperText("Only documents on this job order's checklist are listed.")
                    ->disabled(fn (Get $get) => blank($get('application_id')))
                    ->required()
                    ->live(),

                static::fileField(),

                DatePicker::make('expiry_date')
                    ->visible($needsExpiry)
                    ->required($needsExpiry)
                    ->after('today'),

                static::sourceField(),
            ]);
    }

    public static function fileField(): FileUpload
    {
        return FileUpload::make('file_path')
            ->label('File')
            ->disk('local')               // storage/app/private — no public URL
            ->directory('documents')
            ->visibility('private')
            ->acceptedFileTypes(['image/jpeg', 'image/png', 'application/pdf'])
            ->maxSize(10240)              // in KB = 10 MB (spec 4.2)
            ->storeFileNamesIn('original_name')
            ->required()
            ->columnSpanFull();
    }

    public static function sourceField(): Select
    {
        return Select::make('upload_source')
            ->label('Received via')
            ->options([
                'walk_in' => 'Walk-in',
                'messenger' => 'Messenger',
                'viber' => 'Viber',
                'whatsapp' => 'WhatsApp',
                'email' => 'Email',
            ])
            ->helperText('Staff uploads are recorded as "uploaded on behalf of the applicant".')
            ->required();
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
                TextColumn::make('documentType.name')
                    ->label('Document')
                    ->searchable(),
                TextColumn::make('version')
                    ->formatStateUsing(fn (int $state) => "v{$state}"),
                TextColumn::make('status')
                    ->badge()
                    ->description(fn (Document $record) => $record->rejection_reason),
                TextColumn::make('expiry_date')
                    ->date('M j, Y')
                    ->placeholder('—')
                    ->sortable(),
                TextColumn::make('uploader.name')
                    ->label('Uploaded by')
                    ->placeholder('—'),
                TextColumn::make('verifier.name')
                    ->label('Reviewed by')
                    ->placeholder('—')
                    ->toggleable(),
                TextColumn::make('created_at')
                    ->label('Uploaded')
                    ->since()
                    ->sortable(),
            ])
            ->defaultSort('id', 'desc')
            ->filters([
                SelectFilter::make('status')
                    ->options(
                        collect(DocumentStatus::cases())
                            ->mapWithKeys(fn (DocumentStatus $case) => [$case->value => $case->getLabel()])
                            ->all()
                    ),
                SelectFilter::make('document_type_id')
                    ->label('Document')
                    ->relationship('documentType', 'name'),
            ])
            ->recordActions([
                Action::make('open')
                    ->icon(Heroicon::OutlinedEye)
                    ->color('gray')
                    ->url(fn (Document $record) => Storage::disk('local')
                        ->temporaryUrl($record->file_path, now()->addMinutes(5)))
                    ->openUrlInNewTab(),

                Action::make('verify')
                    ->icon(Heroicon::OutlinedCheckCircle)
                    ->color('success')
                    ->requiresConfirmation()
                    ->modalDescription('Confirm you have checked this file against the original.')
                    ->visible(fn (Document $record) => $record->status === DocumentStatus::Pending)
                    ->action(fn (Document $record) => $record->update([
                        'status' => DocumentStatus::Verified,
                    ])),

                Action::make('reject')
                    ->icon(Heroicon::OutlinedXCircle)
                    ->color('danger')
                    ->visible(fn (Document $record) => $record->status === DocumentStatus::Pending)
                    ->schema([
                        Textarea::make('rejection_reason')
                            ->label('Reason (the applicant will see this)')
                            ->required()
                            ->maxLength(500),
                    ])
                    ->action(fn (Document $record, array $data) => $record->update([
                        'status' => DocumentStatus::Rejected,
                        'rejection_reason' => $data['rejection_reason'],
                    ])),

                Action::make('newVersion')
                    ->label('New version')
                    ->icon(Heroicon::OutlinedArrowUpTray)
                    ->color('gray')
                    ->modalHeading(fn (Document $record) => "Upload new version of {$record->documentType->name}")
                    ->schema([
                        static::fileField(),
                        DatePicker::make('expiry_date')
                            ->visible(fn (Document $record) => $record->documentType->has_expiry)
                            ->required(fn (Document $record) => $record->documentType->has_expiry)
                            ->after('today'),
                        static::sourceField(),
                    ])
                    ->action(fn (Document $record, array $data) => Document::create([
                        ...$data,
                        'application_id' => $record->application_id,
                        'document_type_id' => $record->document_type_id,
                    ]))
                    ->successNotificationTitle('New version uploaded'),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageDocuments::route('/'),
        ];
    }
}