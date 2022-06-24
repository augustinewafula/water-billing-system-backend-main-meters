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
use Illuminate\Database\Eloquent\SoftDeletes;

class MonthlyServiceCharge extends Model
{
    use HasFactory, HasUuid, SoftDeletes, MassPrunable;

    public $incrementing = false;

    protected $keyType = 'uuid';

    protected $dateFormat = 'Y-m-d H:i:s.u';

    protected $fillable = ['user_id', 'service_charge', 'status', 'month'];

    public function setMonthAttribute(string $value): void
    {
        $this->attributes['month'] = Carbon::parse($value)->format('Y-m-d');
    }

    public function getMonthAttribute(string $value): string
    {
        return Carbon::parse($value)->format('Y-m');
    }

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
    public function monthly_service_charge_payments(): HasMany
    {
        return $this->hasMany(MonthlyServiceChargePayment::class)->latest();
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
