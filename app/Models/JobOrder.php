<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class JobOrder extends Model
{
    protected $fillable = [
        'employer_id',
        'position',
        'openings',
        'salary',
        'notes',
    ];

    public function employer(): BelongsTo
    {
        return $this->belongsTo(Employer::class);
    }

    public function applications(): HasMany
    {
        return $this->hasMany(Application::class);
    }

    public function documentTypes(): BelongsToMany
    {
        return $this->belongsToMany(DocumentType::class)->withTimeStamps();
    }
}
