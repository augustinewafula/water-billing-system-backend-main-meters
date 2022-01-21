<?php

namespace App\Models;

use App\Traits\Uuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Meter extends Model
{
    use HasFactory, Uuid;

    public $incrementing = false;

    protected $keyType = 'uuid';

    protected $fillable = ['number', 'valve_status', 'station_id', 'type'];
}
