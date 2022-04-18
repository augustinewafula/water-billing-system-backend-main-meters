<?php

namespace App\Models;

use App\Traits\ClearsResponseCache;
use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class MeterStation extends Model
{
    use HasFactory, HasUuid, ClearsResponseCache, SoftDeletes;

    public $incrementing = false;

    protected $keyType = 'uuid';

    protected $dateFormat = 'Y-m-d H:i:s.u';

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
