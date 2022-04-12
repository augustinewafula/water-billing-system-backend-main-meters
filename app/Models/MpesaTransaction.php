<?php

namespace App\Models;

use App\Traits\Uuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MpesaTransaction extends Model
{
    use HasFactory, Uuid;

    public $incrementing = false;

    protected $keyType = 'uuid';

    protected $fillable = ['FirstName', 'MiddleName', 'LastName', 'TransactionType', 'TransID', 'TransTime', 'BusinessShortCode', 'BillRefNumber', 'InvoiceNumber', 'ThirdPartyTransID', 'MSISDN', 'TransAmount', 'OrgAccountBalance', 'Consumed'];
}
