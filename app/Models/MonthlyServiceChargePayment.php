<?php

namespace App\Models;

use App\Traits\ClearsResponseCache;
use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class MonthlyServiceChargePayment extends Model
{
    use HasFactory, HasUuid, ClearsResponseCache, SoftDeletes;

    public $incrementing = false;

    protected $keyType = 'uuid';

    protected $dateFormat = 'Y-m-d H:i:s.u';

    protected $fillable = ['monthly_service_charge_id', 'amount_paid', 'balance', 'credit', 'amount_over_paid', 'mpesa_transaction_id'];

    /**
     * Get monthly_service_charge that owns the monthly_service_charge payment
     * @return BelongsTo
     */
    public function monthly_service_charge(): BelongsTo
    {
        return $this->belongsTo(MonthlyServiceCharge::class);
    }
}
