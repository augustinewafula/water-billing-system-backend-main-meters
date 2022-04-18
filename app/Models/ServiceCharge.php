<?php

namespace App\Models;

use App\Traits\ClearsResponseCache;
use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class ServiceCharge extends Model
{
    use HasFactory, HasUuid, ClearsResponseCache, SoftDeletes;

    public $incrementing = false;

    protected $dateFormat = 'Y-m-d H:i:s.u';

    protected $fillable = ['from', 'to', 'amount', 'meter_charge_id'];

    /**
     * Get meter charge that owns the service charge
     * @return BelongsTo
     */
    public function meter_charge(): BelongsTo
    {
        return $this->belongsTo(MeterCharge::class);
    }
}
