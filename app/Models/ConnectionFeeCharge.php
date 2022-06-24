<?php

namespace App\Models;

use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\MassPrunable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class ConnectionFeeCharge extends Model
{
    use HasFactory, HasUuid, SoftDeletes, MassPrunable;

    public $incrementing = false;

    protected $dateFormat = 'Y-m-d H:i:s.u';

    protected $fillable = ['connection_fee', 'connection_fee_monthly_installment', 'station_id'];

    /**
     * Get meter station that owns the connection fee charge
     * @return BelongsTo
     */
    public function station(): BelongsTo
    {
        return $this->belongsTo(MeterStation::class);
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
