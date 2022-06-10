<?php

namespace App\Models;

use App\Traits\ClearsResponseCache;
use App\Traits\HasUuid;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\MassPrunable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\Traits\LogsActivity;

class MeterReading extends Model
{
    use HasFactory, HasUuid, ClearsResponseCache, SoftDeletes, MassPrunable, LogsActivity;

    public $incrementing = false;

    protected $keyType = 'uuid';

    protected $dateFormat = 'Y-m-d H:i:s.u';

    protected $fillable = ['meter_id', 'previous_reading', 'current_reading', 'month', 'bill', 'service_fee', 'monthly_service_charge_deducted', 'status', 'sms_sent', 'send_sms_at', 'bill_due_at', 'tell_user_meter_disconnection_on', 'actual_meter_disconnection_on', 'disconnection_remainder_sms_sent'];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'send_sms_at' => 'datetime',
        'bill_due_at' => 'datetime:Y-m-d',
        'tell_user_meter_disconnection_on' => 'datetime:Y-m-d',
        'actual_meter_disconnection_on' => 'datetime:Y-m-d',
    ];

    protected static $submitEmptyLogs = false;
    protected static $logFillable = true;

    public function setMonthAttribute(string $value): void
    {
        $this->attributes['month'] = Carbon::parse($value)->format('Y-m-d');
    }


    public function getMonthAttribute(string $value): string
    {
        return Carbon::parse($value)->format('Y-m');
    }

    /**
     * Get meter that owns the meter reading
     * @return BelongsTo
     */
    public function meter(): BelongsTo
    {
        return $this->belongsTo(Meter::class);
    }

    /**
     * Get meter that owns the meter reading
     * @return hasOne
     */
    public function user(): HasOne
    {
        return $this->hasOne(User::class, 'meter_id', 'meter_id');
    }

    /**
     * Get the meter_billings for the meter reading.
     * @return HasMany
     */
    public function meter_billings(): HasMany
    {
        return $this->hasMany(MeterBilling::class)->latest();
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
