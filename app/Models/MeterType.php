<?php

namespace App\Models;

use App\Traits\ClearsResponseCache;
use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MeterType extends Model
{
    use HasFactory, HasUuid, ClearsResponseCache;

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
