<?php

namespace App\Models;

use App\Traits\Uuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MonthlyServiceChargePayment extends Model
{
    use HasFactory, Uuid;

    public $incrementing = false;

    protected $keyType = 'uuid';

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
