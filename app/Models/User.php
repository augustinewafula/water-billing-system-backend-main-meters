<?php

namespace App\Models;

use App\Traits\ClearsResponseCache;
use App\Traits\HasUuid;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\MassPrunable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Passport\HasApiTokens;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable, HasUuid, HasRoles, ClearsResponseCache, SoftDeletes, MassPrunable, LogsActivity;

    public $incrementing = false;

    protected $keyType = 'uuid';

    protected $dateFormat = 'Y-m-d H:i:s.u';

    protected $guard_name = 'api';

    protected static $submitEmptyLogs = false;

    protected static $logFillable = true;

    /**
     * Set the user's name.
     *
     * @param string $value
     * @return void
     */
    public function setNameAttribute(string $value): void
    {
        $this->attributes['name'] = ucwords($value);
    }

    public function setFirstConnectionFeeOnAttribute($value): void
    {
        $this->attributes['first_connection_fee_on'] = null;
        if ($value){
            $this->attributes['first_connection_fee_on'] = Carbon::parse($value)->format('Y-m-d');
        }

    }


    public function getFirstConnectionFeeOnAttribute($value)
    {
        return $value ? Carbon::parse($value)->format('Y-m') : null;
    }

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'phone',
        'email',
        'meter_id',
        'password',
        'first_monthly_service_fee_on',
        'first_connection_fee_on',
        'last_mpesa_transaction_id',
        'should_pay_connection_fee',
        'account_balance',
        'account_number',
        'use_custom_charges_for_cost_per_unit',
        'cost_per_unit',
        'communication_channels',
        'unaccounted_debt'
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
        'first_monthly_service_fee_on' => 'datetime',
        'communication_channels' => 'array',
    ];

    /**
     * Get the user that owns the meter.
     * @return belongsTo
     */
    public function meter(): belongsTo
    {
        return $this->belongsTo(Meter::class);
    }

    public function unaccounted_debts(): HasMany
    {
        return $this->hasMany(UnaccountedDebt::class);
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
