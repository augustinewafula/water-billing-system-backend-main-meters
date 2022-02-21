<?php

namespace App\Http\Controllers;

use App\Enums\MeterReadingStatus;
use App\Enums\UnresolvedMpesaTransactionReason;
use App\Http\Requests\CreateMeterBillingRequest;
use App\Models\Meter;
use App\Models\MeterBilling;
use App\Models\MeterBillingReport;
use App\Models\MeterReading;
use App\Models\MeterToken;
use App\Models\MeterType;
use App\Models\MpesaTransaction;
use App\Models\UnresolvedMpesaTransaction;
use App\Models\User;
use App\Traits\ProcessPrepaidMeterTransaction;
use Carbon\Carbon;
use DB;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use JsonException;
use Log;
use Str;
use Throwable;

class MeterBillingController extends Controller
{
    use ProcessPrepaidMeterTransaction;
    /**
     * Display a listing of the resource.
     *
     * @return Response
     */
    public function index()
    {
        //
    }

    /**
     * @throws JsonException
     */
    public function mpesaConfirmation(Request $content): Response
    {
//        $content = json_decode($request->getContent(), false, 512, JSON_THROW_ON_ERROR);
        $mpesa_transaction_id = $this->storeMpesaTransaction($content);
        $this->processMpesaTransaction($content, $mpesa_transaction_id);

        $response = new Response();
        $response->headers->set('Content-Type', 'text/xml; charset=utf-8');
        $response->setContent(json_encode(['C2BPaymentConfirmationResult' => 'Success'], JSON_THROW_ON_ERROR));
        return $response;
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param CreateMeterBillingRequest $request
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

        foreach ($pending_meter_readings as $pending_meter_reading) {

            $user_total_amount = $request->amount_paid;
            if ($user->account_balance > 0) {
                $user_total_amount += $user->account_balance;
            }

            $bill_to_pay = $pending_meter_reading->bill;
            if ($pending_meter_reading->status === MeterReadingStatus::Balance) {
                $bill_to_pay = MeterBilling::where('meter_reading_id', $pending_meter_reading->id)
                    ->latest()
                    ->first()
                    ->balance;
            }
            $balance = $bill_to_pay - $user_total_amount;
            $this->saveBillingInfo(
                $request->amount_paid,
                $balance,
                $user,
                $pending_meter_reading,
                $mpesa_transaction_id);

        }

        return response()->json('created', 201);
    }

    public function userHasFullyPaid($balance): bool
    {
        return $balance <= 0;
    }

    /**
     * Display the specified resource.
     *
     * @param MeterBilling $meterBilling
     * @return Response
     */
    public function show(MeterBilling $meterBilling)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     *
     * @param Request $request
     * @param MeterBilling $meterBilling
     * @return Response
     */
    public function update(Request $request, MeterBilling $meterBilling)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param MeterBilling $meterBilling
     * @return Response
     */
    public function destroy(MeterBilling $meterBilling)
    {
        //
    }

    /**
     * @param $amount_paid
     * @param $balance
     * @param $user
     * @param $meter_reading
     * @return bool
     */
    public function saveBillingInfo(
        $amount_paid,
        $balance,
        $user,
        $meter_reading,
        $mpesa_transaction_id): bool
    {
        try {
            DB::transaction(function () use ($amount_paid, $balance, $user, $meter_reading, $mpesa_transaction_id) {
                $meter = Meter::find($meter_reading->meter_id);
                $meter->update([
                    'last_billing_date' => Carbon::now()->toDateTimeString(),
                ]);
                $user_bill_balance = $balance;
                if ($this->userHasFullyPaid($balance)) {
                    $user->update([
                        'account_balance' => abs($balance)
                    ]);
                    $meter_reading->update([
                        'status' => MeterReadingStatus::Paid,
                    ]);
                    $user_bill_balance = 0;
                } else {
                    $meter_reading->update([
                        'status' => MeterReadingStatus::Balance,
                    ]);
                }
                MeterBilling::updateOrCreate([
                    'meter_reading_id' => $meter_reading->id,
                    'amount_paid' => $amount_paid,
                    'balance' => $user_bill_balance,
                    'date_paid' => Carbon::now()->toDateTimeString(),
                    'mpesa_transaction_id' => $mpesa_transaction_id
                ]);
                $bill_month_name = Str::lower(Carbon::createFromFormat('Y-m', $meter_reading->month)->format('M'));
                $bill_year = Carbon::createFromFormat('Y-m', $meter_reading->month)->format('Y');
                $this->saveMeterBillingReport([
                    'meter_id' => $meter->id,
                    $bill_month_name => $user_bill_balance,
                    'year' => $bill_year,
                ]);
            });
            return true;
        } catch (Throwable $th) {
            Log::error($th);
            return false;
        }
    }

    public function saveMeterBillingReport($report): void
    {
        $meter_billing_report = MeterBillingReport::where('meter_id', $report['meter_id'])
            ->where('year', $report['year'])->first();
        if ($meter_billing_report) {
            $meter_billing_report->update($report);
            return;
        }
        MeterBillingReport::create($report);
    }

    /**
     * @param Request $content
     * @return String
     */
    public function storeMpesaTransaction(Request $content): string
    {
        $mpesa_transaction = new MpesaTransaction();
        $mpesa_transaction->TransactionType = $content->TransactionType;
        $mpesa_transaction->TransID = $content->TransID;
        $mpesa_transaction->TransTime = $content->TransTime;
        $mpesa_transaction->TransAmount = $content->TransAmount;
        $mpesa_transaction->BusinessShortCode = $content->BusinessShortCode;
        $mpesa_transaction->BillRefNumber = $content->BillRefNumber;
        $mpesa_transaction->InvoiceNumber = $content->InvoiceNumber;
        $mpesa_transaction->OrgAccountBalance = $content->OrgAccountBalance;
        $mpesa_transaction->ThirdPartyTransID = $content->ThirdPartyTransID;
        $mpesa_transaction->MSISDN = $content->MSISDN;
        $mpesa_transaction->FirstName = $content->FirstName;
        $mpesa_transaction->MiddleName = $content->MiddleName;
        $mpesa_transaction->LastName = $content->LastName;
        $mpesa_transaction->save();
        return $mpesa_transaction->id;
    }

    /**
     * @throws JsonException
     */
    private function processMpesaTransaction(Request $content, $mpesa_transaction_id): void
    {
        $meter = Meter::where('number', $content->BillRefNumber)
            ->first();
        if (!$meter) {
            UnresolvedMpesaTransaction::create([
                'mpesa_transaction_id' => $mpesa_transaction_id,
                'reason' => UnresolvedMpesaTransactionReason::InvalidAccountNumber
            ]);
            return;
        }

        if (MeterType::find($meter->type_id)->name === 'Prepaid') {
            $token = $this->top_up($meter->number, $content->TransAmount);
            $units = $content->TransAmount / 200;

            MeterToken::create([
                'mpesa_transaction_id' => $mpesa_transaction_id,
                'token' => strtok($token, ','),
                'units' => $units,
            ]);
            return;
        }
        $request = new CreateMeterBillingRequest();
        $request->setMethod('POST');
        $request->request->add([
            'meter_id' => $meter->id,
            'amount_paid' => $content->TransAmount
        ]);
        $this->store($request, $mpesa_transaction_id);
    }
}
