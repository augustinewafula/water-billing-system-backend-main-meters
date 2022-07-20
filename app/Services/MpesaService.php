<?php

namespace App\Services;

use App\Enums\UnverifiedMpesaTransactionStatus;
use App\Http\Requests\MpesaTransactionRequest;
use App\Jobs\ProcessTransaction;
use App\Models\Meter;
use App\Models\MeterStation;
use App\Models\MpesaTransaction;
use App\Models\UnverifiedMpesaTransaction;
use App\Models\User;
use App\Traits\CalculatesBill;
use App\Traits\NotifiesUser;
use Http;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use JsonException;
use Log;
use Spatie\WebhookServer\WebhookCall;
use Throwable;

class MpesaService
{
    use NotifiesUser, CalculatesBill;

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

    public function storeUnverifiedTransaction(MpesaTransactionRequest $mpesa_transaction): UnverifiedMpesaTransaction
    {
        return UnverifiedMpesaTransaction::create([
            'ClientIp' => $mpesa_transaction->ip(),
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

    public function acceptTransaction(MpesaTransactionRequest $request): void
    {
        $mpesa_transaction = $this->store($request);
        ProcessTransaction::dispatch($mpesa_transaction);
        $this->initiateWebhook($request->all());
    }

    /**
     * @throws JsonException
     */
    public function validateTransaction(Request $mpesa_transaction): Response
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

    public function handleTransactionStatusResult(Request $request): void
    {
        $unverified_mpesa_transaction = UnverifiedMpesaTransaction::where('ConversationID', $request->ConversationID)->firstOrFail();
        if ($request->ResponseCode === '0'){
            $mpesa_transaction_request = new MpesaTransactionRequest();
            $mpesa_transaction_request->setMethod('POST');
            $mpesa_transaction_request->request->add($unverified_mpesa_transaction);
            try {
                $mpesa_transaction_request->validate((new MpesaTransactionRequest)->rules());
                $this->acceptTransaction($mpesa_transaction_request);
                $unverified_mpesa_transaction->delete();
            }catch (Throwable $throwable){
                \Log::error($throwable);
            }

            return;
        }
        $unverified_mpesa_transaction->status = UnverifiedMpesaTransactionStatus::FRAUDLET;
        $unverified_mpesa_transaction->save();

    }

    public  function handleTransactionStatusQueueTimeout(Request $request): void
    {
        $unverified_mpesa_transaction = UnverifiedMpesaTransaction::where('ConversationID', $request->ConversationID)->firstOrFail();
        $unverified_mpesa_transaction->update(['status' => UnverifiedMpesaTransactionStatus::UNVERIFIED]);
    }

    public function initiateWebhook($request): void
    {
        $mpesa_transaction_callback_url = env('TRANSACTION_CALLBACK_URL');
        if ($mpesa_transaction_callback_url){
            WebhookCall::create()
                ->url($mpesa_transaction_callback_url)
                ->payload($request)
                ->useSecret('sign-using-this-secret')
                ->dispatch();
        }
    }

    public function isValidSafaricomIpAddress($clientIpAddress): bool
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

        return in_array($clientIpAddress, $whitelist, false);
    }

    public function isValidPaybillNumber($paybill_number): bool
    {
        return in_array($paybill_number, $this->validPaybillNumbers(), false);
    }

    public function validPaybillNumbers(): array
    {
        return MeterStation::pluck('paybill_number')
            ->all();
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
                if (env('MINIMUM_AMOUNT_FOR_TOKEN')){
                    $minimum_amount = env('MINIMUM_AMOUNT_FOR_TOKEN');
                }
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
        $minimum_amount_allowed = env('MINIMUM_AMOUNT_FOR_TOKEN');
        if ($amount_paid < $minimum_amount_allowed) {
            return false;
        }
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
    public function generateAccessToken()
    {
        $consumer_key=env('MPESA_CONSUMER_KEY');
        $consumer_secret=env('MPESA_CONSUMER_SECRET');
        $credentials = base64_encode($consumer_key. ':' .$consumer_secret);
        $url = 'https://api.safaricom.co.ke/oauth/v1/generate?grant_type=client_credentials';

        $response = Http::withHeaders([
            'Authorization' => 'Basic '.$credentials,
            'Content-Type' => 'application/json'
        ])->get($url);
        if ($response->successful()) {
            Log::info('generateAccessToken response'.$response->body());
            return json_decode($response->body(), false, 512, JSON_THROW_ON_ERROR)->access_token;
        }
        return null;
    }

}
