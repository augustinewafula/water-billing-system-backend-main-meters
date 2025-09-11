<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ClientCallbackUrl extends Model
{
    use HasFactory, HasUuids, SoftDeletes;

    protected $fillable = [
        'client_id',
        'callback_url',
        'secret_token',
        'is_active',
        'retry_count',
        'max_retries',
        'timeout_seconds'
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'retry_count' => 'integer',
        'max_retries' => 'integer',
        'timeout_seconds' => 'integer'
    ];
}
