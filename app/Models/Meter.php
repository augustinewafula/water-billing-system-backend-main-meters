<?php

namespace App\Models;

use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\MassPrunable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Activitylog\LogOptions;

class Meter extends Model
{
    use HasFactory, HasUuid, SoftDeletes, MassPrunable, LogsActivity;

    public $incrementing = false;

    protected $keyType = 'uuid';

    protected $dateFormat = 'Y-m-d H:i:s.u';

    protected $fillable = ['number', 'valve_status', 'station_id', 'type_id', 'mode', 'last_reading', 'last_reading_date', 'last_billing_date', 'last_communication_date', 'battery_voltage', 'signal_intensity', 'main_meter', 'sim_card_number', 'valve_last_switched_off_by', 'can_generate_token'];

    protected $appends = ['current_reading'];


    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'last_reading_date' => 'datetime',
        'last_billing_date' => 'datetime',
        'last_communication_date' => 'datetime',
    ];

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly($this->fillable);
        // Chain fluent methods for configuration options
    }

    public function getCurrentReadingAttribute(){
         $current_reading  = DailyMeterReading::where('meter_id', $this->attributes['id'])
            ->latest()
            ->first();
         if($current_reading){
            return $current_reading->reading;
         }

         return  null;
    }


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

    /**
     * Get the prunable model query.
     *
     * @return Builder
     */
    public function prunable(): Builder
    {
        return static::where('created_at', '<=', now()->subMonth());
    }
}
