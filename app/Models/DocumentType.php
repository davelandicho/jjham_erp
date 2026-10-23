<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class DocumentType extends Model
{
    protected $fillable = [
        'name',
        'has_expiry',
        'description',
    ];

    protected function casts(): array
    {
        return [
            'has_expiry' => 'boolean',
        ];
    }

    public function jobOrders(): belongsToMany
    {
        return $this->belongsToMany(JobOrder::class)->withTimestamps();
    }
}
