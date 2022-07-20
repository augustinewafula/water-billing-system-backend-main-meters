<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class UnverifiedMpesaTransaction extends Model
{
    use HasFactory;

    public $incrementing = false;

    protected $keyType = 'uuid';

    protected $dateFormat = 'Y-m-d H:i:s.u';

    protected $fillable = ['ConversationalId', 'Originator', 'ClientIp', 'FirstName', 'MiddleName', 'LastName', 'TransactionType', 'TransID', 'TransTime', 'BusinessShortCode', 'BillRefNumber', 'InvoiceNumber', 'ThirdPartyTransID', 'MSISDN', 'TransAmount', 'OrgAccountBalance', 'Status'];
}
