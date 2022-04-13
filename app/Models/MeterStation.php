<?php

namespace App\Models;

use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MeterStation extends Model
{
    use HasFactory, HasUuid;

    public $incrementing = false;

    protected $keyType = 'uuid';

    protected $fillable = ['name', 'location', 'paybill_number'];

    /**
     * Get the meters for the meter station.
     * @return HasMany
     */
    public function meters(): HasMany
    {
        return $this->hasMany(Meter::class, 'station_id');
    }

    /**
     * Get the use that owns the meter
     * @return BelongsTo
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
