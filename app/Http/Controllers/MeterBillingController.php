<?php

namespace App\Http\Controllers;

use App\Http\Requests\MpesaTransactionRequest;
use App\Jobs\ProcessTransaction;
use App\Jobs\QueryTransactionValidity;
use App\Models\MpesaTransaction;
use App\Services\MpesaService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;
use JsonException;
use Throwable;

class MeterBillingController extends Controller
{

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
    public function mpesaValidation(Request $mpesa_transaction, MpesaService $mpesaService): Response
    {
        return $mpesaService->validateTransaction($mpesa_transaction);
    }

    /**
     * @throws JsonException|Throwable
     */
    public function mpesaConfirmation(MpesaTransactionRequest $request, MpesaService $mpesaService): Response
    {
        if ($mpesaService->isValidSafaricomIpAddress($request->ip())) {
            $mpesaService->acceptTransaction($request);

            $response = new Response();
            $response->headers->set('Content-Type', 'text/xml; charset=utf-8');
            $response->setContent(json_encode(['C2BPaymentConfirmationResult' => 'Success'], JSON_THROW_ON_ERROR));
            return $response;
        }
        \Log::info('IP mismatch: '.$request->ip());
        if ($mpesaService->isValidPaybillNumber($request->BusinessShortCode)){
            $mpesaService->storeUnverifiedTransaction($request);
            QueryTransactionValidity::dispatch($request->TransID);
        }
        return response(['message' => 'End of road, goodbye.'], 408);
    }

    public function mspaceMpesaConfirmation(Request $request): JsonResponse
    {
        Log::info('mspaceMpesaConfirmation', ['request' => $request->all()]);
        $request->validate([
            'transID' => 'unique:mpesa_transactions'
        ]);
        $mpesa_transaction = $this->storeMspaceMpesaTransaction($request);
        ProcessTransaction::dispatch($mpesa_transaction);
        return response()->json('accepted');
    }

    /**
     * @param Request $mpesa_transaction
     * @return mixed
     */
    public function storeMspaceMpesaTransaction(Request $mpesa_transaction): MpesaTransaction
    {
        return MpesaTransaction::create([
            'TransID' => $mpesa_transaction->transID,
            'TransAmount' => $mpesa_transaction->amount,
            'BusinessShortCode' => $mpesa_transaction->paybill,
            'BillRefNumber' => $mpesa_transaction->accNo,
            'OrgAccountBalance' => $mpesa_transaction->accBal,
            'MSISDN' => $mpesa_transaction->mobile,
            'FirstName' => $mpesa_transaction->name,
        ]);
    }

}
