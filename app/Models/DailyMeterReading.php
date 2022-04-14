<?php

namespace App\Models;

use App\Traits\ClearsResponseCache;
use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DailyMeterReading extends Model
{
    use HasFactory, HasUuid, ClearsResponseCache;

    public $incrementing = false;

    protected $keyType = 'uuid';

    protected $fillable = ['meter_id', 'reading'];
}
