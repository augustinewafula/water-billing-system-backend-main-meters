<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class HexingMeterCallbackLog extends Model
{
    use HasFactory, HasUuids, SoftDeletes;

    protected $fillable = [
        'meter_id',
        'message_id',
        'action',
        'request_payload',
        'response_payload',
        'status',
        'sent_at',
        'callback_received_at'
    ];

    protected $casts = [
        'request_payload' => 'array',
        'response_payload' => 'array',
        'sent_at' => 'timestamp',
        'callback_received_at' => 'timestamp'
    ];

    public function meter(): BelongsTo
    {
        return $this->belongsTo(Meter::class);
    }
}
