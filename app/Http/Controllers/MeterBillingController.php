<?php

namespace App\Http\Controllers;

use App\Enums\MeterReadingStatus;
use App\Enums\UnresolvedMpesaTransactionReason;
use App\Http\Requests\CreateMeterBillingRequest;
use App\Jobs\SendSMS;
use App\Jobs\SwitchOnPaidMeter;
use App\Models\Meter;
use App\Models\MeterReading;
use App\Models\MeterToken;
use App\Models\MpesaTransaction;
use App\Models\UnresolvedMpesaTransaction;
use App\Models\User;
use App\Traits\calculateBill;
use App\Traits\ProcessPrepaidMeterTransaction;
use App\Traits\StoreMeterBillings;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use JsonException;
use Log;

class MeterBillingController extends Controller
{
    use ProcessPrepaidMeterTransaction, calculateBill, StoreMeterBillings;

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
     * @throws JsonException
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
        $mpesa_transaction_id = $this->storeMpesaTransaction($request);
        if ($mpesa_transaction_id) {
            $this->processMpesaTransaction($request, $mpesa_transaction_id);
        }

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
     * @param Request $content
     * @return String
     */
    public function storeMpesaTransaction(Request $content): ?string
    {
        return MpesaTransaction::create([
            'TransactionType' => $content->TransactionType,
            'TransID' => $content->TransID,
            'TransTime' => $content->TransTime,
            'TransAmount' => $content->TransAmount,
            'BusinessShortCode' => $content->BusinessShortCode,
            'BillRefNumber' => $content->BillRefNumber,
            'InvoiceNumber' => $content->InvoiceNumber,
            'OrgAccountBalance' => $content->OrgAccountBalance,
            'ThirdPartyTransID' => $content->ThirdPartyTransID,
            'MSISDN' => $content->MSISDN,
            'FirstName' => $content->FirstName,
            'MiddleName' => $content->MiddleName,
            'LastName' => $content->LastName,
        ])->id;
    }

    /**
     * @throws JsonException
     */
    private function processMpesaTransaction(Request $content, $mpesa_transaction_id): void
    {
        $user = User::select('users.id as user_id', 'users.account_number', 'meters.id as meter_id', 'meters.number as meter_number', 'meter_types.name as meter_type_name')
            ->join('meters', 'meters.id', 'users.meter_id')
            ->leftJoin('meter_types', 'meter_types.id', 'meters.type_id')
            ->where('account_number', $content->BillRefNumber)
            ->first();
        if (!$user) {
            UnresolvedMpesaTransaction::create([
                'mpesa_transaction_id' => $mpesa_transaction_id,
                'reason' => UnresolvedMpesaTransactionReason::InvalidAccountNumber
            ]);
            return;
        }

        if ($user->meter_type_name === 'Prepaid') {
            $token = strtok($this->top_up($user->meter_number, $content->TransAmount), ',');
            $units = $this->calculateUnits($content->TransAmount);

            MeterToken::create([
                'mpesa_transaction_id' => $mpesa_transaction_id,
                'token' => strtok($token, ','),
                'units' => $units,
                'service_fee' => $this->calculateServiceFee($content->TransAmount, 'prepay'),
                'meter_id' => $user->meter_id,
            ]);
            $date = Carbon::now()->toDateTimeString();
            $message = "Meter: $user->meter_number\nToken: $token\nUnits: $units\nAmount: $content->TransAmount\nDate: $date\nRef: $content->TransID";
            SendSMS::dispatch($content->MSISDN, $message, $user->user_id);
            return;

        }

        $request = new CreateMeterBillingRequest();
        $request->setMethod('POST');
        $request->request->add([
            'meter_id' => $user->meter_id,
            'amount_paid' => $content->TransAmount
        ]);
        //TODO::make organization name dynamic
        $message = "Dear $content->FirstName $content->LastName, your payment of Ksh $content->TransAmount to Progressive Utility has been received. Thank you for being our esteemed customer.";
        SendSMS::dispatch($content->MSISDN, $message, $user->user_id);

        $this->store($request, $mpesa_transaction_id);
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
