<?php

namespace App\Models;

use App\Traits\ClearsResponseCache;
use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\MassPrunable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class ConnectionFeePayment extends Model
{
    use HasFactory, HasUuid, ClearsResponseCache, SoftDeletes, MassPrunable;

    public $incrementing = false;

    protected $keyType = 'uuid';

    protected $dateFormat = 'Y-m-d H:i:s.u';

    protected $fillable = ['connection_fee_id', 'amount_paid', 'balance', 'credit', 'amount_over_paid', 'monthly_service_charge_deducted', 'mpesa_transaction_id'];

    /**
     * Get connection fee that owns the connection fee payment
     * @return BelongsTo
     */
    public function connection_fee(): BelongsTo
    {
        return $this->belongsTo(ConnectionFee::class);
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
