<?php

namespace App\Models;

use App\Traits\ClearsResponseCache;
use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Laravel\Scout\Searchable;

class Sms extends Model
{
    use HasFactory, HasUuid, Searchable, ClearsResponseCache, SoftDeletes;

    public $incrementing = false;

    protected $keyType = 'uuid';

    protected $dateFormat = 'Y-m-d H:i:s.u';

    protected $fillable = ['phone', 'message', 'status', 'cost', 'user_id'];

    /**
     * Get the user that owns the sms.
     * @return belongsTo
     */
    public function meter(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
