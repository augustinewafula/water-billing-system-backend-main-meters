<?php

namespace App\Models;

use App\Enums\PaymentStatus;
use App\Traits\HasUuid;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\MassPrunable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class ConnectionFee extends Model
{
    use HasFactory, HasUuid, SoftDeletes, MassPrunable;

    public $incrementing = false;

    protected $keyType = 'uuid';

    protected $dateFormat = 'Y-m-d H:i:s.u';

    protected $fillable = [
        'user_id',
        'amount',
        'status',
        'month',
        'added_to_user_total_debt',
        'bill_remainder_sms_sent'
    ];

    protected $casts = [
        'month' => 'datetime:Y-m-d',
    ];

    public function scopeNotAddedToUserTotalDebt(Builder $query): Builder
    {
        return $query->where('added_to_user_total_debt', false);
    }

    public function scopeBillRemainderSmsNotSent(Builder $query): Builder
    {
        return $query->where('bill_remainder_sms_sent', false);
    }

    public function scopeNotPaid(Builder $query): Builder
    {
        return $query->where('status', PaymentStatus::NOT_PAID);
    }

    public function scopeHasBalance(Builder $query): Builder
    {
        return $query->where('status', PaymentStatus::PARTIALLY_PAID);
    }

    public function scopeCurrentMonth(Builder $query): Builder
    {
        return $query->whereDate('month', Carbon::now()->startOfMonth()->startOfDay());
    }

    public function scopeCurrentAndPreviousMonth(Builder $query): Builder
    {
        return $query->where('month', '<=', Carbon::now()->startOfMonth()->startOfDay());
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
     * Get the connection fee payments for the connection fee.
     * @return HasMany
     */
    public function connection_fee_payments(): HasMany
    {
        return $this->hasMany(ConnectionFeePayment::class)->latest();
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
