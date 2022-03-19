<?php

namespace App\Models;

use App\Traits\Uuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Laravel\Scout\Searchable;

class Sms extends Model
{
    use HasFactory, Uuid, Searchable;

    public $incrementing = false;

    protected $keyType = 'uuid';

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
