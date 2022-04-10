<?php

namespace App\Models;

use App\Traits\Uuid;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MonthlyServiceCharge extends Model
{
    use HasFactory, Uuid;

    public $incrementing = false;

    protected $keyType = 'uuid';

    protected $fillable = ['user_id', 'service_charge', 'amount_paid', 'balance', 'credit', 'amount_over_paid', 'month', 'mpesa_transaction_id'];

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
}
