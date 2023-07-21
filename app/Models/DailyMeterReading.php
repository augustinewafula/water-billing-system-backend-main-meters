<?php

namespace App\Models;

use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\MassPrunable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class DailyMeterReading extends Model
{
    use HasFactory, HasUuid, MassPrunable;

    public $incrementing = false;

    protected $keyType = 'uuid';

    protected $dateFormat = 'Y-m-d H:i:s.u';

    protected $fillable = ['meter_id', 'reading'];

    public function getConsumedUnitsAttribute()
    {
        return $this->attributes['consumed_units'] ?? 0;
    }

    public function setConsumedUnitsAttribute($value)
    {
        $this->attributes['consumed_units'] = $value;
    }

    /**
     * Get the prunable model query.
     *
     * @return Builder
     */
    public function prunable(): Builder
    {
        return static::where('created_at', '<=', now()->subMonths(6));
    }
}
