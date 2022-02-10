<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MeterCharge extends Model
{
    use HasFactory;

    protected $fillable = ['cost_per_unit', 'service_charge'];
}
