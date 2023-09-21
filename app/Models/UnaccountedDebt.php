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
use Laravel\Scout\Searchable;

class UnaccountedDebt extends Model
{
    use HasFactory, HasUuid, Searchable, SoftDeletes, MassPrunable;

    public $incrementing = false;

    protected $keyType = 'uuid';

    protected $dateFormat = 'Y-m-d H:i:s.u';

    protected $fillable = ['user_id', 'amount_paid', 'amount_deducted', 'amount_remaining', 'mpesa_transaction_id'];

    protected $appends = [
        'initial_unaccounted_debt',
    ];

    public function getInitialUnaccountedDebtAttribute(): float
    {
        // Query only once for the sum of amount_paid for all UnaccountedDebt belonging to the same user
        $totalAmountPaid = self::where('user_id', $this->user_id)
            ->sum('amount_paid');

        // Retrieve the unaccounted_debt from the User model
        $userUnaccountedDebt = $this->user->unaccounted_debt;

        // Calculate the initial unaccounted debt
        $initialUnaccountedDebt = $totalAmountPaid + $userUnaccountedDebt;

        return round($initialUnaccountedDebt, 1);
    }

    /**
     * Get the user that owns the sms.
     * @return belongsTo
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
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
