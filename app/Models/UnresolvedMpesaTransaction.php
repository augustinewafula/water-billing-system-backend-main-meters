<?php

namespace App\Models;

use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class UnresolvedMpesaTransaction extends Model
{
    use HasFactory, HasUuid;

    public $incrementing = false;

    protected $keyType = 'uuid';

    protected $fillable = ['mpesa_transaction_id', 'reason'];
}
