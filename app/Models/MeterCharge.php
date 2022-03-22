<?php

namespace App\Models;

use App\Traits\Uuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MeterCharge extends Model
{
    use HasFactory, Uuid;

    public $incrementing = false;

    protected $fillable = ['cost_per_unit', 'service_charge'];
}
