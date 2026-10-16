<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Employer extends Model
{
    protected $fillable =[
        'name',
        'country',
        'contact_person',
        'contact_number',
        'email',
        'address',
        'notes',
    ];

    public $timestamps = false;

    public function jobOrders(): HasMany
    {
        return $this->hasMany(JobOrder::class);
    }
}
