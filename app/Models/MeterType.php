<?php

namespace App\Models;

use App\Traits\Uuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MeterType extends Model
{
    use HasFactory, Uuid;

    public $incrementing = false;

    protected $keyType = 'uuid';

    /**
     * Get the meters that belong to meter type.
     * @return HasMany
     */
    public function meters(): HasMany
    {
        return $this->hasMany(Meter::class, 'type_id');
    }
}
