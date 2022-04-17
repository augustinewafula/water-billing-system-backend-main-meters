<?php

namespace App\Models;

use App\Traits\ClearsResponseCache;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Setting extends Model
{
    use HasFactory, ClearsResponseCache;

    protected $dateFormat = 'Y-m-d H:i:s.u';

    protected $fillable = ['key', 'value'];
}
