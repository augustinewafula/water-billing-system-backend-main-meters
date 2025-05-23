<?php

namespace App\Models;

use App\Traits\HasUuid;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\MassPrunable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class MonthlyServiceCharge extends Model
{
    use HasFactory, HasUuid, SoftDeletes, MassPrunable;

    public $incrementing = false;

    protected $keyType = 'uuid';

    protected $dateFormat = 'Y-m-d H:i:s.u';

    protected $fillable = ['user_id', 'service_charge', 'status', 'month'];

    protected $casts = [
        'month' => 'date:Y-m-d',
    ];
    protected $appends = ['total_amount_paid'];

    /**
     * Get user that owns the user reading
     * @return BelongsTo
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the monthly service charge payments for the monthly service charge.
     * @return HasMany
     */
    public function monthlyServiceChargePayments(): HasMany
    {
        return $this->hasMany(MonthlyServiceChargePayment::class);
    }

    /**
     * Get total amount paid for this service charge.
     *
     * @return float
     */
    public function getTotalAmountPaidAttribute(): float
    {
        return $this->monthlyServiceChargePayments->sum('amount_paid');
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
