<?php

namespace App\Traits;

use App\Http\Requests\CreditAccountRequest;
use App\Http\Requests\MpesaTransactionRequest;
use App\Jobs\ProcessTransaction;
use App\Models\User;
use App\Services\MpesaService;

trait CreditsUserAccount
{
    use ConvertsPhoneNumberToInternationalFormat;

    public function creditUserAccount(CreditAccountRequest $request, MpesaService $mpesaService): void
    {
        $user = User::findOrFail($request->user_id);
        if (empty($request->mpesa_transaction_reference)) {
            $transaction_id = 'ST_'.now()->getPreciseTimestamp(3);
        } else {
            $transaction_id = $request->mpesa_transaction_reference;
        }
        $account_number = $user->account_number;
        if ($request->account_type === 2) {
            $account_number = $user->account_number.'-meter';
        }

        $mpesa_request = new MpesaTransactionRequest();
        $mpesa_request->setMethod('POST');
        $mpesa_request->request->add([
            'TransID' => $transaction_id,
            'TransTime' => now()->getPreciseTimestamp(3),
            'TransAmount' => $request->amount,
            'FirstName' => $user->name,
            'MSISDN' => $this->phoneNumberToInternationalFormat($user->phone),
            'BillRefNumber' => $account_number,
        ]);

        $mpesa_request->validate((new MpesaTransactionRequest)->rules());
        $mpesa_transaction = $mpesaService->store($mpesa_request);
        ProcessTransaction::dispatch($mpesa_transaction);

    }

}
