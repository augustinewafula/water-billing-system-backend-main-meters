<?php

namespace App\Models;

use App\Traits\ClearsResponseCache;
use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\MassPrunable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class MeterCharge extends Model
{
    use HasFactory, HasUuid, ClearsResponseCache, SoftDeletes, MassPrunable;

    public $incrementing = false;

    protected $dateFormat = 'Y-m-d H:i:s.u';

    protected $fillable = ['cost_per_unit', 'service_charge_in_percentage'];

    /**
     * Get the service charge for the meter charge.
     * @return HasMany
     */
    public function service_charges(): HasMany
    {
        return $this->hasMany(ServiceCharge::class);
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
