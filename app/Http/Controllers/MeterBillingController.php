<?php

namespace App\Http\Controllers;

use App\Http\Requests\MpesaTransactionRequest;
use App\Jobs\ProcessTransaction;
use App\Models\Meter;
use App\Models\MeterStation;
use App\Models\MpesaTransaction;
use App\Models\User;
use App\Traits\CalculatesBill;
use App\Traits\NotifiesUser;
use App\Traits\StoresMpesaTransaction;
use Http;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use JsonException;
use Spatie\WebhookServer\WebhookCall;
use Throwable;

class MeterBillingController extends Controller
{
    use StoresMpesaTransaction, NotifiesUser, CalculatesBill;

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
        if ($this->shouldAcceptTransaction($mpesa_transaction->BillRefNumber, $mpesa_transaction->TransAmount)){
            $result_code = '0';
            $result_description = 'Accepted validation request.';
        }else{
            $result_code = '1';
            $result_description = 'Rejected validation request.';
        }
        return $this->createValidationResponse($result_code, $result_description);
    }

    public function shouldAcceptTransaction($bill_reference_number, $amount_paid): bool
    {
        if ($user = $this->accountNumberExists($bill_reference_number)){
            if (!$user->can_generate_token){
                $message = 'Your account is not allowed to generate tokens. Please contact the administrator for more details.';
                $this->notifyUser((object)['message' => $message, 'title' => 'Payment rejected'], $user, 'general');
            }

            if ($this->isPrepaidMeter($user->meter_id) && !$this->isAmountEnoughToGenerateToken($amount_paid, $user)){
                $minimum_amount = $this->getMinimumAmountToGenerateToken($user);
                $message = 'Amount paid is not enough to acquire token. A minimum of Ksh '.$minimum_amount.' is required.';
                $this->notifyUser((object)['message' => $message, 'title' => 'Payment rejected'], $user, 'general');

                return false;
            }

            return $user->can_generate_token;
        }

        return true;
    }

    public function isPrepaidMeter($meter_id): bool
    {
        $meter_type = Meter::select('meter_types.name')
            ->join('meter_types', 'meters.type_id', 'meter_types.id')
            ->where('meters.id', $meter_id)
            ->value('name');
        return $meter_type === 'Prepaid';
    }

    public function isAmountEnoughToGenerateToken($amount_paid, $user): bool
    {
        $units = $this->calculateUnits($amount_paid, $user);
        return $units > 0;
    }

    public function getMinimumAmountToGenerateToken($user): float
    {
        $MINIMUM_UNITS_TO_GENERATE = 0.1;
        $service_fee = $this->calculateServiceFee(10, 'prepay');
        $cost_per_unit = $this->getCostPerUnit($user);
        $amount_required_for_minimum_units = $MINIMUM_UNITS_TO_GENERATE * $cost_per_unit;
        return $service_fee + $amount_required_for_minimum_units;
    }

    public function accountNumberExists($bill_reference_number): Model|Builder|null
    {
        return User::select('users.id', 'users.account_number', 'users.communication_channels', 'users.email', 'users.phone', 'meters.id as meter_id', 'meters.can_generate_token')
            ->join('meters', 'meters.id', 'users.meter_id')
            ->where('account_number', $bill_reference_number)
            ->first();

    }


    /**
     * @throws JsonException|Throwable
     */
    public function mpesaConfirmation(MpesaTransactionRequest $request): Response
    {
        $client_ip = $request->ip();
//        if (!$this->isValidSafaricomIpAddress($client_ip)) {
//            if (!$this->isValidPaybillNumber($request->BusinessShortCode)){
//                Log::notice("Ip $client_ip has been stopped from accessing transaction url");
//                Log::notice($request);
//                $response = ['message' => 'Nothing interesting around here.'];
//                return response()->json($response, 418);
//            }
//            $this->queryMpesaTransactionStatus($request);
//        }


        $mpesa_transaction = $this->storeMpesaTransaction($request);
        ProcessTransaction::dispatch($mpesa_transaction);
//        $this->queryMpesaTransactionStatus($request);
        $mpesa_transaction_callback_url = env('TRANSACTION_CALLBACK_URL');
        if ($mpesa_transaction_callback_url){
            WebhookCall::create()
                ->url($mpesa_transaction_callback_url)
                ->payload($request->all())
                ->useSecret('sign-using-this-secret')
                ->dispatch();
        }


        $response = new Response();
        $response->headers->set('Content-Type', 'text/xml; charset=utf-8');
        $response->setContent(json_encode(['C2BPaymentConfirmationResult' => 'Success'], JSON_THROW_ON_ERROR));
        return $response;
    }

    public function mspaceMpesaConfirmation(Request $request): JsonResponse
    {
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

    private function isValidSafaricomIpAddress($clientIpAddress): bool
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

    public function queryMpesaTransactionStatus($request): void
    {
        $data = [
            'Initiator' => 'gkymaina',
            'SecurityCredential' => 'O7ZEQ5sbvUXcOCfmor6bqV9HgrtgFZI2F9V1oAO7sps0phO7jawWqmDNZlNK+tf/wjpS9NJdnN3D1h/NGTr160YdkLAaK1N0GnQujm0iCLDvO4fJpkNP0A/AugntB3KbNO3552dd6PfmMaQQUA5Z8QvmaxZlOELxrLtQKIncx3Yep8SNEEzFmY6vQx2n6WfbCnikQ13h7zDQAU9+m8ZHza2tFd9d/pKbUAP7WVXELZoLDggxitnjouh/g790dvEsgZb+mx87xC2hJwkk/NRM/CsL2IAbo1CR5l++Jbq++JUBEP5iA0DIUAn+BYtQCv6XSw9QjV5db27Q5P5u0oM7WA==',
            'CommandID' => 'TransactionStatusQuery',
            'TransactionID' => 'QDQ7Z9J7YN',
            'PartyA' => '994470',
            'IdentifierType' => '1',
            'ResultURL' => '',
            'QueueTimeOutURL' => '',
            'Remarks' => 'Confirming',
            'Occasion' => 'Ip mismatch'
        ];
        $response = Http::retry(2, 100)
            ->post('https://api.safaricom.co.ke/mpesa/transactionstatus/v1/query', $data);
        if ($response->successful()) {
            \Log::info($response->body());
        }

    }

    public function isValidPaybillNumber($paybill_number): bool
    {
        return in_array($paybill_number, $this->validPaybillNumbers(), true);
    }

    public function validPaybillNumbers(): array
    {
        return MeterStation::pluck('paybill_number')
            ->all();
    }

}
