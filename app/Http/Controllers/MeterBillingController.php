<?php

namespace App\Http\Controllers;

use App\Models\MpesaTransaction;
use App\Models\User;
use App\Traits\ProcessesMpesaTransaction;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use JsonException;
use Log;
use Throwable;

class MeterBillingController extends Controller
{
    use ProcessesMpesaTransaction;

    public function __construct()
    {
        $this->middleware('permission:meter-reading-list', ['only' => ['index', 'show']]);
        $this->middleware('permission:meter-reading-create', ['only' => ['store', 'resend']]);
        $this->middleware('permission:meter-reading-edit', ['only' => ['update']]);
        $this->middleware('permission:meter-reading-delete', ['only' => ['destroy']]);
    }

    /**
     * @throws JsonException
     */
    public function createValidationResponse($result_code, $result_description): Response
    {
        $result = json_encode(['ResultCode' => $result_code, 'ResultDesc' => $result_description], JSON_THROW_ON_ERROR);
        $response = new Response();
        $response->headers->set('Content-Type', 'application/json; charset=utf-8');
        $response->setContent($result);
        return $response;
    }

    /**
     * @throws JsonException
     */
    public function mpesaValidation(Request $mpesa_transaction): Response
    {
        if ($this->accountNumberExists($mpesa_transaction->BillRefNumber)){
            $result_code = '0';
            $result_description = 'Accepted validation request.';
        }else{
            $result_code = '1';
            $result_description = 'Rejected validation request.';
        }
        return $this->createValidationResponse($result_code, $result_description);
    }

    public function accountNumberExists($bill_reference_number): bool
    {
        $user = User::select('account_number')
            ->where('account_number', $bill_reference_number)
            ->first();
        if ($user){
            return true;
        }
        return false;

    }


    /**
     * @throws JsonException|Throwable
     */
    public function mpesaConfirmation(Request $request)
    {
        $client_ip = $request->ip();
        if (!$this->safaricomIpAddress($client_ip)) {
            Log::notice("Ip $client_ip has been stopped from accessing transaction url");
            Log::notice($request);
            $response = ['message' => 'Nothing interesting around here.'];
            return response()->json($response, 418);
        }

        $request->validate([
            'TransID' => 'unique:mpesa_transactions'
        ]);
        $mpesa_transaction = $this->storeMpesaTransaction($request);
        $this->processMpesaTransaction($mpesa_transaction);


        $response = new Response();
        $response->headers->set('Content-Type', 'text/xml; charset=utf-8');
        $response->setContent(json_encode(['C2BPaymentConfirmationResult' => 'Success'], JSON_THROW_ON_ERROR));
        return $response;
    }


    /**
     * @param Request $mpesa_transaction
     * @return mixed
     */
    public function storeMpesaTransaction(Request $mpesa_transaction): MpesaTransaction
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

    private function safaricomIpAddress($clientIpAddress): bool
    {
        $whitelist = [
            '196.201.214.200',
            '196.201.214.206',
            '196.201.213.114',
            '196.201.214.207',
            '196.201.214.208',
            '196.201.213.44',
            '196.201.212.127',
            '196.201.212.128',
            '196.201.212.129',
            '196.201.212.132',
            '196.201.212.136',
            '196.201.212.74',
            '196.201.212.138',
            '196.201.212.69'];

        return in_array($clientIpAddress, $whitelist, true);
    }

}
