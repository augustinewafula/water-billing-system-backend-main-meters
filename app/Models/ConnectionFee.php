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

class ConnectionFee extends Model
{
    use HasFactory, HasUuid, SoftDeletes, MassPrunable;

    public $incrementing = false;

    protected $keyType = 'uuid';

    protected $dateFormat = 'Y-m-d H:i:s.u';

    protected $fillable = ['user_id', 'amount', 'status', 'month'];

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
