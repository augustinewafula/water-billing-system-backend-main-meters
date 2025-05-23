<?php

namespace App\Models;

use App\Traits\DisableableTrait;
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
use Illuminate\Support\Str;
use Laravel\Passport\HasApiTokens;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Permission\Traits\HasRoles;
use Spatie\Activitylog\LogOptions;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable, HasUuid, HasRoles, SoftDeletes, MassPrunable, LogsActivity, DisableableTrait;

    public $incrementing = false;

    protected $keyType = 'uuid';

    protected $dateFormat = 'Y-m-d H:i:s.u';

    protected $guard_name = 'api';

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
        'total_connection_fee_paid',
        'account_balance',
        'account_number',
        'use_custom_charges_for_cost_per_unit',
        'cost_per_unit',
        'communication_channels',
        'unaccounted_debt',
        'should_reset_password',
        'connection_fee',
        'number_of_months_to_pay_connection_fee',
        'use_custom_charges_for_service_charge',
        'service_charge',
        'should_notify_user',
        'is_disabled',
        'should_pay_monthly_service_charge',
        'monthly_service_charge',
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
        'is_disabled' => 'boolean',
        'should_pay_monthly_service_charge' => 'boolean',
    ];

    protected $appends = [
        'total_monthly_service_charge_debt',
    ];


    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly($this->fillable);
        // Chain fluent methods for configuration options
    }

    /**
     * Set the user's name.
     *
     * @param string $value
     * @return void
     */
    public function setNameAttribute(string $value): void
    {
        $this->attributes['name'] = Str::of($value)
            ->lower()
            ->title();
    }

    public function setFirstConnectionFeeOnAttribute($value): void
    {
        $this->attributes['first_connection_fee_on'] = null;
        if ($value){
            $this->attributes['first_connection_fee_on'] = Carbon::parse($value)->format('Y-m-d H:i:s');
        }

    }


    public function getFirstConnectionFeeOnAttribute($value)
    {
        return $value ? Carbon::parse($value)->format('Y-m-d') : null;
    }
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

    public function hasFundsInAccount(): bool
    {
        return $this->account_balance > 0;
    }

    public function hasNoFundsInAccount(): bool
    {
        return $this->account_balance <= 0;
    }

    public function monthlyServiceCharges(): HasMany
    {
        return $this->hasMany(MonthlyServiceCharge::class);
    }

    public function getTotalMonthlyServiceChargeDebtAttribute(): float
    {
        return $this->getTotalMonthlyServiceChargeDebt();
    }

    /**
     * Get total service charge debt for the user.
     *
     * @return float
     */
    public function getTotalMonthlyServiceChargeDebt(): float
    {
        return $this->monthlyServiceCharges()
            ->with('monthlyServiceChargePayments')
            ->get()
            ->reduce(function (float $carry, MonthlyServiceCharge $charge) {
                $totalPaid = $charge->monthlyServiceChargePayments->sum('amount_paid');
                $debt = max(0, $charge->service_charge - $totalPaid);
                return $carry + $debt;
            }, 0.0);
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
