<?php

namespace App\Models;

use App\Traits\ClearsResponseCache;
use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MonthlyServiceChargeReport extends Model
{
    use HasFactory, HasUuid, ClearsResponseCache;

    public $incrementing = false;

    protected $keyType = 'uuid';

    protected $fillable = ['user_id', 'jan', 'feb', 'mar', 'apr', 'may', 'jun', 'jul', 'aug', 'sep', 'oct', 'nov', 'dec', 'year'];

    protected $casts = [
        'year' => 'datetime:Y',
    ];
}
