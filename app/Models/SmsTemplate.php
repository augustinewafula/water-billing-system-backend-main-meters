<?php

namespace App\Models;

use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SmsTemplate extends Model
{
    use HasFactory, HasUuid;

    public $incrementing = false;

    protected $keyType = 'uuid';

    protected $fillable = ['message'];
}
