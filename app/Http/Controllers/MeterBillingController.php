<?php

namespace App\Http\Controllers;

use App\Enums\MeterReadingStatus;
use App\Enums\UnresolvedMpesaTransactionReason;
use App\Http\Requests\CreateMeterBillingRequest;
use App\Jobs\SendSMS;
use App\Jobs\SwitchOnPaidMeter;
use App\Models\Meter;
use App\Models\MeterReading;
use App\Models\MpesaTransaction;
use App\Models\UnresolvedMpesaTransaction;
use App\Models\User;
use App\Traits\CalculatesBill;
use App\Traits\ProcessMonthlyServiceChargeTransaction;
use App\Traits\ProcessPrepaidMeterTransaction;
use App\Traits\StoreMeterBillings;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use JsonException;
use Log;
use Throwable;

class MeterBillingController extends Controller
{
    use ProcessPrepaidMeterTransaction, CalculatesBill, StoreMeterBillings, ProcessMonthlyServiceChargeTransaction;

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
    public function mpesaValidation(Request $request): Response
    {
        $result_code = '0';
        $result_description = 'Accepted validation request.';
        return $this->createValidationResponse($result_code, $result_description);
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
     * Store a newly created resource in storage.
     *
     * @param CreateMeterBillingRequest $request
     * @param $mpesa_transaction_id
     * @return JsonResponse
     * @throws Throwable
     */
    public function store(CreateMeterBillingRequest $request, $mpesa_transaction_id): JsonResponse
    {
        $pending_meter_readings = MeterReading::where('meter_id', $request->meter_id)
            ->where(function ($query) {
                $query->where('status', MeterReadingStatus::NotPaid);
                $query->orWhere('status', MeterReadingStatus::Balance);
            })
            ->orderBy('created_at', 'ASC')->get();

        if ($pending_meter_readings->count() === 0) {
            UnresolvedMpesaTransaction::create([
                'mpesa_transaction_id' => $mpesa_transaction_id,
                'reason' => UnresolvedMpesaTransactionReason::MeterReadingNotFound
            ]);
            return response()->json('Meter reading not found', 422);
        }

        $user = User::where('meter_id', $request->meter_id)->first();

        if (!$user) {
            UnresolvedMpesaTransaction::create([
                'mpesa_transaction_id' => $mpesa_transaction_id,
                'reason' => UnresolvedMpesaTransactionReason::UserNotFound
            ]);
            return response()->json('No user assigned to this meter', 422);
        }

        $this->processMeterBillings($request, $pending_meter_readings, $user, $mpesa_transaction_id);
        SwitchOnPaidMeter::dispatch(Meter::find($request->meter_id));
        return response()->json('created', 201);
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

    /**
     * @throws JsonException
     * @throws Throwable
     */
    private function processMpesaTransaction(MpesaTransaction $mpesa_transaction): void
    {
        $user = User::select('users.id as user_id', 'users.account_number', 'users.first_monthly_service_fee_on', 'meters.id as meter_id', 'meters.number as meter_number', 'meter_types.name as meter_type_name')
            ->join('meters', 'meters.id', 'users.meter_id')
            ->leftJoin('meter_types', 'meter_types.id', 'meters.type_id')
            ->where('account_number', $mpesa_transaction->BillRefNumber)
            ->first();
        if (!$user) {
            UnresolvedMpesaTransaction::create([
                'mpesa_transaction_id' => $mpesa_transaction->id,
                'reason' => UnresolvedMpesaTransactionReason::InvalidAccountNumber
            ]);
            return;
        }

        $monthly_service_charge_deducted = 0;
        if ($this->hasMonthlyServiceChargeDebt($user)) {
            $monthly_service_charge_deducted = $this->storeMonthlyServiceCharge($user->user_id, $mpesa_transaction);
        }

        if ($user->meter_type_name === 'Prepaid') {
            $this->processPrepaidTransaction($user->meter_id, $mpesa_transaction, $monthly_service_charge_deducted);
            return;

        }

        $this->processPostPaidTransaction($user, $mpesa_transaction, $monthly_service_charge_deducted);
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


    /**
     * @param $user
     * @param MpesaTransaction $mpesa_transaction
     * @param $monthly_service_charge_deducted
     * @return void
     * @throws Throwable
     */
    private function processPostPaidTransaction($user, MpesaTransaction $mpesa_transaction, $monthly_service_charge_deducted): void
    {
        $request = new CreateMeterBillingRequest();
        $request->setMethod('POST');
        $request->request->add([
            'meter_id' => $user->meter_id,
            'amount_paid' => $mpesa_transaction->TransAmount,
            'monthly_service_charge_deducted' => $monthly_service_charge_deducted
        ]);
        //TODO::make organization name dynamic
        $message = "Dear $mpesa_transaction->FirstName $mpesa_transaction->LastName, your payment of Ksh $mpesa_transaction->TransAmount to Progressive Utility has been received. Thank you for being our esteemed customer.";
        SendSMS::dispatch($mpesa_transaction->MSISDN, $message, $user->user_id);

        $this->store($request, $mpesa_transaction->id);
    }
}
