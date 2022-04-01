<?php

namespace App\Models;

use App\Traits\Uuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Meter extends Model
{
    use HasFactory, Uuid;

    public $incrementing = false;

    protected $keyType = 'uuid';

    protected $fillable = ['number', 'valve_status', 'station_id', 'type_id', 'mode', 'last_reading', 'last_reading_date', 'last_billing_date', 'voltage', 'signal', 'main_meter', 'sim_card_number'];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'last_reading_date' => 'datetime',
        'last_billing_date' => 'datetime',
    ];

    /**
     * Get meter station that owns the meter
     * @return BelongsTo
     */
    public function station(): BelongsTo
    {
        return $this->belongsTo(MeterStation::class);
    }

    /**
     * Get the meter associated with the user.
     * @return BelongsTo
     */
    public function type(): BelongsTo
    {
        return $this->belongsTo(MeterType::class);
    }

    /**
     * Get the user associated with the user.
     * @return HasOne
     */
    public function user(): HasOne
    {
        return $this->hasOne(User::class);
    }
}
