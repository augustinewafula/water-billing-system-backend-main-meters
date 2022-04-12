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
use App\Traits\ProcessMonthlyServiceChargeTransaction;
use App\Traits\ProcessPrepaidMeterTransaction;
use App\Traits\StoreMeterBillings;
use Carbon\Carbon;
use DB;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use JsonException;
use Log;
use Throwable;

class MeterBillingController extends Controller
{
    use ProcessPrepaidMeterTransaction, calculateBill, StoreMeterBillings, ProcessMonthlyServiceChargeTransaction;

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
     * @throws Throwable
     */
    private function processMpesaTransaction(Request $content, $mpesa_transaction_id): void
    {
        $user = User::select('users.id as user_id', 'users.account_number', 'users.first_monthly_service_fee_on', 'meters.id as meter_id', 'meters.number as meter_number', 'meter_types.name as meter_type_name')
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

        $monthly_service_charge_deducted = 0;
        if ($this->hasMonthlyServiceChargeDebt($user)) {
            $monthly_service_charge_deducted = $this->storeMonthlyServiceCharge($user->user_id, $mpesa_transaction_id, $content->TransAmount);
        }

        if ($user->meter_type_name === 'Prepaid') {
            $this->processPrepaidTransaction($user->user_id, $content, $monthly_service_charge_deducted, $mpesa_transaction_id);
            return;

        }

        $this->processPostPaidTransaction($user, $content, $monthly_service_charge_deducted, $mpesa_transaction_id);
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
     * @param $user_id
     * @param Request $content
     * @param $monthly_service_charge_deducted
     * @param $mpesa_transaction_id
     * @return void
     * @throws JsonException|Throwable
     */
    private function processPrepaidTransaction($user_id, Request $content, $monthly_service_charge_deducted, $mpesa_transaction_id): void
    {
        $user = User::findOrFail($user_id);
        $user_total_amount = $content->TransAmount - $monthly_service_charge_deducted;
        if ($user->account_balance > 0) {
            $user_total_amount += $user->account_balance;
        }
        if ($user_total_amount <= 0) {
            $message = "Your paid amount is not enough to purchase tokens, Ksh $monthly_service_charge_deducted was deducted for monthly service fee balance.";
            SendSMS::dispatch($content->MSISDN, $message, $user->id);
            return;
        }
        $units = $this->calculateUnits($user_total_amount);
        if ($units < 1) {
            $message = 'Your paid amount is not enough to purchase tokens. ';
            if ($monthly_service_charge_deducted > 0) {
                $message .= "Ksh $monthly_service_charge_deducted was deducted for monthly service fee balance.";
            }
            try {
                DB::beginTransaction();
                $user->update([
                    'account_balance' => $user_total_amount
                ]);
                MpesaTransaction::find($mpesa_transaction_id)->update([
                    'Consumed' => true,
                ]);
                DB::commit();
            } catch (Throwable $throwable) {
                DB::rollBack();
                Log::error($throwable);
            }
            SendSMS::dispatch($content->MSISDN, $message, $user->id);
            return;
        }
        $token = strtok($this->top_up($user->meter_number, $user_total_amount), ',');

        try {
            DB::beginTransaction();
            MeterToken::create([
                'mpesa_transaction_id' => $mpesa_transaction_id,
                'token' => strtok($token, ','),
                'units' => $units,
                'service_fee' => $this->calculateServiceFee($user_total_amount, 'prepay'),
                'monthly_service_charge_deducted' => $monthly_service_charge_deducted,
                'meter_id' => $user->meter_id,
            ]);
            $user->update([
                'account_balance' => 0
            ]);
            MpesaTransaction::find($mpesa_transaction_id)->update([
                'Consumed' => true,
            ]);
            DB::commit();

        } catch (Throwable $throwable) {
            DB::rollBack();
            Log::error($throwable);
        }
        $date = Carbon::now()->toDateTimeString();
        $message = "Meter: $user->meter_number\nToken: $token\nUnits: $units\nAmount: $content->TransAmount\nDate: $date\nRef: $content->TransID";
        SendSMS::dispatch($content->MSISDN, $message, $user->user_id);
    }

    /**
     * @param $user
     * @param Request $content
     * @param $monthly_service_charge_deducted
     * @param $mpesa_transaction_id
     * @return void
     * @throws Throwable
     */
    private function processPostPaidTransaction($user, Request $content, $monthly_service_charge_deducted, $mpesa_transaction_id): void
    {
        $request = new CreateMeterBillingRequest();
        $request->setMethod('POST');
        $request->request->add([
            'meter_id' => $user->meter_id,
            'amount_paid' => $content->TransAmount,
            'monthly_service_charge_deducted' => $monthly_service_charge_deducted
        ]);
        //TODO::make organization name dynamic
        $message = "Dear $content->FirstName $content->LastName, your payment of Ksh $content->TransAmount to Progressive Utility has been received. Thank you for being our esteemed customer.";
        SendSMS::dispatch($content->MSISDN, $message, $user->user_id);

        $this->store($request, $mpesa_transaction_id);
    }
}
