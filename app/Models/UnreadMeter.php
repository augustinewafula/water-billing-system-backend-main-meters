<?php

namespace App\Models;

use App\Traits\HasUuid;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class UnreadMeter extends Model
{
    use HasFactory, HasUuid;

    public $incrementing = false;
    protected $keyType = 'uuid';
    protected $fillable = [
        'meter_id',
        'month',
        'reason',
    ];

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
}
