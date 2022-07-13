<?php

namespace App\Services;

use App\Http\Requests\MpesaTransactionRequest;
use App\Models\MpesaTransaction;

class MpesaService
{
    public function store(MpesaTransactionRequest $mpesa_transaction): MpesaTransaction
    {
        return MpesaTransaction::create([
            'TransactionType' => $mpesa_transaction->TransactionType,
            'TransID' => $mpesa_transaction->TransID,
            'TransTime' => $mpesa_transaction->TransTime,
            'TransAmount' => $mpesa_transaction->TransAmount,
            'BusinessShortCode' => $mpesa_transaction->BusinessShortCode,
            'BillRefNumber' => $mpesa_transaction->BillRefNumber,
            'InvoiceNumber' => $mpesa_transaction->InvoiceNumber,
            'OrgAccountBalance' => $mpesa_transaction->OrgAccountBalance,
            'ThirdPartyTransID' => $mpesa_transaction->ThirdPartyTransID,
            'MSISDN' => $mpesa_transaction->MSISDN,
            'FirstName' => $mpesa_transaction->FirstName,
            'MiddleName' => $mpesa_transaction->MiddleName,
            'LastName' => $mpesa_transaction->LastName,
        ]);
    }

}
