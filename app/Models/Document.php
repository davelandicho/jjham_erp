<?php

namespace App\Models;

use App\Enums\DocumentStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class Document extends Model
{
    protected $fillable = [
        'application_id',
        'document_type_id',
        'file_path',
        'original_name',
        'mime_type',
        'size',
        'expiry_date',
        'status',
        'rejection_reason',
        'upload_source',
    ];

    public const LOCKED_FIELDS = [
        'application_id', 'document_type_id', 'version',
        'file_path', 'original_name', 'mime_type', 'size',
    ];

    protected function casts(): array
    {
        return [
            'status' => DocumentStatus::class,
            'expiry_date' => 'date',
            'verified_at' => 'datetime',
            'size' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Document $document) {
            $latest = static::where('application_id', $document->application_id)
                ->where('document_type_id', $document->document_type_id)
                ->max('version');

            $document->version = ($latest ?? 0) + 1;
            $document->uploaded_by ??= auth()->id();
            $document->status ??= DocumentStatus::Pending;

            if ($document->file_path && blank($document->mime_type)) {
                $disk = Storage::disk('local');
                $document->mime_type = $disk->mimeType($document->file_path);
                $document->size = $disk->size($document->file_path);
            }            
        });

        static::updating(function (Document $document) {
            if ($document->isDirty(self::LOCKED_FIELDS)) {
                throw new \DomainException('A document file cannot be changed. Upload a new version instead.');
            }

            if ($document->isDirty('status')) {
                if ($document->status === DocumentStatus::Rejected && blank($document->rejection_reason)) {
                    throw new \DomainException('A rejection reason is required.');
                }

                $document->verified_by = auth()->id();
                $document->verified_at = now();
            }
        });

        static::deleting(function () {
            throw new \DomainException('Document versions can never be deleted.');
        });
    }

    public function application(): BelongsTo
    {
        return $this->belongsTo(Application::class);
    }

    public function documentType(): BelongsTo
    {
        return $this->belongsTo(DocumentType::class);
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function verifier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verified_by');
    }
}