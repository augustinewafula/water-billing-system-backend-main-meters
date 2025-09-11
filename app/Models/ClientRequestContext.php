<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ClientRequestContext extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'meter_id',
        'client_id',
        'message_id',
        'action_type',
        'original_request',
        'hexing_response',
        'status'
    ];

    protected $casts = [
        'original_request' => 'array',
        'hexing_response' => 'array'
    ];

    public function meter(): BelongsTo
    {
        return $this->belongsTo(Meter::class);
    }
}
