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

class Sms extends Model
{
    use HasFactory, HasUuid, Searchable, SoftDeletes, MassPrunable;

    public $incrementing = false;

    protected $keyType = 'uuid';

    protected $dateFormat = 'Y-m-d H:i:s.u';

    protected $fillable = ['phone', 'message', 'status', 'cost', 'user_id', 'message_id', 'network_code', 'failure_reason', 'station_id'];

    /**
     * Get the user that owns the sms.
     * @return belongsTo
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get meter station that owns the meter
     * @return BelongsTo
     */
    public function station(): BelongsTo
    {
        return $this->belongsTo(MeterStation::class);
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
