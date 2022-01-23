<?php

namespace App\Models;

use App\Traits\Uuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Meter extends Model
{
    use HasFactory, Uuid;

    public $incrementing = false;

    protected $keyType = 'uuid';

    protected $fillable = ['number', 'valve_status', 'station_id', 'type'];

    /**
     * Get meter station that owns the meter
     * @return BelongsTo
     */
    public function meter_station(): BelongsTo
    {
        return $this->belongsTo(MeterStation::class);
    }

    /**
     * Get the meter associated with the user.
     * @return HasMany
     */
    public function meter_type(): HasMany
    {
        return $this->hasMany(MeterType::class);
    }

    /**
     * Get the user associated with the user.
     * @return HasOne
     */
    public function user(): HasOne
    {
        return $this->HasOne(User::class);
    }
}
