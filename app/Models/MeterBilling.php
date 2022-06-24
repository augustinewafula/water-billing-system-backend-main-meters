<?php

namespace App\Models;

use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\MassPrunable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class MeterBilling extends Model
{
    use HasFactory, HasUuid, SoftDeletes, MassPrunable;

    public $incrementing = false;

    protected $keyType = 'uuid';

    protected $dateFormat = 'Y-m-d H:i:s.u';

    protected $fillable = ['meter_reading_id', 'amount_paid', 'balance', 'mpesa_transaction_id', 'date_paid', 'amount_over_paid', 'credit', 'monthly_service_charge_deducted', 'connection_fee_deducted', 'unaccounted_debt_deducted'];

    /**
     * Get meter reading that owns the meter billing
     * @return BelongsTo
     */
    public function meter_reading(): BelongsTo
    {
        return $this->belongsTo(MeterReading::class);
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
