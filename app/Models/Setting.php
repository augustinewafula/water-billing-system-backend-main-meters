<?php

namespace App\Models;

use App\Traits\ClearsResponseCache;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Setting extends Model
{
    use HasFactory, ClearsResponseCache, SoftDeletes;

    protected $dateFormat = 'Y-m-d H:i:s.u';

    protected $fillable = ['key', 'value'];
}
