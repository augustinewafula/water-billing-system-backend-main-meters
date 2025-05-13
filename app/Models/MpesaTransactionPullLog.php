<?php

namespace App\Models;

use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Prunable;

class MpesaTransactionPullLog extends Model
{
    use HasFactory, HasUuid, Prunable;

    protected $fillable = ['last_pulled_at'];

    protected $casts = [
        'last_pulled_at' => 'datetime',
    ];

    /**
     * Get the prunable model query.
     */
    public function prunable(): Builder
    {
        return static::where('created_at', '<=', now()->subDay());
    }
}
