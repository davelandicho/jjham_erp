<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class JobOrder extends Model
{
    protected $fillable = [
        'employer_id',
        'position',
        'openings',
        'salary',
        'total_fee',
        'notes',
    ];

    public function employer(): BelongsTo
    {
        return $this->belongsTo(employer::class);
    }
}
